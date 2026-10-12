#!/usr/bin/env php
<?php
/** Local development only: fills the database with sample teams, volunteers, events and reports. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { exit('CLI only'); }
require dirname(__DIR__) . '/app/bootstrap.php';
if (\App\Core\Config::get('app.env') === 'production') { fwrite(STDERR, "Refusing to seed demo data in production.\n"); exit(1); }

use App\Models\Event; use App\Models\MentorAssignment; use App\Models\Report; use App\Models\SubEvent; use App\Models\Team; use App\Models\User;
use App\Services\Recurrence; use App\Services\ReportGenerator;

$t = Team::create(['name' => 'Toronto Downtown', 'parent_team' => 'daee']);
$t2 = Team::create(['name' => 'Mississauga', 'parent_team' => 'daee']);
$t3 = Team::create(['name' => 'Calgary', 'parent_team' => 'daee']);
$lead = User::create(['email' => 'lead@example.org', 'type' => 'volunteer', 'status' => 'approved', 'first_name' => 'Layla', 'last_name' => 'Ahmed', 'gender' => 'female', 'city' => 'Toronto, ON', 'mobile' => '(416) 555-1000', 'groups' => ['volunteer'], 'sub_groups' => ['daee', 'mentor'], 'teams' => [$t], 'admin_teams' => [$t]]);
$people = [['Yusuf', 'Khan', 'male', 'Toronto, ON', $t], ['Amina', 'Siddiqui', 'female', 'Mississauga, ON', $t2], ['Omar', 'Farouk', 'male', 'Toronto, ON', $t], ['Hana', 'Rahman', 'female', 'Mississauga, ON', $t2], ['Bilal', 'Hussain', 'male', 'Calgary, AB', $t3], ['Zainab', 'Ali', 'female', 'Calgary, AB', $t3], ['Ibrahim', 'Musa', 'male', 'Vancouver, BC', $t3], ['Maryam', 'Noor', 'female', 'Montreal, QC', $t2]];
$vols = [];
foreach ($people as $i => [$f, $l, $g, $c, $team]) {
    $vols[] = User::create(['email' => strtolower("$f.$l@example.org"), 'type' => 'volunteer', 'status' => 'approved', 'first_name' => $f, 'last_name' => $l, 'gender' => $g, 'city' => $c, 'mobile' => '(416) 555-20' . $i, 'groups' => ['volunteer'], 'sub_groups' => ['daee'], 'teams' => [$team], 'pin' => (string) (100 + $i)]);
}
$nm = User::create(['email' => 'noor@example.org', 'type' => 'newmuslim', 'status' => 'approved', 'first_name' => 'Noor', 'last_name' => 'Williams', 'gender' => 'female', 'city' => 'Toronto, ON', 'phone' => '(647) 555-3000', 'groups' => ['newmuslim'], 'previous_religion' => 'Christianity', 'shahada_location' => 'Yonge-Dundas Square']);
$nm2 = User::create(['email' => 'james@example.org', 'type' => 'newmuslim', 'status' => 'enrolled', 'first_name' => 'James', 'last_name' => 'Carter', 'gender' => 'male', 'city' => 'Calgary, AB', 'phone' => '(647) 555-3001', 'groups' => ['newmuslim'], 'created_by' => $vols[0]]);
User::create(['email' => 'newvol@example.org', 'type' => 'volunteer', 'status' => 'in_review', 'first_name' => 'Sara', 'last_name' => 'Malik', 'gender' => 'female', 'city' => 'Brampton, ON', 'groups' => ['volunteer']]);
$ev = Event::create(['team_id' => $t, 'name' => 'Yonge & Dundas Dawah Table', 'description' => 'Yonge-Dundas Square, north side', 'city' => 'Toronto, ON', 'min_participants' => 3, 'event_type' => 'recurring', 'is_public' => 0, 'active_events' => 4, 'start_at' => date('Y-m-d 13:00:00', strtotime('last saturday -3 weeks')), 'event_length' => 3, 'recurrence' => 'weekly', 'status' => 'active', 'author_id' => $lead]);
Recurrence::materialize(Event::find($ev));
$ev2 = Event::create(['team_id' => $t2, 'name' => 'Square One Outreach', 'description' => 'Celebration Square', 'city' => 'Mississauga, ON', 'min_participants' => 2, 'event_type' => 'recurring', 'is_public' => 0, 'active_events' => 3, 'start_at' => date('Y-m-d 15:00:00', strtotime('last sunday -2 weeks')), 'event_length' => 2, 'recurrence' => 'weekly', 'status' => 'active', 'author_id' => $lead]);
Recurrence::materialize(Event::find($ev2));
foreach (SubEvent::futureForEvent($ev) as $i => $s) { if ($i < 2) { SubEvent::attend($s['id'], $vols[0], SubEvent::timeSlots($s)); SubEvent::attend($s['id'], $lead, SubEvent::timeSlots($s)); } }
for ($w = 1; $w <= 4; $w++) {
    $p = SubEvent::create(['event_id' => $ev, 'team_id' => $t, 'name' => 'Yonge & Dundas Dawah Table', 'description' => 'Yonge-Dundas Square, north side', 'city' => 'Toronto, ON', 'min_participants' => 3, 'start_at' => date('Y-m-d 13:00:00', strtotime("-$w weeks saturday")), 'event_length' => 3, 'status' => 'active']);
    SubEvent::attend($p, $vols[0], ['slot']); SubEvent::attend($p, $vols[2], ['slot']); SubEvent::attend($p, $lead, ['slot']);
}
ReportGenerator::eventReports();
foreach (Report::list(['type' => 'event_report', 'status' => 'pending']) as $i => $r) {
    if ($i % 3 !== 0) { Report::submit($r['id'], ['attend' => 'yes', 'chats' => rand(3, 12), 'chats_quality' => rand(1, 5), 'shahada' => rand(0, 1), 'materials' => rand(5, 30), 'quran' => rand(0, 3), 'duration' => 3, 'any_story' => 'A student asked for a Quran after a long chat.'], $r['assignee_id'], false); }
}
MentorAssignment::assign($lead, $nm, null); MentorAssignment::accept($lead, $nm); ReportGenerator::firstMentorReport($lead, $nm);
MentorAssignment::assign($lead, $nm2, null);
echo "Demo data created. Sign in as admin (create one with tools/create-admin.php) or lead@example.org via the emailed code (storage/logs/mail.log).\n";
