<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Core\Logger;
use App\Models\EmailLog;
use App\Models\JobRun;
use App\Models\LoginAttempt;
use App\Models\Otp;
use App\Models\Report;
use App\Models\Setting;
use App\Models\SubEvent;
use App\Models\Team;
use App\Models\User;
use App\Models\VacationRequest;

/**
 * Scheduled jobs. cron/run.php runs every 5 minutes and calls Jobs::runDue().
 * Each job declares how often it runs through a "period key"; a (job, period) pair runs at most once.
 */
final class Jobs
{
    /** job => [label, period callback, description] */
    public static function definitions(): array
    {
        $every5 = fn() => date('Y-m-d H:') . str_pad((string) (intdiv((int) date('i'), 5) * 5), 2, '0', STR_PAD_LEFT);
        $hourly = fn() => date('Y-m-d H');
        $every6h = fn() => date('Y-m-d') . '-' . intdiv((int) date('G'), 6);
        $daily = fn() => date('Y-m-d');
        $weekly = fn() => date('o-\WW');
        $monthly = fn() => date('Y-m');
        return [
            'materialize_events' => ['Keep upcoming event occurrences open', $every5, 'Creates missing future occurrences of recurring events.'],
            'event_reports' => ['Generate event reports', $every5, 'Opens a pending report for each attendee once an event has ended.'],
            'mentor_reports' => ['Generate weekly mentor reports', $weekly, 'Every week (Sunday 05:00) one pending report per accepted mentor/mentee pair.', ['dow' => [0], 'hour' => 5]],
            'mentor_reminder_sun' => ['Mentor report reminder (Sunday)', $weekly, 'Reminds mentors with pending reports.', ['dow' => [0], 'hour' => 8]],
            'mentor_reminder_mon' => ['Mentor report reminder (Monday)', $weekly, '', ['dow' => [1], 'hour' => 8]],
            'mentor_reminder_thu' => ['Mentor report reminder (Thursday)', $weekly, '', ['dow' => [4], 'hour' => 8]],
            'pending_report_reminders' => ['Pending event report reminders', $hourly, 'First and second reminders for pending event reports.'],
            'event_reminders' => ['Upcoming event reminders', $daily, 'Emails team members two days before an event.', ['hour' => 6]],
            'shift_up_reminder' => ['Shift-up reminder', $daily, "Emails da'ees with no sign-up for next month on the 22nd and 25th.", ['dom' => [22, 25], 'hour' => 6]],
            'low_events_notice' => ['Low events notice to leads', $monthly, 'On the 1st, warns leads of teams with too few events this month.', ['dom' => [1], 'hour' => 7]],
            'pause_inactive' => ['Pause inactive volunteers', $monthly, 'Volunteers without login for N days become paused.', ['dom' => [1], 'hour' => 1]],
            'newmuslim_inactive' => ['Mark unresponsive New Muslims inactive', $weekly, 'Four consecutive weekly reports with no response.', ['dow' => [1], 'hour' => 6]],
            'sharepoint_sync' => ['SharePoint sync', $every5, 'Pushes submitted reports to SharePoint lists (when enabled).'],
            'export_reports' => ['Export report snapshots', $every6h, 'Writes JSON snapshots for BI tools.'],
            'housekeeping' => ['Housekeeping', $daily, 'Purges expired codes, old logs and job history.', ['hour' => 3]],
            'interview_reminders' => ['Interview reminders (2 days before)', $daily, 'Emails Canadian applicants two days before their booked interview.', ['hour' => 8]],
            'ra_reminders' => ['Risk assessments not started', $daily, 'Reminds the processors about risk assessments not started 3+ days after qualification.', ['hour' => 9]],
            'onboarding_reminders' => ['Weekly onboarding reminders', $weekly, 'Monday: Canadian and Online applicants with outstanding onboarding steps.', ['dow' => [1], 'hour' => 8]],
            'gorap_reminders' => ['GORAP training reminders', $weekly, 'Monday: approved Exhibitions / Street Dawah volunteers without the GORAP training.', ['dow' => [1], 'hour' => 8]],
            'comm_scheduled' => ['Scheduled communications', $every5, 'Delivers bulk communications whose scheduled time has arrived.'],
            'inactivity_nudge' => ['Inactivity nudge & zero-hours chaser', $daily, 'Daily at 09:00: emails approved volunteers with no activity for 30 days (reminder template) and, on the last day of the month, those with zero hours this month (chaser template).', ['hour' => 9]],
            'training_sync' => ['Training completions sync', $every6h, 'Pulls LearnDash completions from the training site and awards the Dawah Training badge (when configured).'],
            'story_sync' => ['Story hub sync', $daily, 'Imports new stories from the global iERA site.', ['hour' => 4]],
            'teams_flush' => ['Microsoft Teams delivery', $every5, 'Retries Teams cards that could not be delivered immediately and clears old sent cards.'],
        ];
    }

