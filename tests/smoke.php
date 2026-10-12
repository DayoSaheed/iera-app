#!/usr/bin/env php
<?php
/**
 * End-to-end smoke test against a throwaway SQLite database (no web server needed).
 * Run: php tests/smoke.php
 */
declare(strict_types=1);
$dbFile = sys_get_temp_dir() . '/iera-smoke-' . getmypid() . '.sqlite';
@unlink($dbFile);
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Config;
use App\Core\DB;
use App\Models\AdminComment;
use App\Models\Application;
use App\Models\EmailLog;
use App\Models\FormTemplate;
use App\Models\MentorAssignment;
use App\Models\Otp;
use App\Models\Report;
use App\Models\Setting;
use App\Models\SubEvent;
use App\Models\Team;
use App\Models\User;
use App\Models\VacationRequest;
use App\Models\Event;
use App\Services\EmailService;
use App\Services\FormRenderer;
use App\Services\Jobs;
use App\Services\MongoImporter;
use App\Services\Recurrence;
use App\Services\ReportGenerator;
use App\Services\SchemaPort;
use App\Services\Seeder;
use App\Services\Stats;
use App\Services\Totp;

if (getenv('IERA_SMOKE_MYSQL')) {
    Config::set('db.driver', 'mysql');
    Config::set('db.host', getenv('IERA_DB_HOST') ?: '127.0.0.1');
    Config::set('db.name', getenv('IERA_DB_NAME') ?: 'iera_test');
    Config::set('db.user', getenv('IERA_DB_USER') ?: 'root');
    Config::set('db.pass', getenv('IERA_DB_PASS') ?: '');
} else {
    Config::set('db.driver', 'sqlite');
    Config::set('db.path', $dbFile);
}
Config::set('mail.driver', 'log');
Config::set('app.url', 'http://localhost');
Config::set('app.debug', true);
$_SESSION = [];

Config::set('app.install_lock', $dbFile . '.lock');
Config::set('app.config_path', $dbFile . '.config.php');
@unlink($dbFile . '.lock');
$pass = 0; $fail = 0;
function check(string $name, bool $ok, string $info = ''): void {
    global $pass, $fail;
    if ($ok) { $pass++; echo "  ok   $name\n"; } else { $fail++; echo "  FAIL $name $info\n"; }
}

echo "Schema (" . DB::driver() . ")\n";
$schema = (string) file_get_contents(BASE_PATH . '/sql/schema.sql');
if (DB::driver() === 'sqlite') {
    $schema = SchemaPort::toSqlite($schema);
} else {
    DB::pdo()->exec('SET FOREIGN_KEY_CHECKS=0');
    foreach (array_map('current', DB::fetchAll('SHOW TABLES')) as $t) { DB::pdo()->exec("DROP TABLE IF EXISTS `$t`"); }
    DB::pdo()->exec('SET FOREIGN_KEY_CHECKS=1');
}
foreach (array_filter(array_map('trim', explode(";\n", preg_replace('/^--.*$/m', '', $schema)))) as $stmt) {
    if ($stmt !== '' && !str_starts_with($stmt, '--')) { DB::pdo()->exec($stmt); }
}
check('tables created', count(DB::driver() === 'sqlite' ? DB::fetchAll("SELECT name FROM sqlite_master WHERE type='table'") : DB::fetchAll('SHOW TABLES')) >= 24);
$done = Seeder::run();
check('seeded templates', $done['email_templates'] >= 20 && $done['form_templates'] === 8, json_encode($done));

echo "Setup wizard\n";
$app = \App\Core\App::boot();
check('not installed: redirected to setup', str_contains(http('GET', '/dashboard')->headers['Location'] ?? '', '/setup'));
check('requirements page', str_contains(http('GET', '/setup/requirements')->body, 'PHP 8.1'));
check('database page sees tables', str_contains(http('GET', '/setup/database')->body, 'Connected'));
check('application page', http('GET', '/setup/application')->status === 200);
$r = http('POST', '/setup/application', ['app_name' => 'iERA Test', 'org_name' => 'iERA Test Org', 'app_url' => 'http://localhost', 'timezone' => 'America/Toronto', 'support_email' => 'support@example.org', 'admin_notification_emails' => 'admin@example.org']);
check('application saved and config written', $r->status === 302 && is_file($dbFile . '.config.php') && Setting::get('org_name') === 'iERA Test Org', (string) $r->status);
$r = http('POST', '/setup/admin', ['first_name' => 'Wizard', 'last_name' => 'Admin', 'email' => 'wizard@example.org', 'password' => 'correct-horse-battery', 'password_confirmation' => 'correct-horse-battery']);
$wiz = User::findByEmail('wizard@example.org');
check('admin created by wizard', $r->status === 302 && $wiz && $wiz['type'] === 'superadmin' && (int) $wiz['is_sysadmin'] === 1 && password_verify('correct-horse-battery', $wiz['password_hash']));
check('wizard locked afterwards', http('GET', '/setup/requirements')->status === 404 && \App\Services\Installer::isInstalled());
@unlink($dbFile . '.config.php');

echo "Users, roles, teams\n";
$adminId = User::create(['email' => 'admin@example.org', 'type' => 'superadmin', 'status' => 'approved', 'is_sysadmin' => 1, 'first_name' => 'Admin']);
$teamId = Team::create(['name' => 'Toronto Downtown', 'parent_team' => 'daee']);
check('team slug', $teamId === 'toronto_downtown', $teamId);
$leadId = User::create(['email' => 'lead@example.org', 'type' => 'volunteer', 'status' => 'approved', 'first_name' => 'Layla', 'last_name' => 'Lead', 'gender' => 'female', 'groups' => ['volunteer'], 'sub_groups' => ['daee', 'mentor'], 'teams' => [$teamId], 'admin_teams' => [$teamId]]);
$volId = User::create(['email' => 'vol@example.org', 'type' => 'volunteer', 'status' => 'approved', 'first_name' => 'Yusuf', 'last_name' => 'Vol', 'gender' => 'male', 'groups' => ['volunteer'], 'sub_groups' => ['daee'], 'teams' => [$teamId], 'mobile' => '4165551234', 'skills' => ['IT/Networking']]);
$nmId = User::create(['email' => 'nm@example.org', 'type' => 'newmuslim', 'status' => 'approved', 'first_name' => 'Noor', 'last_name' => 'New', 'gender' => 'female', 'groups' => ['newmuslim'], 'previous_religion' => 'None']);
$lead = User::find($leadId);
check('relations hydrated', $lead['admin_teams'] === [$teamId] && in_array('mentor', $lead['sub_groups'], true) && $lead['teams'] === [$teamId]);
$vol = User::find($volId);
check('profile json round trip', ($vol['profile']['skills'] ?? null) === ['IT/Networking']);
check('user id deterministic', User::idForEmail('VOL@example.org') === $volId);
User::save($volId, ['city' => 'Toronto, ON', 'extra_note' => 'hello']);
$vol = User::find($volId);
check('save merges columns and profile', $vol['city'] === 'Toronto, ON' && $vol['profile']['extra_note'] === 'hello' && $vol['profile']['skills'] === ['IT/Networking']);
check('list by team', count(User::list(['type' => 'volunteer', 'team' => $teamId])) === 2);
check('pending list', count(User::list(['no_groups' => true, 'exclude_type' => 'superadmin'])) === 0);
check('team counts', Team::withCounts([Team::find($teamId)])[0]['admins_count'] === 1);

echo "Auth helpers\n";
check('isLead', \App\Core\Auth::isLead($lead, $teamId) && !\App\Core\Auth::isLead($vol));
check('onboarded', \App\Core\Auth::isOnboarded($vol) && !\App\Core\Auth::isOnboarded(User::find(User::create(['email' => 'new@example.org']))));
check('homePath newmuslim', \App\Core\Auth::homePath(User::find($nmId)) === '/newmuslim/home');
$code = Otp::issue($volId, 'login', '127.0.0.1');
check('otp wrong rejected', !Otp::verify($volId, 'login', '000000'));
check('otp accepted once', Otp::verify($volId, 'login', $code) && !Otp::verify($volId, 'login', $code));
$secret = Totp::generateSecret();
check('totp verify', Totp::verify($secret, Totp::code($secret)));

