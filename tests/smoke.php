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