    /** Run every job whose period has not run yet and whose time window matches. */
    public static function runDue(bool $force = false, ?string $only = null): array
    {
        $results = [];
        foreach (self::definitions() as $job => $def) {
            if ($only && $only !== $job) {
                continue;
            }
            if (!$force && !self::windowMatches($def[3] ?? [])) {
                continue;
            }
            $period = $force ? date('Y-m-d H:i:s') . '-manual' : ($def[1])();
            $runId = JobRun::claim($job, $period);
            if ($runId === null) {
                continue;
            }
            try {
                $msg = (string) self::$job();
                JobRun::finish($runId, 'ok', $msg);
                $results[$job] = $msg;
            } catch (\Throwable $e) {
                JobRun::finish($runId, 'failed', $e->getMessage());
                Logger::error("Job $job failed: " . $e->getMessage());
                $results[$job] = 'FAILED: ' . $e->getMessage();
            }
        }
        return $results;
    }

    private static function windowMatches(array $w): bool
    {
        if (isset($w['dow']) && !in_array((int) date('w'), $w['dow'], true)) {
            return false;
        }
        if (isset($w['dom']) && !in_array((int) date('j'), $w['dom'], true)) {
            return false;
        }
        if (isset($w['hour']) && (int) date('G') < $w['hour']) {
            return false;
        }
        return true;
    }

    // ---- jobs ----------------------------------------------------------

    public static function comm_scheduled(): string
    {
        return Communications::runScheduled() . ' scheduled send(s) delivered';
    }

    public static function inactivity_nudge(): string
    {
        $r = Communications::inactivityCheck();
        return $r['inactive'] . ' inactive volunteer(s) nudged' . ($r['last_day'] ? ', ' . $r['zero_hours'] . ' zero-hours chaser(s)' : '');
    }

    public static function training_sync(): string
    {
        $r = TrainingSync::sync();
        if (isset($r['error'])) {
            return 'Skipped: ' . $r['error'];
        }
        return count($r['awarded']) . ' awarded, ' . count($r['skipped']) . ' skipped, ' . count($r['errors']) . ' error(s)';
    }

    public static function teams_flush(): string
    {
        $r = Teams::flush();
        Teams::purge();
        return $r['sent'] . ' card(s) delivered, ' . $r['failed'] . ' still pending';
    }

    public static function story_sync(): string
    {
        $r = StorySync::sync();
        return isset($r['error']) ? 'Skipped: ' . $r['error'] : $r['imported'] . ' imported, ' . $r['skipped'] . ' skipped';
    }

    public static function interview_reminders(): string
    {
        return Onboarding::interviewReminders() . ' reminder(s) sent';
    }

    public static function ra_reminders(): string
    {
        return Onboarding::raReminders() . ' risk assessment(s) chased';
    }

    public static function onboarding_reminders(): string
    {
        return Onboarding::onboardingReminders() . ' applicant(s) reminded';
    }

    public static function gorap_reminders(): string
    {
        return Onboarding::gorapReminders() . ' volunteer(s) reminded';
    }

    public static function materialize_events(): string
    {
        return Recurrence::materializeAll() . ' occurrence(s) created';
    }

    public static function event_reports(): string
    {
        return ReportGenerator::eventReports() . ' report(s) opened';
    }

    public static function mentor_reports(): string
    {
        return ReportGenerator::mentorReports() . ' mentor report(s) opened for ' . ReportGenerator::weekKey();
    }

    public static function mentor_reminder_sun(): string { return self::mentorReminder(); }
    public static function mentor_reminder_mon(): string { return self::mentorReminder(); }
    public static function mentor_reminder_thu(): string { return self::mentorReminder(); }

    private static function mentorReminder(): string
    {
        $onVacation = VacationRequest::onVacationUserIds();
        $rows = DB::fetchAll(
            "SELECT u.id, u.email, u.first_name, COUNT(*) c FROM reports r JOIN users u ON u.id = r.assignee_id
             WHERE r.type = 'mentor' AND r.status = 'pending' AND u.status = 'approved' GROUP BY u.id, u.email, u.first_name"
        );
        $n = 0;
        foreach ($rows as $r) {
            if (in_array($r['id'], $onVacation, true)) {
                continue;
            }
            if (EmailService::send('mentor_reminder', [$r['email']], ['name' => $r['first_name'], 'count' => (int) $r['c']])) {
                $n++;
            }
        }
        return "$n reminder(s) sent";
    }