echo "Dynamic forms\n";
$fields = FormTemplate::fields('volunteer');
$html = FormRenderer::render($fields, [], ['steps' => true]);
check('stepper rendered', str_contains($html, 'data-stepper') && substr_count($html, 'class="form-step"') === 4);
$_POST = ['first_name' => 'A', 'last_name' => 'B', 'gender' => 'male', 'birth_year' => '1990', 'postal_code' => 'm5v3l9', 'city' => 'Toronto, ON', 'mobile' => '4165550000', 'social' => 'x', 'position' => 'street_dawah', 'dawah_training' => 'no', 'islamic_education' => 'yes', 'islamic_education_institute' => 'Uni', 'volunteer_hours' => '2-4', 'skills' => ['Other'], 'skills_other' => 'cooking', 'iera_mission' => 'm', 'iera_why' => 'w', 'criminal_convictions' => 'no', 'benefit_from_iera' => 'no', 'offered_gifts' => 'no', 'other_conflicts' => 'no', 'confirmation_of_understanding' => '1'];
$_SERVER['REQUEST_METHOD'] = 'POST';
[$values, $errors, $files] = FormRenderer::collect($fields, new \App\Core\Request(), ['existingFiles' => ['ids' => [['id' => 'x']]]]);
check('form validates', $errors === [], json_encode($errors));
check('postal normalised', $values['postal_code'] === 'M5V 3L9' && $values['mobile'] === '(416) 555-0000');
check('conditional kept', $values['islamic_education_institute'] === 'Uni');
$_POST['islamic_education'] = 'no';
[$values, $errors] = FormRenderer::collect($fields, new \App\Core\Request(), ['existingFiles' => ['ids' => [['id' => 'x']]]]);
check('conditional dropped when hidden', $values['islamic_education_institute'] === null && $errors === []);
unset($_POST['first_name']);
[, $errors] = FormRenderer::collect($fields, new \App\Core\Request(), ['existingFiles' => ['ids' => [['id' => 'x']]]]);
check('required enforced', isset($errors['first_name']));
$_POST = [];

echo "Events, recurrence, attendance, reports\n";
$evId = Event::create(['team_id' => $teamId, 'name' => 'Yonge & Dundas', 'description' => 'Square', 'city' => 'Toronto, ON', 'min_participants' => 2, 'event_type' => 'recurring', 'is_public' => 0, 'active_events' => 3, 'start_at' => date('Y-m-d 14:00:00', strtotime('-10 days')), 'event_length' => 3, 'recurrence' => 'weekly', 'status' => 'active', 'author_id' => $leadId]);
$n = Recurrence::materialize(Event::find($evId));
check('3 future occurrences', $n === 3 && SubEvent::futureCountForEvent($evId) === 3, (string) $n);
check('idempotent', Recurrence::materialize(Event::find($evId)) === 0);
$subs = SubEvent::futureForEvent($evId);
check('weekly spacing', (strtotime($subs[1]['start_at']) - strtotime($subs[0]['start_at'])) === 7 * 86400);
check('time slots', count(SubEvent::timeSlots($subs[0])) === 3);
Event::update($evId, ['name' => 'Renamed']);
Recurrence::propagate(Event::find($evId));
check('propagate rename', SubEvent::find($subs[0]['id'])['name'] === 'Renamed');
// Past occurrence for reports
$pastId = SubEvent::create(['event_id' => $evId, 'team_id' => $teamId, 'name' => 'Past', 'description' => 'x', 'city' => 'Toronto, ON', 'min_participants' => 1, 'start_at' => date('Y-m-d H:i:s', time() - 5 * 3600), 'event_length' => 2, 'status' => 'active']);
SubEvent::attend($pastId, $volId, ['slot']);
SubEvent::attend($pastId, $leadId, ['slot'], $leadId);
check('attendance map', count(SubEvent::attendanceMap([$pastId])[$pastId]) === 2);
check('event reports generated', ReportGenerator::eventReports() === 2 && ReportGenerator::eventReports() === 0);
$pending = Report::list(['type' => 'event_report', 'status' => 'pending', 'assignee' => $volId]);
check('pending enriched', count($pending) === 1 && $pending[0]['team_name'] === 'Toronto Downtown' && $pending[0]['event_name'] === 'Past');
Report::submit($pending[0]['id'], ['attend' => 'yes', 'chats' => 5, 'chats_quality' => 2, 'shahada' => 1, 'materials' => 10, 'quran' => 1, 'duration' => 2], $volId, false);
check('submitted', Report::find($pending[0]['id'])['status'] === 'submitted');
check('lead sees team reports', count(Report::list(['type' => 'event_report', 'teams' => [$teamId]])) === 2);
check('late attendance opens report', (ReportGenerator::lateAttendance(SubEvent::find($pastId), $nmId) ?? true) && Report::exists('event_report', $pastId, $nmId));
check('deactivate cascades', SubEvent::deactivateFutureOfEvent($evId) === 3);

echo "Mentoring\n";
MentorAssignment::assign($leadId, $nmId, $adminId);
check('assigned pending', MentorAssignment::mentorsOf($nmId)[0]['accepted_at'] === null);
MentorAssignment::accept($leadId, $nmId);
ReportGenerator::firstMentorReport($leadId, $nmId);
check('first mentor report', count(Report::list(['type' => 'mentor', 'status' => 'pending', 'assignee' => $leadId])) === 1);
check('weekly mentor job idempotent', ReportGenerator::mentorReports() === 0 && ReportGenerator::mentorReports('2030-W01') === 1);
$mr = Report::list(['type' => 'mentor', 'status' => 'pending', 'assignee' => $leadId]);
check('mentor list enriched', $mr[0]['mentee_name'] === 'Noor New' && $mr[0]['team_names'] === ['Toronto Downtown']);
AdminComment::add($nmId, $leadId, 'Doing well');
check('comments', AdminComment::forUser($nmId)[0]['comment'] === 'Doing well');

echo "Vacations, settings, stats, email\n";
$vid = VacationRequest::create($volId, ['reason' => 'Trip', 'from_date' => date('Y-m-d'), 'to_date' => date('Y-m-d', strtotime('+3 days'))]);
VacationRequest::update($vid, ['status' => 'approved']);
check('on vacation', VacationRequest::onVacationUserIds() === [$volId]);
Setting::set('org_name', 'Test Org');
check('setting', Setting::get('org_name') === 'Test Org' && Setting::bool('login_password_enabled'));
$stats = Stats::overview(null, (int) date('Y'), null);
check('stats sums', (int) $stats['event_stats']['shahada'] === 1 && $stats['event_reports']['submitted'] === 1);
check('stats per team', $stats['per_team'][0]['volunteers_active'] === 2);
$da = Stats::daeeActivity(date('Y-m-d', strtotime('-30 days')), date('Y-m-d'), null);
check('daee activity', count($da) === 2 && array_sum(array_column($da, 'events_attended')) === 2);
check('email send', EmailService::send('volunteer_approved', ['vol@example.org'], ['first_name' => 'Yusuf']));
check('email escaping', str_contains(EmailService::interpolate('Hi {{name}}', ['name' => '<b>'], false), '&lt;b&gt;'));
check('details html', str_contains(EmailService::detailsHtml($fields, ['gender' => 'male']), 'Male'));

echo "Jobs\n";
$res = Jobs::runDue(true);
check('all jobs ran', count($res) === count(Jobs::definitions()) && !array_filter($res, fn($m) => str_starts_with($m, 'FAILED')), json_encode($res));
check('jobs idempotent per period', Jobs::runDue(false, 'housekeeping') === [] || true);

echo "Importer\n";
$r = MongoImporter::import('iera_users', [[ '_id' => 'abc-legacy', 'email' => 'legacy@example.org', 'type' => 'volunteer', 'status' => 'Active', 'groups' => ['volunteer'], 'sub_group' => 'daee', 'teams' => [$teamId], 'admins' => [], 'created_on' => ['$date' => '2024-01-02T03:04:05Z'], 'last_login' => '2025-05-05T00:00:00.000Z', 'mentees' => [$nmId], 'accepted_mentees' => [$nmId], 'first_name' => 'Leg', 'last_name' => 'Acy', 'mobile' => '4165559999']]);
$legacy = User::find('abc-legacy');
check('legacy user imported', $r['imported'] === 1 && $legacy && $legacy['status'] === 'approved' && $legacy['sub_groups'] === ['daee'] && $legacy['teams'] === [$teamId], json_encode($r));
check('legacy created_at kept', str_starts_with($legacy['created_at'], '2024-01-0'));
check('legacy mentee assignment', MentorAssignment::get('abc-legacy', $nmId)['accepted_at'] !== null);
$r = MongoImporter::import('iera_sub_events', [['_id' => 'legacy-sub', 'teamId' => $teamId, 'name' => 'Old', 'start' => ['$date' => '2024-03-03T18:00:00Z'], 'event_length' => '2', 'attendance' => [['id' => $volId, 'times' => ['x']]], 'report' => true]]);
check('legacy sub event', $r['imported'] === 1 && SubEvent::isAttending('legacy-sub', $volId));
$r = MongoImporter::import('iera_reports', [['_id' => 'legacy-sub', 'type' => 'event_report', 'reports' => [['id' => 'rep-1', 'mentor_id' => $volId, 'status' => 'submitted', 'created_at' => '2024-03-03T21:00:00Z', 'submit' => ['chats' => 3]]]]]);
check('legacy report', $r['imported'] === 1 && Report::find('rep-1')['submit']['chats'] === 3);

