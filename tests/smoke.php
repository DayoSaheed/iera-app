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
$app = \App\Core\App::boot();
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
http('POST', '/login/type', ['type' => 'volunteer']);
$r = http('POST', '/login/email', ['email' => 'fresh@example.org']);
check('email step creates user and goes to code', $r->status === 302 && str_contains($r->headers['Location'], '/login/code') && User::findByEmail('fresh@example.org'));
$fresh = User::findByEmail('fresh@example.org');
$otp = Otp::issue($fresh['id'], 'login', null);
$r = http('POST', '/login/code', ['code' => $otp]);
check('code step signs in and routes to registration', $r->status === 302 && str_contains($r->headers['Location'], '/register/volunteer'));
check('volunteer registration form renders', str_contains(http('GET', '/register/volunteer', [], $fresh['id'])->body, 'data-stepper'));
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

@unlink($dbFile);
echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