    public static function pending_report_reminders(): string
    {
        $n = 0;
        $h1 = max(1, Setting::int('report_reminder_hours'));
        $h2 = max($h1 + 1, Setting::int('report_reminder_hours_2'));
        foreach ([['pending_report_reminder', $h1, 'reminder_1'], ['pending_report_reminder_day', $h2, 'reminder_2']] as [$tpl, $hours, $kind]) {
            foreach (Report::pendingOlderThan('event_report', $hours, $kind) as $r) {
                $s = SubEvent::find($r['subject_id']);
                $u = User::find($r['assignee_id'], false);
                if ($s && $u && $u['status'] === 'approved') {
                    $leads = array_map(fn($a) => User::fullName($a), Team::admins($s['team_id']));
                    EmailService::send($tpl, [$u['email']], [
                        'name' => $u['first_name'],
                        'title' => $s['name'],
                        'when' => fmt_datetime($s['start_at']),
                        'lead' => $leads ? implode(', ', $leads) : 'iERA Volunteer Team',
                    ]);
                    $n++;
                }
                Report::markNotified($r['id'], $kind);
            }
        }
        return "$n reminder(s) sent";
    }

    public static function event_reminders(): string
    {
        $days = max(1, Setting::int('event_reminder_days'));
        $day = date('Y-m-d', strtotime("+$days days"));
        $events = SubEvent::window(null, "$day 00:00:00", "$day 23:59:59");
        $n = 0;
        $onVacation = VacationRequest::onVacationUserIds();
        foreach ($events as $s) {
            $leads = array_map(fn($a) => User::fullName($a), Team::admins($s['team_id']));
            $where = trim(($s['description'] ?? '') . ', ' . ($s['city'] ?? ''), ', ');
            foreach (Team::members($s['team_id']) as $m) {
                if ($m['status'] !== 'approved' || in_array($m['id'], $onVacation, true)) {
                    continue;
                }
                EmailService::send('event_reminder', [$m['email']], [
                    'name' => $m['first_name'],
                    'title' => $s['name'],
                    'where' => $where,
                    'when' => fmt_datetime($s['start_at']),
                    'lead' => $leads ? implode(', ', $leads) : 'iERA Volunteer Team',
                ]);
                $n++;
            }
        }
        return count($events) . ' event(s), ' . $n . ' email(s)';
    }

    public static function shift_up_reminder(): string
    {
        $from = date('Y-m-01 00:00:00', strtotime('first day of next month'));
        $to = date('Y-m-t 23:59:59', strtotime('first day of next month'));
        $signedUp = array_column(DB::fetchAll(
            "SELECT DISTINCT a.user_id FROM sub_event_attendance a JOIN sub_events s ON s.id = a.sub_event_id WHERE s.status = 'active' AND s.start_at BETWEEN ? AND ?",
            [$from, $to]
        ), 'user_id');
        $onVacation = VacationRequest::onVacationUserIds();
        $n = 0;
        foreach (User::list(['type' => 'volunteer', 'sub_group' => 'daee', 'status' => ['approved']], false) as $u) {
            if (in_array($u['id'], $signedUp, true) || in_array($u['id'], $onVacation, true)) {
                continue;
            }
            if (EmailService::send('shift_up_reminder', [$u['email']], ['name' => $u['first_name']])) {
                $n++;
            }
        }
        return "$n reminder(s) sent";
    }

    public static function low_events_notice(): string
    {
        $min = max(1, Setting::int('min_events_per_month'));
        $from = date('Y-m-01 00:00:00');
        $to = date('Y-m-t 23:59:59');
        $n = 0;
        foreach (Team::all('daee', 'approved') as $t) {
            $count = (int) DB::fetchColumn("SELECT COUNT(*) FROM sub_events WHERE team_id = ? AND status = 'active' AND start_at BETWEEN ? AND ?", [$t['id'], $from, $to]);
            if ($count >= $min) {
                continue;
            }
            $to_ = Team::adminEmails($t['id']);
            if (!$to_) {
                continue;
            }
            EmailService::send('low_events_notice', $to_, ['team' => $t['name'], 'month' => date('F Y'), 'count' => $count, 'min' => $min], ['cc' => Setting::emails('volunteer_manager_email')]);
            $n++;
        }
        return "$n team(s) notified";
    }