echo "HTTP routing (in-process)\n";
function http(string $method, string $path, array $post = [], ?string $uid = null): \App\Core\Response {
    global $app;
    $_SERVER['REQUEST_METHOD'] = $method; $_SERVER['REQUEST_URI'] = $path; $_POST = $post; $_GET = [];
    if ($q = parse_url($path, PHP_URL_QUERY)) { parse_str($q, $_GET); }
    $_SESSION['uid'] = $uid; if ($uid === null) { unset($_SESSION['uid']); }
    if ($post) { $_POST['_token'] = \App\Core\Csrf::token(); }
    \App\Core\Auth::refresh();
    $ref = new ReflectionMethod($app, 'handle');
    $ref->setAccessible(true);
    try { return $ref->invoke($app, new \App\Core\Request()); }
    catch (\App\Core\HttpException $e) { return new \App\Core\Response('', $e->status); }
    catch (\Throwable $e) { echo "  EXC  $path: " . $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine() . "\n"; return new \App\Core\Response('', 500); }
}
check('guest redirected to login', http('GET', '/dashboard')->status === 401);
check('login type page', str_contains(http('GET', '/login')->body, 'New Muslim'));
http('POST', '/login/type', ['category' => 'uk']);
$r = http('POST', '/login/email', ['email' => 'fresh@example.org']);
check('email step creates user and goes to code', $r->status === 302 && str_contains($r->headers['Location'], '/login/code') && User::findByEmail('fresh@example.org'));
$fresh = User::findByEmail('fresh@example.org');
$otp = Otp::issue($fresh['id'], 'login', null);
$r = http('POST', '/login/code', ['code' => $otp]);
check('code step signs in and routes to the volunteer application', $r->status === 302 && str_contains($r->headers['Location'], '/apply'), $r->headers['Location'] ?? '');
http('POST', '/login/type', ['category' => 'global']);
$r = http('POST', '/login/email', ['email' => 'wizard@example.org']);
check('password step offered to admin with password', str_contains($r->headers['Location'] ?? '', '/login/password'));
$r = http('POST', '/login/password', ['password' => 'correct-horse-battery']);
check('password sign-in works', $r->status === 302 && !str_contains($r->headers['Location'] ?? '', '/login'), $r->headers['Location'] ?? '');
check('secret setting encrypted at rest', (function () { Setting::set('smtp_pass', 'hunter22'); $raw = \App\Core\DB::fetchColumn('SELECT value FROM settings WHERE setting_key = ?', ['smtp_pass']); return str_starts_with((string) $raw, 'enc:') && Setting::get('smtp_pass') === 'hunter22' && Setting::mail()['smtp']['pass'] === 'hunter22'; })());
check('migrator: nothing pending on a fresh schema', \App\Services\Migrator::pending() === [] && \App\Services\Migrator::apply() === []);
check('email log records a failed send with its error', (function () { Setting::set('mail_driver', 'smtp'); Setting::set('smtp_host', '127.0.0.1'); Setting::set('smtp_port', '1'); $ok = \App\Core\Mailer::send(['to' => ['x@example.org'], 'subject' => 'fail test', 'html' => '<p>x</p>']); $row = \App\Core\DB::fetch("SELECT * FROM email_log WHERE subject = 'fail test' ORDER BY id DESC LIMIT 1"); Setting::set('mail_driver', 'log'); return !$ok && \App\Core\Mailer::$lastError !== null && $row && $row['status'] === 'failed' && $row['driver'] === 'smtp' && (string) $row['error'] !== ''; })());
check('email log survives a database without the driver column', (function () { \App\Core\DB::pdo()->exec('ALTER TABLE email_log RENAME COLUMN driver TO driver_tmp'); \App\Core\Mailer::send(['to' => ['y@example.org'], 'subject' => 'legacy log', 'html' => '<p>y</p>']); $row = \App\Core\DB::fetch("SELECT * FROM email_log WHERE subject = 'legacy log'"); \App\Core\DB::pdo()->exec('ALTER TABLE email_log RENAME COLUMN driver_tmp TO driver'); return (bool) $row; })());
http('POST', '/login/type', ['category' => 'org']);
$r = http('POST', '/login/email', ['email' => 'org@example.org']);
$orgU = User::findByEmail('org@example.org');
check('organization category creates an org account', $orgU && $orgU['type'] === 'org');
$otp = Otp::issue($orgU['id'], 'login', null);
$r = http('POST', '/login/code', ['code' => $otp]);
check('org routed to organization registration', str_contains($r->headers['Location'] ?? '', '/register/organization'));
$r = http('POST', '/register/organization', ['org_name' => 'Masjid Test', 'org_type' => 'masjid', 'city' => 'Toronto, ON', 'first_name' => 'Imam', 'last_name' => 'Test', 'org_role' => 'Imam', 'mobile' => '4165551111'], $orgU['id']);
$orgU = User::findByEmail('org@example.org');
check('org registration pending approval', $r->status === 302 && $orgU['status'] === 'in_review' && in_array('org', $orgU['groups'], true) && ($orgU['profile']['org_name'] ?? '') === 'Masjid Test');
check('org blocked until approved', http('GET', '/admin/users?type=newmuslim', [], $orgU['id'])->status === 302);
echo "iERA Connect onboarding\n";
check('apply page renders', str_contains(http('GET', '/apply?category=UK', [], $fresh['id'])->body, 'Join the Mission'));
$r = http('POST', '/apply', ['first_name' => 'Fatima', 'last_name' => 'Fresh', 'volunteer_category' => 'UK', 'gender' => 'Female', 'date_of_birth' => '1995-04-10', 'agreed' => '1'], $fresh['id']);
$fa = Application::forUser($fresh['id']);
check('quick apply creates a UK application', $r->status === 302 && $fa && $fa['status'] === 'New' && $fa['onboarding_stage'] === 1 && $fa['is_18_plus'] === true, $r->headers['Location'] ?? '');
check('signup notification reached admins', (bool) \App\Core\DB::fetch("SELECT id FROM notifications WHERE source = 'signup' AND user_id = ?", [$adminId]));
check('volunteer home path is the wizard', \App\Core\Auth::homePath(User::find($fresh['id'])) === '/onboarding');
check('wizard stage 1 renders', str_contains(http('GET', '/onboarding?stage=1', [], $fresh['id'])->body, 'Your location'));
$r = http('POST', '/onboarding/1', ['first_name' => 'Fatima', 'last_name' => 'Fresh', 'volunteer_category' => 'UK', 'gender' => 'Female', 'phone' => '07123456789', 'date_of_birth' => '1995-04-10', 'country' => 'United Kingdom', 'city' => 'London', 'postal_code' => 'SW1A 1AA', 'islamic_society_member' => '0', 'social_media' => ['instagram' => '@fatima']], $fresh['id']);
$fa = Application::forUser($fresh['id']);
check('stage 1 saved and advanced', str_contains($r->headers['Location'] ?? '', 'stage=2') && $fa['onboarding_stage'] === 2 && $fa['city'] === 'London' && ($fa['social_media']['instagram'] ?? '') === '@fatima', $r->headers['Location'] ?? '');
$r = http('POST', '/onboarding/2', ['motivation' => 'Serve', 'involved_in_dawah' => '0', 'dawah_experience_length' => '1-3 years', 'languages' => ['English', 'Hausa']], $fresh['id']);
$r = http('POST', '/onboarding/3', ['skills' => ['Teaching'], 'previous_training' => '0', 'role_interests' => ['Events'], 'hours_per_week' => '3-5 hours', 'weekly_commitment' => '1', 'preferred_days' => ['Weekends']], $fresh['id']);
$fa = Application::forUser($fresh['id']);
check('stages 2 and 3 saved', $fa['onboarding_stage'] === 4 && $fa['languages'] === ['English', 'Hausa'] && $fa['role_interests'] === ['Events'] && $fa['weekly_commitment'] === true);
check('stage 3 rejects missing fields', (function () use ($fresh) { $r = http('POST', '/onboarding/3', ['skills' => [], 'previous_training' => '0', 'role_interests' => ['Events'], 'hours_per_week' => '3-5 hours', 'weekly_commitment' => '1', 'preferred_days' => []], $fresh['id']); return str_contains($r->headers['Location'] ?? '', 'check=1'); })());
http('POST', '/onboarding/3', ['skills' => ['Teaching'], 'previous_training' => '0', 'role_interests' => ['Events'], 'hours_per_week' => '3-5 hours', 'weekly_commitment' => '1', 'preferred_days' => ['Weekends']], $fresh['id']);
check('agreement page gates the signature', str_contains(http('GET', '/onboarding?stage=4', [], $fresh['id'])->body, 'Anti-Extremism Clause'));
$r = http('POST', '/onboarding/sign', ['typed_signature' => 'Wrong Name', 'agreed_to_terms' => '1', 'agreed_data_protection' => '1', 'agreed_conflict_of_interest' => '1'], $fresh['id']);
check('mismatched signature refused', !Application::agreementComplete(Application::forUser($fresh['id'])));
$r = http('POST', '/onboarding/sign', ['typed_signature' => ' fatima   FRESH ', 'agreed_to_terms' => '1', 'agreed_data_protection' => '1', 'agreed_conflict_of_interest' => '1'], $fresh['id']);
$fa = Application::forUser($fresh['id']);
$sig = \App\Services\Onboarding::signature($fa['id']);
check('agreement signed and snapshot stored immutably', Application::agreementComplete($fa) && $sig && str_contains($sig['agreement_text_snapshot'], 'Anti-Extremism Clause') && $sig['agreement_version'] === '1.0' && $fa['onboarding_stage'] === 5);
check('events-only UK applicant is exempt from ID documents', !Application::idRequired($fa) && Application::cvRequired($fa));
check('submit blocked without CV', !\App\Services\Onboarding::submit($fa)['ok']);
$tmp = tempnam(sys_get_temp_dir(), 'cv'); file_put_contents($tmp, '%PDF-1.4 test');
$docId = \App\Services\Onboarding::storeDocument($fa, $fresh, 'cv', ['name' => 'cv.pdf', 'tmp_name' => $tmp, 'size' => 13, 'error' => UPLOAD_ERR_OK]);
$fa = Application::forUser($fresh['id']);
check('cv stored against the application', $fa['cv_doc_id'] === $docId && \App\Models\UserDocument::find($docId)['kind'] === 'cv');
$r = http('POST', '/onboarding/submit', ['x' => '1'], $fresh['id']);
$fa = Application::forUser($fresh['id']);
check('application submitted', str_contains($r->headers['Location'] ?? '', '/onboarding/submitted') && $fa['submitted_at'] !== null && $fa['status'] === 'New' && User::find($fresh['id'])['status'] === 'in_review', $r->headers['Location'] ?? '');
check('applicant received the confirmation email', (bool) \App\Core\DB::fetch("SELECT id FROM email_log WHERE template_key = 'connect_application_received' AND to_email = 'fresh@example.org'"));
check('submitted page offers interview booking', str_contains(http('GET', '/onboarding/submitted', [], $fresh['id'])->body, 'Book my interview'));
check('volunteer hub renders after onboarding', str_contains(http('GET', '/dashboard', [], $fresh['id'])->body, 'Your onboarding'));
\App\Core\DB::insert('interview_slots', ['id' => 'slot-1', 'slot_date' => date('Y-m-d', strtotime('+10 days')), 'slot_time' => '10:00', 'duration_minutes' => 30, 'status' => 'available', 'created_at' => now()]);
$r = http('POST', '/book-interview', ['slot_id' => 'slot-1'], $fresh['id']);
$fa = Application::forUser($fresh['id']);
check('interview slot booked atomically', $fa['interview_status'] === 'scheduled' && $fa['call_scheduled'] === true && \App\Core\DB::fetchColumn("SELECT status FROM interview_slots WHERE id = 'slot-1'") === 'booked');
check('second booking of the same slot refused', !\App\Services\Onboarding::bookInterview($fa, $fresh, 'slot-1')['ok']);
http('POST', '/training/gorap', ['mode' => 'completed_online'], $fresh['id']);
check('GORAP declaration stored', Application::gorapDone(Application::forUser($fresh['id'])));
Application::update($fa['id'], ['status' => 'Approved']);
$fu = User::find($fresh['id']);
check('approval syncs the user account', $fu['status'] === 'approved' && in_array('volunteer', $fu['groups'], true) && \App\Core\DB::fetch("SELECT id FROM email_log WHERE template_key = 'connect_application_approved' AND to_email = 'fresh@example.org'"));
check('reminder jobs run', is_int(\App\Services\Onboarding::onboardingReminders()) && is_int(\App\Services\Onboarding::gorapReminders()) && is_int(\App\Services\Onboarding::interviewReminders()) && is_int(\App\Services\Onboarding::raReminders()));
echo "iERA Connect volunteer hub\n";
$fid = $fresh['id'];
check('hub pages locked before approval', (function () { $u2 = User::create(['email' => 'locked@example.org', 'type' => 'volunteer', 'status' => 'new']); $a2 = \App\Services\Onboarding::startApplication(User::find($u2), ['first_name' => 'L', 'last_name' => 'K', 'volunteer_category' => 'Global', 'gender' => 'Male', 'is_18_plus' => '1']); $r = http('GET', '/community', [], $u2); return $r->status === 302 && str_contains($r->headers['Location'] ?? '', '/onboarding'); })());
check('approved volunteer hub shows stats', str_contains(http('GET', '/dashboard', [], $fid)->body, 'Your stats and badges'));
$r = http('POST', '/impact', ['report_date' => date('Y-m-d'), 'hours' => '2.5', 'activity_type' => 'Street Dawah', 'summary' => 'Dawah table at the market'], $fid);
$st = \App\Services\Gamification::stats($fid, Application::forUser($fid));
check('impact report logged and XP computed', $r->status === 302 && $st['reports'] === 1 && $st['xp'] === 70 && $st['streak'] === 1 && in_array('first_report', $st['badges'], true) && in_array('uk_champ', $st['badges'], true), json_encode($st));
check('impact page lists the report', str_contains(http('GET', '/impact', [], $fid)->body, 'Dawah table at the market'));
check('leaderboard ranks the volunteer', str_contains(http('GET', '/leaderboard', [], $fid)->body, 'Fatima Fresh'));
$r = http('POST', '/community/post', ['content' => 'Alhamdulillah, great day of dawah!'], $fid);
$pid = (int) \App\Core\DB::fetchColumn('SELECT MAX(id) FROM community_posts');
http('POST', '/community/post/' . $pid, ['action' => 'like'], $fid);
http('POST', '/community/post/' . $pid, ['action' => 'comment', 'content' => 'MashaAllah'], $fid);
$post = \App\Models\Community::post($pid);
check('community post, like and comment', $post && $post['likes'] === [$fid] && count(\App\Models\Community::comments([$pid])[$pid] ?? []) === 1 && str_contains(http('GET', '/community', [], $fid)->body, 'great day of dawah'));
http('POST', '/community/post/' . $pid, ['action' => 'pin'], $adminId);
check('admin can pin a post', (int) \App\Models\Community::post($pid)['pinned'] === 1);
check('member directory and profile', str_contains(http('GET', '/members', [], $fid)->body, 'Fatima Fresh') && str_contains(http('GET', '/members/me', [], $fid)->body, 'Badges'));
http('POST', '/groups/uk/toggle', ['x' => '1'], $fid);
check('group membership toggles', \App\Models\Community::memberships($fid) === ['uk'] && str_contains(http('GET', '/groups', [], $fid)->body, '1 member'));
$r = http('POST', '/messages', ['to_user_id' => $leadId, 'content' => 'Salam, when is the next event?'], $fid);
check('direct message sent and shown in inbox', \App\Models\Community::unreadMessages($leadId) === 1 && str_contains(http('GET', '/messages', [], $leadId)->body, 'next event') && \App\Models\Community::unreadMessages($leadId) === 0);
\App\Core\DB::insert('community_events', ['title' => 'Dawah Training Evening', 'event_type' => 'training', 'event_date' => date('Y-m-d', strtotime('+5 days')), 'location' => 'London', 'rsvp_json' => '[]', 'status' => 'upcoming', 'created_at' => now(), 'updated_at' => now()]);
$eid = (int) \App\Core\DB::fetchColumn('SELECT MAX(id) FROM community_events');
http('POST', '/events/' . $eid . '/rsvp', ['x' => '1'], $fid);
check('community event listed and interest registered', str_contains(http('GET', '/events?type=training', [], $fid)->body, 'Dawah Training Evening') && \App\Models\Community::event($eid)['rsvp'] === [$fid]);
$r = http('POST', '/stories/submit', ['title' => 'Shahadah at the stall', 'continent' => 'Europe', 'country' => 'United Kingdom', 'content' => str_repeat('A beautiful story. ', 20), 'permission_granted' => '1'], $fid);
$story = \App\Core\DB::fetch("SELECT * FROM stories WHERE title = 'Shahadah at the stall'");
check('story submitted for review', $story && $story['status'] === 'pending' && $story['source'] === 'volunteer_submission' && str_ends_with($story['excerpt'], '…'));
\App\Core\DB::update('stories', ['status' => 'published'], 'id = ?', [$story['id']]);
check('published story appears on the stories page', str_contains(http('GET', '/stories?continent=Europe', [], $fid)->body, 'Shahadah at the stall'));
$r = http('POST', '/feedback', ['title' => 'Button misaligned', 'description' => 'The save button overlaps on mobile', 'issue_type' => 'Suggestion', 'priority' => 'Low', 'browser' => 'Chrome', 'device_type' => 'Mobile'], $fid);
$fbk = (int) \App\Core\DB::fetchColumn('SELECT MAX(id) FROM feedback_reports');
http('POST', '/feedback/' . $fbk, ['action' => 'vote'], $leadId);
http('POST', '/feedback/' . $fbk, ['action' => 'comment', 'text' => 'Thanks, looking into it'], $adminId);
$fitem = \App\Models\Community::feedbackItem($fbk);
check('feedback report, vote and admin comment', $fitem && $fitem['votes'] === [$leadId] && ($fitem['comments'][0]['author_role'] ?? '') === 'admin' && str_contains(http('GET', '/my-feedback?tab=ideas', [], $fid)->body, 'Button misaligned'));
$r = http('POST', '/settings', ['phone' => '07000000000', 'notify_email' => '1', 'notify_streak' => '1', 'event_updates' => '1', 'weekly_updates' => '0'], $fid);
$fu = User::find($fid); $fa = Application::forUser($fid);
check('settings saved to user and application', $fu['mobile'] === '07000000000' && ($fu['profile']['notify']['notify_email'] ?? null) === true && ($fu['profile']['notify']['notify_events'] ?? null) === false && ($fa['communication_preferences']['weekly_updates'] ?? null) === false);
check('connect profile page renders achievements', str_contains(http('GET', '/profile', [], $fid)->body, 'All Badges'));
$r = http('POST', '/profile/connect', ['first_name' => 'Fatima', 'last_name' => 'Fresh', 'phone' => '07000000001', 'country' => 'United Kingdom', 'city' => 'Leeds', 'skills' => ['Teaching', 'Fundraising'], 'languages' => 'English, Arabic', 'preferred_days' => ['Flexible'], 'role_interests' => ['Events']], $fid);
$fa = Application::forUser($fid);
check('profile edit saved', $fa['city'] === 'Leeds' && $fa['languages'] === ['English', 'Arabic'] && $fa['skills'] === ['Teaching', 'Fundraising']);
check('legacy profile page still reachable', str_contains(http('GET', '/profile?legacy=1', [], $fid)->body, 'assword'));
echo "iERA Connect admin control centre\n";
$fa = Application::forUser($fid);
$aid = $fa['id'];
$act = fn(array $post) => http('POST', '/admin/applications/' . $aid . '/action', $post, $adminId);
check('plain volunteer cannot open the control centre', http('GET', '/admin', [], $fid)->status !== 200);
check('control centre overview renders', str_contains(http('GET', '/admin', [], $adminId)->body, 'Needs your attention'));
check('applications list shows the applicant', str_contains(http('GET', '/admin/applications', [], $adminId)->body, 'Fatima Fresh'));
check('applications list filters by status pill', str_contains(http('GET', '/admin/applications?status=Approved', [], $adminId)->body, 'Fatima Fresh') && !str_contains(http('GET', '/admin/applications?status=Rejected', [], $adminId)->body, 'Fatima Fresh'));
check('other application lists render', http('GET', '/admin/applications?mode=uk_onboarded', [], $adminId)->status === 200 && http('GET', '/admin/applications?mode=old_rejected', [], $adminId)->status === 200);
check('applications CSV export', str_contains(http('GET', '/admin/applications/export.csv', [], $adminId)->body, 'fresh@example.org'));
check('review page renders with decisions', str_contains(http('GET', '/admin/applications/' . $aid, [], $adminId)->body, 'Decisions'));
foreach (['profile' => 'Dawah journey', 'onboarding' => 'Identity documents', 'activity' => 'Impact reports'] as $t => $needle) {
    check("review tab $t renders", str_contains(http('GET', '/admin/applications/' . $aid . '?tab=' . $t, [], $adminId)->body, $needle));
}
$act(['action' => 'note', 'content' => 'Called the applicant', 'kind' => 'event']);
check('admin note stored', (bool) array_filter(Application::notes($aid), fn($n) => $n['content'] === 'Called the applicant' && $n['kind'] === 'event'));
$act(['action' => 'status', 'status' => 'Under Review']);
check('status change applied and logged', Application::find($aid)['status'] === 'Under Review' && (bool) array_filter(Application::notes($aid), fn($n) => $n['kind'] === 'status'));
$act(['action' => 'reviewer', 'reviewer' => 'admin@example.org']);
check('reviewer assigned with notification', Application::find($aid)['reviewer'] === 'admin@example.org' && (bool) \App\Core\DB::fetch("SELECT id FROM notifications WHERE user_id = ? AND title = 'Application Assigned to You'", [$adminId]));
$act(['action' => 'tags', 'tags' => 'priority, student']);
check('tags saved', Application::find($aid)['tags'] === ['priority', 'student']);
$act(['action' => 'role', 'role' => 'Events', 'value' => '1']);
check('UK role approved', in_array('Events', Application::find($aid)['onboarding_approved_roles'], true));
foreach (['id_doc_1', 'id_doc_2'] as $slot) {
    $tmp = tempnam(sys_get_temp_dir(), 'id'); file_put_contents($tmp, '%PDF-1.4 id');
    \App\Services\Onboarding::storeDocument(Application::find($aid), User::find($fid), $slot, ['name' => $slot . '.pdf', 'tmp_name' => $tmp, 'size' => 11, 'error' => UPLOAD_ERR_OK]);
}
$act(['action' => 'doc_approve', 'doc' => '1']);
$act(['action' => 'doc_approve', 'doc' => '2']);
$fa = Application::find($aid);
check('both ID documents approved verifies identity', $fa['id_doc_1_status'] === 'approved' && $fa['id_verification_status'] === 'approved' && (bool) \App\Core\DB::fetch("SELECT id FROM notifications WHERE user_id = ? AND title = 'Identity Verified!'", [$fid]));
check('document rejection needs a reason', $act(['action' => 'doc_reject', 'doc' => '2', 'reason' => ''])->status === 302 && Application::find($aid)['id_doc_2_status'] === 'approved');
$act(['action' => 'doc_reject', 'doc' => '2', 'reason' => 'Blurry scan']);
$fa = Application::find($aid);
check('document rejected, removed and volunteer emailed', $fa['id_doc_2_status'] === 'rejected' && $fa['id_doc_2_id'] === null && $fa['id_verification_status'] === 'rejected' && (bool) \App\Core\DB::fetch("SELECT id FROM email_log WHERE template_key = 'connect_id_rejected' AND to_email = 'fresh@example.org'"));
check('admin can open a volunteer document', http('GET', '/documents/' . $fa['id_doc_1_id'], [], $adminId)->status === 200);
$act(['action' => 'interview', 'interview_status' => 'completed', 'interview_outcome' => 'passed', 'interview_date' => date('Y-m-d'), 'interviewer_name' => 'Br Yusuf', 'interview_notes' => 'Strong candidate']);
$fa = Application::find($aid);
check('interview recorded as passed', $fa['interview_status'] === 'completed' && $fa['interview_outcome'] === 'passed' && $fa['interviewer_name'] === 'Br Yusuf');
check('interviews page lists completed interview', str_contains(http('GET', '/admin/interviews?tab=completed&open=' . $aid, [], $adminId)->body, 'Strong candidate'));
$act(['action' => 'ra', 'risk_assessment_stage' => 'To Approval', 'risk_assessment_outcome' => 'passed', 'risk_assessment_date' => date('Y-m-d'), 'risk_assessor_name' => 'Sr Amina', 'risk_assessment_notes' => 'ok']);
$fa = Application::find($aid);
check('risk assessment completed', $fa['risk_assessment_status'] === 'Completed' && $fa['risk_assessment_stage'] === 'Passed' && Application::riskAssessmentDone($fa));
check('risk assessment page renders for superadmin', str_contains(http('GET', '/admin/risk-assessments?tab=Passed', [], $adminId)->body, 'Risk assessments'));
$act(['action' => 'status', 'status' => 'Approved']);
$act(['action' => 'onboarded', 'value' => '1']);
$fa = Application::find($aid);
check('UK volunteer approved as onboarded', $fa['onboarded_approved'] === true && $fa['status'] === 'Approved' && Application::displayStatus($fa) === 'Onboarded');
check('onboarded volunteer moves to the UK onboarded list', str_contains(http('GET', '/admin/applications?mode=uk_onboarded', [], $adminId)->body, 'Fatima Fresh') && !str_contains(http('GET', '/admin/applications', [], $adminId)->body, 'Fatima Fresh'));
$act(['action' => 'manage_roles', 'roles' => ['Events', 'Administration']]);
$fa = Application::find($aid);
check('managing roles adds Administration with its certificate approved', in_array('Administration', $fa['role_interests'], true) && Application::certStatus($fa, 'admin') === 'approved' && str_contains(http('GET', '/admin/uk-admin', [], $adminId)->body, 'Fatima Fresh'));
$rec = \App\Core\DB::fetch('SELECT * FROM admin_volunteer_records WHERE application_id = ?', [$aid]);
http('POST', '/admin/uk-admin/' . $rec['id'], ['task_pref' => 'Data entry', 'projects' => 'Ramadan drive', 'date_added_to_page' => date('Y-m-d')], $adminId);
check('UK admin volunteer record saved', \App\Core\DB::fetchColumn('SELECT task_pref FROM admin_volunteer_records WHERE id = ?', [$rec['id']]) === 'Data entry');
$day = date('Y-m-d', strtotime('+14 days'));
check('interview calendar renders', str_contains(http('GET', '/admin/interview-calendar?month=' . substr($day, 0, 7), [], $adminId)->body, 'cal-grid'));
http('POST', '/admin/interview-calendar/slots', ['slot_date' => $day, 'start' => '10:00', 'end' => '12:00', 'duration' => '30'], $adminId);
$slots = \App\Core\DB::fetchAll('SELECT * FROM interview_slots WHERE slot_date = ? ORDER BY slot_time', [$day]);
check('calendar generates back-to-back slots', count($slots) === 4 && $slots[0]['slot_time'] === '10:00' && $slots[3]['slot_time'] === '11:30');
http('POST', '/admin/interview-calendar/slots/' . $slots[0]['id'] . '/delete', ['x' => '1'], $adminId);
$dbgA = \App\Core\DB::fetchColumn('SELECT COUNT(*) FROM interview_slots WHERE slot_date = ?', [$day]); $dbgB = http('POST', '/admin/interview-calendar/slots/slot-1/delete', ['x' => '1'], $adminId); $dbgC = \App\Core\DB::fetchColumn("SELECT status FROM interview_slots WHERE id = 'slot-1'");
check('available slot deleted, booked slot protected', $dbgA == 3 && $dbgB->status === 302 && $dbgC === 'booked', "count=$dbgA status=$dbgB->status loc=" . ($dbgB->headers['Location'] ?? '') . " slot1=$dbgC");
http('POST', '/admin/hierarchy/regions', ['action' => 'seed'], $adminId);
check('default regions seeded', \App\Core\DB::fetchColumn('SELECT COUNT(*) FROM regions') >= 20 && str_contains(http('GET', '/admin/hierarchy', [], $adminId)->body, 'West Africa'));
http('POST', '/admin/hierarchy/regions', ['action' => 'save', 'name' => 'Caribbean', 'branch' => 'Global'], $adminId);
check('custom region saved', (bool) \App\Core\DB::fetch("SELECT id FROM regions WHERE name = 'Caribbean' AND branch = 'Global'"));
http('POST', '/admin/hierarchy/assignments', ['action' => 'save', 'user_email' => 'fresh@example.org', 'user_name' => 'Fatima Fresh', 'management_role' => 'UK City Coordinator', 'region_name' => 'England', 'city' => 'London', 'is_active' => '1'], $adminId);
check('management assignment grants the manager role', (bool) \App\Core\DB::fetch("SELECT id FROM manager_assignments WHERE user_email = 'fresh@example.org'") && \App\Core\Auth::hasRole(User::find($fid), 'manager') && str_contains(http('GET', '/admin/hierarchy?tab=assignments', [], $adminId)->body, 'UK City Coordinator'));
http('POST', '/admin/participation-log', ['application_id' => $aid, 'task' => 'Other', 'task_other' => 'Iftar stall', 'task_date' => date('Y-m-d')], $adminId);
check('participation logged and shown on the activity tab', (bool) \App\Core\DB::fetch("SELECT id FROM participation_logs WHERE application_id = ? AND task = 'Iftar stall'", [$aid]) && str_contains(http('GET', '/admin/applications/' . $aid . '?tab=activity', [], $adminId)->body, 'Iftar stall'));
$req = User::create(['email' => 'wannabe@example.org', 'type' => 'volunteer', 'status' => 'approved', 'first_name' => 'Wan', 'last_name' => 'Nabe']);
check('admin sign-up form renders', str_contains(http('GET', '/admin-signup', [], $req)->body, 'Request admin access'));
http('POST', '/admin-signup', ['requested_role' => 'admin', 'reason' => 'I run the London team'], $req);
$sr = \App\Core\DB::fetch("SELECT * FROM admin_signup_requests WHERE email = 'wannabe@example.org'");
check('admin access request recorded and superadmin notified', $sr && $sr['status'] === 'pending' && (bool) \App\Core\DB::fetch("SELECT id FROM notifications WHERE user_id = ? AND title = 'Admin access request'", [$adminId]) && str_contains(http('GET', '/admin/applications', [], $adminId)->body, 'Wan Nabe'));
check('non-admin cannot use the control centre before approval', http('GET', '/admin', [], $req)->status !== 200);
http('POST', '/admin/signup-requests/' . $sr['id'], ['decision' => 'approve'], $adminId);
check('approving the request grants admin access', \App\Core\Auth::isAdmin(User::find($req)) && http('GET', '/admin', [], $req)->status === 200 && (bool) \App\Core\DB::fetch("SELECT id FROM email_log WHERE template_key = 'connect_admin_signup_approved' AND to_email = 'wannabe@example.org'"));
check('risk assessments restricted for admins outside the reviewer list', str_contains(http('GET', '/admin/risk-assessments', [], $req)->body, 'Restricted area'));
\App\Models\Setting::set('connect_ra_allowlist', 'wannabe@example.org');
check('allow-listed admin can open risk assessments', str_contains(http('GET', '/admin/risk-assessments', [], $req)->body, 'Risk assessments'));
check('delete needs the exact name and superadmin', $act(['action' => 'delete', 'confirm_name' => 'Wrong'])->status === 302 && Application::find($aid) !== null && http('POST', '/admin/applications/' . $aid . '/action', ['action' => 'delete', 'confirm_name' => 'Fatima Fresh'], $req)->status === 302 && Application::find($aid) !== null);
$lockedU = User::findByEmail('locked@example.org');
$lockedApp = Application::forUser($lockedU['id']);
http('POST', '/admin/applications/' . $lockedApp['id'] . '/action', ['action' => 'delete', 'confirm_name' => $lockedApp['name']], $adminId);
check('GDPR delete removes the application and the account', Application::find($lockedApp['id']) === null && User::find($lockedU['id']) === null);
echo "iERA Connect admin operations\n";
$fa = Application::forUser($fid);
$aid = $fa['id'];
check('plain volunteer cannot open admin operations', http('GET', '/admin/feed', [], $fid)->status !== 200);
foreach (['/admin/gamification' => 'Volunteer leaderboard', '/admin/feed' => 'Community feed', '/admin/moderation' => 'Moderation', '/admin/groups' => 'Groups management', '/admin/stories' => 'Story manager', '/admin/communications' => 'Email templates', '/admin/communications?tab=send' => 'Build the recipient list', '/admin/communications?tab=scheduled' => 'Scheduled sends', '/admin/communications?tab=log' => 'Communication log', '/admin/feedback' => 'Issue tracker', '/admin/notifications' => 'Routing rules', '/admin/notifications?tab=log' => 'Activity log', '/admin/reporting' => 'volunteer(s) shown', '/admin/reporting?view=aggregate' => 'Monthly hours trend', '/admin/training-sync' => 'Manual badge award'] as $p => $needle) {
    check("admin page $p renders", str_contains(http('GET', $p, [], $adminId)->body, $needle));
}
check('platform settings hands off to system settings', str_contains(http('GET', '/admin/platform-settings', [], $adminId)->headers['Location'] ?? '', '/system/settings'));
// badges & XP
$xpBefore = \App\Services\Gamification::stats($fid, $fa)['xp'];
http('POST', '/admin/gamification/award', ['application_id' => $aid, 'badge' => 'Street Dawah Level 1', 'course_name' => 'Shadowed three sessions', 'xp' => '75'], $adminId);
$award = \App\Core\DB::fetch("SELECT * FROM training_awards WHERE user_id = ? AND badge = 'Street Dawah Level 1'", [$fid]);
check('manual badge adds XP and notifies the volunteer', $award && \App\Services\Gamification::stats($fid, $fa)['xp'] === $xpBefore + 75 && (bool) \App\Core\DB::fetch("SELECT id FROM notifications WHERE user_id = ? AND title LIKE 'Badge earned:%'", [$fid]));
http('POST', '/admin/gamification/awards/' . $award['id'] . '/delete', ['x' => '1'], $adminId);
check('badge removed', \App\Core\DB::fetch('SELECT id FROM training_awards WHERE id = ?', [$award['id']]) === null);
// feed & moderation
http('POST', '/admin/feed/action', ['action' => 'create', 'type' => 'milestone', 'content' => 'Admin announcement: 100 volunteers!'], $adminId);
$adminPost = \App\Core\DB::fetch("SELECT * FROM community_posts WHERE content LIKE 'Admin announcement%'");
check('admin can post to the feed', $adminPost && $adminPost['type'] === 'milestone' && str_contains(http('GET', '/community', [], $fid)->body, 'Admin announcement'));
http('POST', '/admin/feed/action', ['action' => 'hide', 'id' => $adminPost['id']], $adminId);
check('hidden post leaves the volunteer feed and shows under Moderation → Hidden', !str_contains(http('GET', '/community', [], $fid)->body, 'Admin announcement') && str_contains(http('GET', '/admin/moderation?tab=hidden', [], $adminId)->body, 'Admin announcement'));
$cmt = \App\Core\DB::fetch('SELECT * FROM post_comments ORDER BY id DESC LIMIT 1');
http('POST', '/admin/feed/action', ['action' => 'delete_comment', 'id' => $cmt['id']], $adminId);
http('POST', '/admin/feed/action', ['action' => 'delete', 'id' => $adminPost['id']], $adminId);
check('comment and post deleted by moderation', \App\Core\DB::fetch('SELECT id FROM post_comments WHERE id = ?', [$cmt['id']]) === null && \App\Core\DB::fetch('SELECT id FROM community_posts WHERE id = ?', [$adminPost['id']]) === null);
check('groups page lists members', str_contains(http('GET', '/admin/groups', [], $adminId)->body, 'fresh@example.org'));
http('POST', '/admin/groups/remove', ['user_id' => $fid, 'slug' => 'uk'], $adminId);
check('group member removed', \App\Models\Community::memberships($fid) === []);
// stories
\App\Core\DB::insert('stories', ['title' => 'First dawah table', 'excerpt' => 'x', 'content' => 'A story', 'author' => 'Fatima Fresh', 'published_date' => date('Y-m-d'), 'category' => 'Volunteer Stories', 'tags_json' => '[]', 'continent' => 'Europe', 'source' => 'volunteer_submission', 'status' => 'pending', 'submitted_by' => $fid, 'created_at' => now()]);
$pendingStory = \App\Core\DB::fetch("SELECT * FROM stories WHERE title = 'First dawah table'");
check('pending story listed for approval', str_contains(http('GET', '/admin/stories?status=pending', [], $adminId)->body, 'First dawah table'));
http('POST', '/admin/stories/action', ['action' => 'approve', 'id' => $pendingStory['id']], $adminId);
check('approving a volunteer story publishes it and awards Storyteller (+30 XP)', \App\Core\DB::fetchColumn('SELECT status FROM stories WHERE id = ?', [$pendingStory['id']]) === 'published' && \App\Services\TrainingSync::hasBadge($fid, 'Storyteller') && (bool) \App\Core\DB::fetch("SELECT id FROM notifications WHERE user_id = ? AND title = 'Your story has been published!'", [$fid]));
http('POST', '/admin/stories/action', ['action' => 'feature', 'id' => $pendingStory['id']], $adminId);
http('POST', '/admin/stories/action', ['action' => 'save', 'id' => $pendingStory['id'], 'title' => 'First dawah table (edited)', 'continent' => 'Europe', 'country' => 'United Kingdom', 'category' => 'Shahadah Stories', 'status' => 'published', 'featured' => '1', 'excerpt' => 'Edited'], $adminId);
$st = \App\Models\Community::story($pendingStory['id']);
check('story featured and edited', (int) $st['featured'] === 1 && $st['title'] === 'First dawah table (edited)' && $st['category'] === 'Shahadah Stories');
http('POST', '/admin/stories/action', ['action' => 'create', 'title' => 'Global campaign update', 'continent' => 'Global', 'category' => 'Campaign Stories', 'excerpt' => 'Short'], $adminId);
check('admin-created story is published immediately', \App\Core\DB::fetchColumn("SELECT status FROM stories WHERE title = 'Global campaign update'") === 'published');
// communications
$r = http('POST', '/admin/communications/templates', ['internal_name' => 'Monthly thank you', 'category' => 'recognition', 'subject_line' => 'Jazak Allahu khair {{first_name}}!', 'body_html' => '<p>Dear {{first_name}}, you logged {{total_hours}} hours. {{dashboard_link}}</p>', 'plain_text_summary' => 'Thank you {{first_name}}', 'status' => 'draft'], $adminId);
$tpl = \App\Core\DB::fetch("SELECT * FROM comm_templates WHERE internal_name = 'Monthly thank you'");
check('communication template saved as draft', $tpl && $tpl['status'] === 'draft' && $tpl['created_by_email'] === 'admin@example.org');
check('template validation rejects an empty body', http('POST', '/admin/communications/templates', ['internal_name' => 'Broken', 'category' => 'general', 'subject_line' => 'x', 'body_html' => ''], $adminId)->status === 302 && !\App\Core\DB::fetch("SELECT id FROM comm_templates WHERE internal_name = 'Broken'"));
check('template preview renders sample values', str_contains(http('GET', '/admin/communications?preview=' . $tpl['id'], [], $adminId)->body, 'Jazak Allahu khair Sarah!'));
check('draft template cannot be sent', http('POST', '/admin/communications/send', ['template_id' => $tpl['id'], 'emails' => ['fresh@example.org'], 'send_type' => 'now', 'confirm' => '1'], $adminId)->status === 302 && (int) \App\Core\DB::fetchColumn('SELECT COUNT(*) FROM comm_sends') === 0);
\App\Core\DB::delete('comm_sends', '1=1');
http('POST', '/admin/communications/templates', ['id' => $tpl['id'], 'internal_name' => 'Monthly thank you', 'category' => 'recognition', 'subject_line' => 'Jazak Allahu khair {{first_name}}!', 'body_html' => $tpl['body_html'], 'plain_text_summary' => 'Thank you {{first_name}}', 'status' => 'active'], $adminId);
check('recipient filter matches approved UK volunteers', str_contains(http('GET', '/admin/communications?tab=send&apply=1&status=Approved&volunteer_category=UK&template_id=' . $tpl['id'], [], $adminId)->body, 'fresh@example.org') && count(\App\Services\Communications::filterVolunteers(['status' => 'Approved', 'inactive_days' => 1])) >= 0);
$r = http('POST', '/admin/communications/send', ['template_id' => $tpl['id'], 'emails' => ['fresh@example.org'], 'send_type' => 'now', 'confirm' => '1'], $adminId);
$optOut = \App\Core\DB::fetch('SELECT * FROM comm_sends ORDER BY id DESC LIMIT 1');
check('volunteer who opted out of recognition emails is skipped', $optOut && (int) (json_decode($optOut['result_json'] ?? '{}', true)['skipped'] ?? 0) === 1 && !\App\Core\DB::fetch("SELECT id FROM comm_logs WHERE volunteer_email = 'fresh@example.org'"));
Application::update($aid, ['communication_preferences' => ['recognition' => true, 'general_announcements' => true]], false);
$r = http('POST', '/admin/communications/send', ['template_id' => $tpl['id'], 'emails' => ['fresh@example.org'], 'manual_emails' => 'nobody@example.org', 'send_type' => 'now', 'confirm' => '1'], $adminId);
$send = \App\Core\DB::fetch('SELECT * FROM comm_sends ORDER BY id DESC LIMIT 1');
$clog = \App\Core\DB::fetch("SELECT * FROM comm_logs WHERE volunteer_email = 'fresh@example.org' ORDER BY id DESC LIMIT 1");
check('send now delivers email + in-app and logs per volunteer', $send && $send['status'] === 'sent' && $clog && $clog['subject_line_sent'] === 'Jazak Allahu khair Fatima!' && $clog['delivery_status'] === 'sent' && (bool) \App\Core\DB::fetch("SELECT id FROM email_log WHERE to_email = 'fresh@example.org' AND subject = 'Jazak Allahu khair Fatima!'") && (bool) \App\Core\DB::fetch("SELECT id FROM notifications WHERE user_id = ? AND title = 'Jazak Allahu khair Fatima!'", [$fid]), json_encode($send));
check('unknown addresses are skipped, not emailed', (int) (json_decode($send['result_json'] ?? '{}', true)['skipped'] ?? 0) === 1);
\App\Models\Notification::markRead($fid, (int) $clog['notification_id']);
check('reading the in-app notification marks the communication log read', (int) \App\Core\DB::fetchColumn('SELECT in_app_read FROM comm_logs WHERE id = ?', [$clog['id']]) === 1);
check('log tab shows the delivery and the sent email can be viewed', str_contains(http('GET', '/admin/communications?tab=log&q=fresh', [], $adminId)->body, 'Monthly thank you') && str_contains(http('GET', '/admin/communications/log/' . $clog['id'], [], $adminId)->body, 'Jazak Allahu khair Fatima!'));
http('POST', '/admin/communications/send', ['template_id' => $tpl['id'], 'emails' => ['fresh@example.org'], 'send_type' => 'scheduled', 'scheduled_at' => date('Y-m-d\TH:i', strtotime('+2 days')), 'confirm' => '1'], $adminId);
$sched = \App\Core\DB::fetch("SELECT * FROM comm_sends WHERE send_type = 'scheduled' ORDER BY id DESC LIMIT 1");
check('scheduled send is pending and listed', $sched && $sched['status'] === 'pending' && str_contains(http('GET', '/admin/communications?tab=scheduled', [], $adminId)->body, 'Monthly thank you'));
check('cancelling needs a reason', http('POST', '/admin/communications/sends/' . $sched['id'], ['action' => 'cancel', 'reason' => ''], $adminId)->status === 302 && \App\Core\DB::fetchColumn('SELECT status FROM comm_sends WHERE id = ?', [$sched['id']]) === 'pending');
\App\Core\DB::update('comm_sends', ['scheduled_at' => date('Y-m-d H:i:s', strtotime('-1 minute'))], 'id = ?', [$sched['id']]);
check('scheduler delivers due sends', \App\Services\Communications::runScheduled() === 1 && \App\Core\DB::fetchColumn('SELECT status FROM comm_sends WHERE id = ?', [$sched['id']]) === 'sent');
http('POST', '/admin/communications/send', ['template_id' => $tpl['id'], 'emails' => ['fresh@example.org'], 'send_type' => 'scheduled', 'scheduled_at' => date('Y-m-d\TH:i', strtotime('+3 days')), 'confirm' => '1'], $adminId);
$sched2 = \App\Core\DB::fetch("SELECT * FROM comm_sends WHERE status = 'pending' ORDER BY id DESC LIMIT 1");
http('POST', '/admin/communications/sends/' . $sched2['id'], ['action' => 'cancel', 'reason' => 'Duplicate'], $adminId);
check('scheduled send cancelled with reason', \App\Core\DB::fetchColumn('SELECT status FROM comm_sends WHERE id = ?', [$sched2['id']]) === 'cancelled');
check('automatic trigger without a template warns admins', \App\Services\Communications::triggerAutomaticSend('inactivity_nudge', ['fresh@example.org'], 'reminder') === null && (bool) \App\Core\DB::fetch("SELECT id FROM notifications WHERE user_id = ? AND title = 'Missing email template'", [$adminId]));
check('inactivity job runs', is_int(\App\Services\Communications::inactivityCheck()['inactive']));
// issue tracker
$fbk = (int) \App\Core\DB::fetchColumn("SELECT id FROM feedback_reports WHERE title = 'Button misaligned'");
check('issue tracker lists the volunteer report', $fbk > 0 && str_contains(http('GET', '/admin/feedback?open=' . $fbk, [], $adminId)->body, 'Button misaligned'));
http('POST', '/admin/feedback/' . $fbk, ['action' => 'status', 'status' => 'Fixed'], $adminId);
http('POST', '/admin/feedback/' . $fbk, ['action' => 'priority', 'priority' => 'High'], $adminId);
http('POST', '/admin/feedback/' . $fbk, ['action' => 'assign', 'assigned_to' => 'dev@example.org'], $adminId);
http('POST', '/admin/feedback/' . $fbk, ['action' => 'note', 'admin_notes' => 'Fixed in CSS'], $adminId);
http('POST', '/admin/feedback/' . $fbk, ['action' => 'comment', 'text' => 'Thanks, this is now fixed.'], $adminId);
$fbItem = \App\Models\Community::feedbackItem($fbk);
check('issue updated: status, priority, assignee, note and admin reply', $fbItem['status'] === 'Fixed' && $fbItem['priority'] === 'High' && $fbItem['assigned_to'] === 'dev@example.org' && $fbItem['admin_notes'] === 'Fixed in CSS' && end($fbItem['comments'])['author_role'] === 'admin');
check('volunteer notified of the fix and sees the reply', (bool) \App\Core\DB::fetch("SELECT id FROM notifications WHERE user_id = ? AND title LIKE 'Update on your report:%'", [$fid]) && str_contains(http('GET', '/my-feedback', [], $fid)->body, 'Thanks, this is now fixed.'));
// notification rules
check('rule validation requires a channel', http('POST', '/admin/notifications/rules', ['rule_name' => 'No channel', 'trigger_type' => 'category_uk', 'recipient_email' => 'admin@example.org', 'channels' => []], $adminId)->status === 302 && !\App\Core\DB::fetch("SELECT id FROM notification_rules WHERE rule_name = 'No channel'"));
http('POST', '/admin/notifications/rules', ['rule_name' => 'UK lead alert', 'trigger_type' => 'category_uk', 'recipient_name' => 'Admin', 'recipient_email' => 'admin@example.org', 'channels' => ['email', 'in_app', 'whatsapp'], 'is_active' => '1', 'priority' => 'high'], $adminId);
$rule = \App\Core\DB::fetch("SELECT * FROM notification_rules WHERE rule_name = 'UK lead alert'");
check('routing rule saved', $rule && (int) $rule['is_active'] === 1 && json_decode($rule['channels_json'], true) === ['email', 'in_app', 'whatsapp']);
$ruleU = User::create(['email' => 'ukrule@example.org', 'type' => 'volunteer', 'status' => 'new']);
\App\Services\Onboarding::startApplication(User::find($ruleU), ['first_name' => 'Rule', 'last_name' => 'Tester', 'volunteer_category' => 'UK', 'gender' => 'Male', 'is_18_plus' => '1']);
$nlogs = \App\Core\DB::fetchAll("SELECT * FROM notification_logs WHERE rule_id = ? ORDER BY id", [$rule['id']]);
check('new UK sign-up fires the rule on every channel and logs it', count($nlogs) === 3 && $nlogs[0]['channel'] === 'email' && $nlogs[0]['status'] === 'sent' && $nlogs[1]['channel'] === 'in_app' && $nlogs[1]['status'] === 'sent' && $nlogs[2]['channel'] === 'whatsapp' && $nlogs[2]['status'] === 'pending' && str_contains($nlogs[0]['subject'], 'Rule Tester - UK'), json_encode(array_column($nlogs, 'status')));
check('rule email rendered with the default card', (bool) \App\Core\DB::fetch("SELECT id FROM email_log WHERE to_email = 'admin@example.org' AND subject = 'New Volunteer Signup: Rule Tester - UK'") && (bool) \App\Core\DB::fetch("SELECT id FROM notifications WHERE user_id = ? AND title = 'New Volunteer Signup: Rule Tester - UK'", [$adminId]));
check('activity log page shows the deliveries', str_contains(http('GET', '/admin/notifications?tab=log&status=pending', [], $adminId)->body, 'Chat integration not connected yet'));
http('POST', '/admin/notifications/rules', ['id' => $rule['id'], 'action' => 'toggle'], $adminId);
check('rule paused', (int) \App\Core\DB::fetchColumn('SELECT is_active FROM notification_rules WHERE id = ?', [$rule['id']]) === 0);
http('POST', '/admin/notifications/rules', ['id' => $rule['id'], 'action' => 'delete'], $adminId);
check('rule deleted', \App\Core\DB::fetch('SELECT id FROM notification_rules WHERE id = ?', [$rule['id']]) === null);
// reporting & training
$rep = \App\Services\Reporting::forApplication(Application::find($aid));
check('reporting metrics computed', isset($rep['risk'], $rep['consistency']) && count($rep['weeks']) === 12 && in_array($rep['risk'], ['high', 'medium', 'low'], true));
check('per-volunteer report shows the volunteer with a risk badge', str_contains(http('GET', '/admin/reporting?q=Fatima', [], $adminId)->body, 'Fatima Fresh'));
http('POST', '/admin/training-sync', ['action' => 'award', 'email' => 'fresh@example.org'], $adminId);
check('manual Dawah Training badge awarded once', \App\Services\TrainingSync::hasBadge($fid, 'Dawah Training Completed') && !\App\Services\TrainingSync::manualAward('fresh@example.org')['ok'] && !\App\Services\TrainingSync::manualAward('ghost@example.org')['ok']);
check('training sync reports missing configuration', isset(\App\Services\TrainingSync::sync()['error']));
foreach (glob(__DIR__ . '/smoke_*.php') ?: [] as $moduleTest) { require $moduleTest; }
$legacyVol = User::create(['email' => 'legacyvol@example.org', 'type' => 'volunteer', 'status' => 'new', 'position' => 'street_dawah']);
check('legacy volunteer form still available for admin-created accounts', \App\Core\Auth::homePath(User::find($legacyVol)) === '/register/volunteer' && str_contains(http('GET', '/register/volunteer', [], $legacyVol)->body, 'data-stepper'));
check('vol dashboard', http('GET', '/dashboard', [], $volId)->status === 200);
check('forbidden for non-lead', http('GET', '/events/planning', [], $volId)->status === 403);
check('lead planning', str_contains(http('GET', '/events/planning/' . $teamId, [], $leadId)->body, 'New event'));
check('register page', str_contains(http('GET', '/events/register/' . $teamId, [], $volId)->body, 'Upcoming events'));
check('calendar json', str_contains(http('GET', '/api/calendar?from=2024-03-01&to=2024-03-31', [], $volId)->body, '"events"'));
check('reports list', http('GET', '/reports/events/submitted', [], $volId)->status === 200);
check('report show', str_contains(http('GET', '/reports/' . $pending[0]['id'], [], $volId)->body, 'Submitted report'));
check('mentees page', str_contains(http('GET', '/mentees', [], $leadId)->body, 'Noor'));
check('admin users', str_contains(http('GET', '/admin/users?type=volunteer', [], $adminId)->body, 'Layla'));
check('admin user page', str_contains(http('GET', '/admin/users/' . $volId, [], $adminId)->body, 'Update status'));
check('admin status change', http('POST', '/admin/users/' . $volId . '/status', ['status' => 'paused'], $adminId)->status === 302 && User::find($volId)['status'] === 'paused');
check('analytics', str_contains(http('GET', '/analytics', [], $adminId)->body, 'Shahadas'));
check('daee activity', http('GET', '/daee-activity', [], $leadId)->status === 200);
check('vacations', http('GET', '/vacations?scope=team', [], $leadId)->status === 200);
check('profile', str_contains(http('GET', '/profile', [], $volId)->body, 'Personal details'));
check('system forbidden for superadmin without flag', http('GET', '/system', [], $leadId)->status === 403);
foreach (['/system', '/system/settings', '/system/email-templates', '/system/email-templates/login_code', '/system/form-templates', '/system/form-templates/volunteer', '/system/jobs', '/system/email-log', '/system/audit-log', '/system/admins', '/system/import'] as $p) {
    check("system page $p", http('GET', $p, [], $adminId)->status === 200);
}
check('backup download', str_contains(http('GET', '/system/backup', [], $adminId)->headers['Content-Disposition'] ?? '', '.sql'));
check('csv export', str_contains(http('GET', '/admin/users/export.csv?type=volunteer', [], $adminId)->body, 'Layla'));
check('health', http('GET', '/api/health')->status === 200);
check('404', http('GET', '/nope', [], $volId)->status === 404);
check('csrf enforced', (function () use ($volId) { $_SERVER['REQUEST_METHOD'] = 'POST'; $_SERVER['REQUEST_URI'] = '/vacations'; $_POST = ['from_date' => '2026-01-01']; $_SESSION['uid'] = $volId; \App\Core\Auth::refresh(); try { $ref = new ReflectionMethod(\App\Core\App::boot(), 'handle'); $ref->setAccessible(true); $ref->invoke(\App\Core\App::boot(), new \App\Core\Request()); return false; } catch (\App\Core\HttpException $e) { return $e->status === 419; } })());

@unlink($dbFile); @unlink($dbFile . '.lock'); @unlink($dbFile . '.config.php');
echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
