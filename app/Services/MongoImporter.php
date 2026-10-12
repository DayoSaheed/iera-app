<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Core\Str;
use App\Models\MentorAssignment;
use App\Models\Report;
use App\Models\SubEvent;
use App\Models\Team;
use App\Models\User;

/**
 * Imports mongoexport JSON (array or one document per line) from the legacy app.
 * Order: teams -> users -> events -> sub_events -> reports -> additional_user_info -> form_templates
 */
final class MongoImporter
{
    public const COLLECTIONS = ['iera_teams', 'iera_users', 'iera_events', 'iera_sub_events', 'iera_reports', 'additional_user_info', 'form_templates'];

    public static function importFile(string $collection, string $path): array
    {
        $docs = self::parse((string) file_get_contents($path));
        return self::import($collection, $docs);
    }

    public static function parse(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }
        $data = json_decode($raw, true);
        if (is_array($data)) {
            return array_is_list($data) ? $data : [$data];
        }
        $docs = [];
        foreach (preg_split('/\r?\n/', $raw) ?: [] as $line) {
            $line = trim($line);
            if ($line !== '') {
                $d = json_decode($line, true);
                if (is_array($d)) {
                    $docs[] = $d;
                }
            }
        }
        return $docs;
    }

    /** Unwrap mongoexport extended JSON values ($date, $oid, $numberLong...). */
    private static function v(mixed $x): mixed
    {
        if (is_array($x)) {
            if (isset($x['$date'])) {
                $d = $x['$date'];
                if (is_array($d) && isset($d['$numberLong'])) {
                    return date('Y-m-d H:i:s', intdiv((int) $d['$numberLong'], 1000));
                }
                $t = is_numeric($d) ? (int) $d / 1000 : strtotime((string) $d);
                return $t ? date('Y-m-d H:i:s', (int) $t) : null;
            }
            if (isset($x['$oid'])) {
                return $x['$oid'];
            }
            if (isset($x['$numberLong'])) {
                return (int) $x['$numberLong'];
            }
            if (isset($x['$numberInt'])) {
                return (int) $x['$numberInt'];
            }
            if (isset($x['$numberDouble'])) {
                return (float) $x['$numberDouble'];
            }
            return array_map([self::class, 'v'], $x);
        }
        return $x;
    }

    private static function dt(mixed $x): ?string
    {
        $x = self::v($x);
        if (!$x) {
            return null;
        }
        if (is_string($x)) {
            $t = strtotime($x);
            return $t ? date('Y-m-d H:i:s', $t) : null;
        }
        return null;
    }

    public static function import(string $collection, array $docs): array
    {
        $imported = $skipped = 0;
        $errors = [];
        foreach ($docs as $i => $doc) {
            try {
                $doc = self::v($doc);
                $ok = match ($collection) {
                    'iera_teams' => self::team($doc),
                    'iera_users' => self::user($doc),
                    'iera_events' => self::event($doc),
                    'iera_sub_events' => self::subEvent($doc),
                    'iera_reports' => self::reports($doc),
                    'additional_user_info' => self::additional($doc),
                    'form_templates' => self::formTemplate($doc),
                    default => false,
                };
                $ok ? $imported++ : $skipped++;
            } catch (\Throwable $e) {
                $skipped++;
                if (count($errors) < 20) {
                    $errors[] = "#$i: " . $e->getMessage();
                }
            }
        }
        return ['collection' => $collection, 'imported' => $imported, 'skipped' => $skipped, 'errors' => $errors];
    }

    private static function team(array $d): bool
    {
        $id = (string) ($d['_id'] ?? '');
        if ($id === '') {
            return false;
        }
        $data = ['name' => $d['name'] ?? Str::startCase($id), 'parent_team' => $d['parent_team'] ?? null, 'status' => ($d['status'] ?? 'approved') === 'inactive' ? 'inactive' : 'approved', 'description' => $d['description'] ?? null];
        if (Team::find($id)) {
            Team::update($id, $data);
        } else {
            Team::create($data + ['id' => $id]);
        }
        return true;
    }

    private static function status(string $s, string $type): string
    {
        $s = strtolower(trim($s));
        $map = ['active' => 'approved', 'in progress' => 'in_review', 'on call' => 'paused', 'removed' => 'removed', 'pending' => 'in_review', '' => 'new'];
        $s = $map[$s] ?? $s;
        $valid = $type === 'newmuslim' ? array_keys(User::NEWMUSLIM_STATUSES) : array_keys(User::VOLUNTEER_STATUSES);
        return in_array($s, $valid, true) ? $s : ($type === 'newmuslim' ? 'approved' : 'in_review');
    }

    private static function user(array $d): bool
    {
        $email = strtolower(trim((string) ($d['email'] ?? '')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        $type = in_array($d['type'] ?? '', User::TYPES, true) ? $d['type'] : (in_array('newmuslim', $d['groups'] ?? [], true) ? 'newmuslim' : 'volunteer');
        $id = (string) ($d['_id'] ?? User::idForEmail($email));
        $skip = ['_id', 'email', 'password', 'type', 'groups', 'sub_groups', 'sub_group', 'teams', 'admins', 'mentees', 'accepted_mentees', 'ids', 'created_on', 'updated_on', 'last_login', 'status', 'created_by', 'training_date', 'shahada_location_old', 'social'];
        $data = ['email' => $email, 'type' => $type, 'status' => self::status((string) ($d['status'] ?? ''), $type)];
        foreach ($d as $k => $v) {
            if (in_array($k, $skip, true)) {
                continue;
            }
            $data[$k] = is_array($v) && !array_is_list($v) ? json_encode($v) : $v;
        }
        $data['shahada_location'] = $d['shahada_location'] ?? $d['shahada_location_old'] ?? null;
        $data['social'] = $d['social'] ?? null;
        $data['created_by'] = $d['created_by'] ?? null;
        $data['training_date'] = self::dt($d['training_date'] ?? null);
        $data['last_login_at'] = self::dt($d['last_login'] ?? null);
        $groups = array_values(array_intersect((array) ($d['groups'] ?? []), User::GROUPS));
        $subs = array_values(array_intersect(array_merge((array) ($d['sub_groups'] ?? []), (array) ($d['sub_group'] ?? [])), User::SUB_GROUPS));
        $teams = array_values(array_filter((array) ($d['teams'] ?? []), 'is_string'));
        $admins = array_values(array_filter((array) ($d['admins'] ?? []), 'is_string'));
        $existing = User::find($id, false);
        if ($existing) {
            User::save($id, $data + ['groups' => $groups, 'sub_groups' => $subs, 'teams' => $teams, 'admin_teams' => $admins]);
        } else {
            User::create($data + ['id' => $id, 'groups' => $groups, 'sub_groups' => $subs, 'teams' => $teams, 'admin_teams' => $admins]);
            if ($created = self::dt($d['created_on'] ?? null)) {
                DB::update('users', ['created_at' => $created], 'id = ?', [$id]);
            }
        }
        foreach (array_values(array_filter((array) ($d['mentees'] ?? []), 'is_string')) as $menteeId) {
            self::deferredAssignments()[] = [$id, $menteeId, in_array($menteeId, (array) ($d['accepted_mentees'] ?? []), true)];
        }
        self::flushAssignments();
        return true;
    }

    private static array $pendingAssignments = [];

    private static function &deferredAssignments(): array
    {
        return self::$pendingAssignments;
    }

    /** Assignments reference users that may be imported later; retry each time. */
    private static function flushAssignments(): void
    {
        foreach (self::$pendingAssignments as $k => [$mentor, $mentee, $accepted]) {
            if (User::exists($mentor) && User::exists($mentee)) {
                MentorAssignment::assign($mentor, $mentee, null);
                if ($accepted) {
                    MentorAssignment::accept($mentor, $mentee);
                }
                unset(self::$pendingAssignments[$k]);
            }
        }
    }

    private static function event(array $d): bool
    {
        $id = (string) ($d['_id'] ?? '');
        $teamId = (string) ($d['teamId'] ?? '');
        if ($id === '' || !Team::find($teamId)) {
            return false;
        }
        $row = [
            'team_id' => $teamId,
            'name' => (string) ($d['name'] ?? 'Event'),
            'description' => $d['description'] ?? null,
            'city' => $d['city'] ?? null,
            'min_participants' => (int) ($d['min_participants'] ?? 0),
            'event_type' => ($d['event_type'] ?? 'recurring') === 'single' ? 'single' : 'recurring',
            'is_public' => !empty($d['public']) ? 1 : 0,
            'active_events' => max(1, (int) ($d['active_events'] ?? 4)),
            'start_at' => self::dt($d['start'] ?? null) ?? now(),
            'event_length' => (float) ($d['event_length'] ?? 2),
            'recurrence' => in_array($d['recurring'] ?? '', ['weekly', 'monthly', 'yearly'], true) ? $d['recurring'] : 'weekly',
            'status' => ($d['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active',
            'author_id' => $d['author']['id'] ?? null,
        ];
        if (DB::fetchColumn('SELECT 1 FROM events WHERE id = ?', [$id])) {
            DB::update('events', $row + ['updated_at' => now()], 'id = ?', [$id]);
        } else {
            DB::insert('events', $row + ['id' => $id, 'created_at' => now(), 'updated_at' => now()]);
        }
        return true;
    }

    private static function subEvent(array $d): bool
    {
        $id = (string) ($d['_id'] ?? '');
        $teamId = (string) ($d['teamId'] ?? '');
        if ($id === '' || !Team::find($teamId)) {
            return false;
        }
        $parent = $d['parent_id'] ?? null;
        if ($parent && !DB::fetchColumn('SELECT 1 FROM events WHERE id = ?', [$parent])) {
            $parent = null;
        }
        $row = [
            'event_id' => $parent,
            'team_id' => $teamId,
            'name' => (string) ($d['name'] ?? 'Event'),
            'description' => $d['description'] ?? null,
            'city' => $d['city'] ?? null,
            'min_participants' => (int) ($d['min_participants'] ?? 0),
            'start_at' => self::dt($d['start'] ?? null) ?? now(),
            'event_length' => (float) ($d['event_length'] ?? 2),
            'status' => ($d['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active',
            'report_generated_at' => !empty($d['report']) ? now() : null,
        ];
        if (DB::fetchColumn('SELECT 1 FROM sub_events WHERE id = ?', [$id])) {
            DB::update('sub_events', $row + ['updated_at' => now()], 'id = ?', [$id]);
        } else {
            DB::insert('sub_events', $row + ['id' => $id, 'created_at' => now(), 'updated_at' => now()]);
        }
        foreach ((array) ($d['attendance'] ?? []) as $a) {
            $uid = (string) ($a['id'] ?? $a['user']['id'] ?? '');
            if ($uid && User::exists($uid)) {
                SubEvent::attend($id, $uid, (array) ($a['times'] ?? []), $a['by'] ?? null);
            }
        }
        return true;
    }

    private static function reports(array $d): bool
    {
        $subject = (string) ($d['_id'] ?? '');
        $type = ($d['type'] ?? 'event_report') === 'mentor' ? 'mentor' : 'event_report';
        if ($subject === '') {
            return false;
        }
        $n = 0;
        foreach ((array) ($d['reports'] ?? []) as $r) {
            $assignee = (string) ($r['mentor_id'] ?? $r['user']['_id'] ?? '');
            if (!$assignee || !User::exists($assignee)) {
                continue;
            }
            $created = self::dt($r['created_at'] ?? null) ?? now();
            $period = $type === 'mentor' ? date('o-\WW', strtotime($created)) : 'event';
            $id = (string) ($r['id'] ?? Str::uuid());
            if (!DB::fetchColumn('SELECT 1 FROM reports WHERE id = ?', [$id]) && !Report::exists($type, $subject, $assignee, $period)) {
                DB::insert('reports', [
                    'id' => $id, 'type' => $type, 'subject_id' => $subject, 'assignee_id' => $assignee, 'period_key' => $period,
                    'status' => ($r['status'] ?? 'pending') === 'submitted' ? 'submitted' : 'pending',
                    'submit_json' => !empty($r['submit']) ? json_encode($r['submit']) : null,
                    'submitted_by' => $r['submitted_by']['_id'] ?? null,
                    'submitted_at' => ($r['status'] ?? '') === 'submitted' ? (self::dt($r['updated_at'] ?? null) ?? $created) : null,
                    'sharepoint_entry_id' => $r['entry_id'] ?? null,
                    'created_at' => $created, 'updated_at' => self::dt($r['updated_at'] ?? null) ?? $created,
                ]);
                $n++;
            }
        }
        return $n > 0;
    }

    private static function additional(array $d): bool
    {
        $uid = (string) ($d['_id'] ?? '');
        if (!$uid || !User::exists($uid)) {
            return false;
        }
        foreach ((array) ($d['vacation_requests'] ?? []) as $v) {
            $id = (string) ($v['_id'] ?? Str::uuid());
            if (!DB::fetchColumn('SELECT 1 FROM vacation_requests WHERE id = ?', [$id])) {
                $status = strtolower((string) ($v['status'] ?? 'requested'));
                DB::insert('vacation_requests', [
                    'id' => $id, 'user_id' => $uid, 'reason' => $v['reason'] ?? null,
                    'from_date' => !empty($v['fromDate']) ? date('Y-m-d', strtotime((string) self::v($v['fromDate']))) : null,
                    'to_date' => !empty($v['toDate']) ? date('Y-m-d', strtotime((string) self::v($v['toDate']))) : null,
                    'status' => in_array($status, ['approved', 'denied'], true) ? $status : 'requested',
                    'created_at' => self::dt($v['created_at'] ?? null) ?? now(), 'updated_at' => now(),
                ]);
            }
        }
        foreach ((array) ($d['admin_comments'] ?? []) as $c) {
            $text = trim((string) ($c['comment'] ?? $c['comments'] ?? $c['text'] ?? ''));
            if ($text !== '') {
                DB::insert('admin_comments', ['user_id' => $uid, 'author_id' => $c['user']['_id'] ?? null, 'comment' => $text, 'created_at' => self::dt($c['created_at'] ?? null) ?? now()]);
            }
        }
        return true;
    }

    private static function formTemplate(array $d): bool
    {
        $id = (string) ($d['_id'] ?? '');
        if ($id === '' || empty($d['form']) || !is_array($d['form'])) {
            return false;
        }
        $fields = [];
        foreach ($d['form'] as $f) {
            if (($f['type'] ?? '') === 'select_old') {
                $f['type'] = 'select';
            }
            if (isset($f['dynamic']['url'])) {
                $url = (string) $f['dynamic']['url'];
                $f['dynamic'] = str_contains($url, 'teams') ? 'teams' : (str_contains($url, 'newmuslim') ? 'newmuslims' : (str_contains($url, 'mentor') ? 'mentors' : 'volunteers'));
            }
            if (($f['name'] ?? '') === 'start') { $f['name'] = 'start_at'; }
            if (($f['name'] ?? '') === 'recurring') { $f['name'] = 'recurrence'; }
            if (($f['name'] ?? '') === 'public') { $f['name'] = 'is_public'; }
            $fields[] = $f;
        }
        \App\Models\FormTemplate::save($id, Str::startCase($id), $fields);
        return true;
    }
}