    public static function pause_inactive(): string
    {
        $md = date('m-d');
        $skipFrom = (string) Setting::get('inactivity_skip_from');
        $skipTo = (string) Setting::get('inactivity_skip_to');
        if ($skipFrom && $skipTo && ($md >= $skipFrom || $md <= $skipTo)) {
            return 'skipped (winter break window)';
        }
        $days = max(30, Setting::int('inactivity_days'));
        $cutoff = date('Y-m-d H:i:s', time() - $days * 86400);
        $onVacation = VacationRequest::onVacationUserIds();
        $rows = DB::fetchAll("SELECT id FROM users WHERE type = 'volunteer' AND status = 'approved' AND last_login_at IS NOT NULL AND last_login_at < ?", [$cutoff]);
        $n = 0;
        foreach ($rows as $r) {
            if (in_array($r['id'], $onVacation, true)) {
                continue;
            }
            User::save($r['id'], ['status' => 'paused']);
            \App\Models\AuditLog::log('user.auto_paused', 'user', $r['id'], "no login for $days days", null);
            $n++;
        }
        return "$n volunteer(s) paused";
    }

    public static function newmuslim_inactive(): string
    {
        $since = date('Y-m-d H:i:s', time() - 28 * 86400);
        $mentees = DB::fetchAll(
            "SELECT subject_id, COUNT(*) total,
                    SUM(CASE WHEN status = 'submitted' THEN 1 ELSE 0 END) submitted
             FROM reports WHERE type = 'mentor' AND created_at >= ? GROUP BY subject_id HAVING COUNT(*) >= 4",
            [$since]
        );
        $list = [];
        foreach ($mentees as $m) {
            if ((int) $m['submitted'] < (int) $m['total']) {
                continue;
            }
            $reports = DB::fetchAll("SELECT submit_json FROM reports WHERE type = 'mentor' AND subject_id = ? AND created_at >= ?", [$m['subject_id'], $since]);
            $allNo = true;
            foreach ($reports as $r) {
                $s = json_decode((string) $r['submit_json'], true) ?: [];
                if (($s['mentee_responded'] ?? 'yes') !== 'no') {
                    $allNo = false;
                    break;
                }
            }
            if (!$allNo) {
                continue;
            }
            $u = User::find($m['subject_id'], false);
            if ($u && $u['type'] === 'newmuslim' && $u['status'] !== 'inactive') {
                User::save($u['id'], ['status' => 'inactive']);
                \App\Models\AuditLog::log('user.auto_inactive', 'user', $u['id'], '4 weeks unresponsive', null);
                $list[] = User::fullName($u) . ' (' . $u['email'] . ')';
            }
        }
        if ($list) {
            EmailService::send('newmuslim_inactive_admin', EmailService::adminRecipients('newmuslim_coordinator_email'), ['list' => '<ul><li>' . implode('</li><li>', array_map('e', $list)) . '</li></ul>']);
        }
        return count($list) . ' marked inactive';
    }

    public static function sharepoint_sync(): string
    {
        if (!SharePointSync::enabled()) {
            return 'disabled';
        }
        $a = SharePointSync::syncEventReports();
        $b = SharePointSync::syncMentorReports();
        return "events {$a['synced']}/{$a['failed']} failed, mentor {$b['synced']}/{$b['failed']} failed";
    }

    public static function export_reports(): string
    {
        if (!Setting::bool('reports_export_enabled')) {
            return 'disabled';
        }
        $dir = STORAGE_PATH . '/exports';
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        foreach (['event_report', 'mentor'] as $type) {
            $rows = Report::list(['type' => $type, 'status' => 'submitted']);
            $out = array_map(fn($r) => [
                'id' => $r['id'], 'type' => $type, 'status' => $r['status'], 'created_at' => $r['created_at'], 'submitted_at' => $r['submitted_at'],
                'assignee' => $r['assignee_name'], 'email' => $r['email'], 'team' => implode(', ', $r['team_names']),
                'event' => $r['event_name'] ?? null, 'start' => $r['start_at'] ?? null, 'mentee' => $r['mentee_name'] ?? null, 'submit' => $r['submit'],
            ], $rows);
            file_put_contents("$dir/$type.json", json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
        return 'snapshots written';
    }

    public static function housekeeping(): string
    {
        Otp::purge();
        LoginAttempt::purge();
        EmailLog::purge(90);
        JobRun::purge(60);
        DB::delete('remember_tokens', 'expires_at < ?', [now()]);
        DB::delete('password_resets', 'expires_at < ?', [now()]);
        return 'done';
    }
}
