<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;

final class MentorAssignment
{
    public static function assign(string $mentorId, string $menteeId, ?string $by): bool
    {
        if (DB::fetchColumn('SELECT 1 FROM mentor_assignments WHERE mentor_id = ? AND mentee_id = ?', [$mentorId, $menteeId])) {
            return false;
        }
        DB::insert('mentor_assignments', [
            'mentor_id' => $mentorId,
            'mentee_id' => $menteeId,
            'assigned_by' => $by,
            'created_at' => now(),
        ]);
        return true;
    }

    public static function remove(string $mentorId, string $menteeId): void
    {
        DB::delete('mentor_assignments', 'mentor_id = ? AND mentee_id = ?', [$mentorId, $menteeId]);
    }

    public static function get(string $mentorId, string $menteeId): ?array
    {
        return DB::fetch('SELECT * FROM mentor_assignments WHERE mentor_id = ? AND mentee_id = ?', [$mentorId, $menteeId]);
    }

    public static function accept(string $mentorId, string $menteeId): void
    {
        DB::update('mentor_assignments', ['accepted_at' => now(), 'rejected_at' => null], 'mentor_id = ? AND mentee_id = ?', [$mentorId, $menteeId]);
    }

    public static function reject(string $mentorId, string $menteeId, string $reason): void
    {
        DB::update('mentor_assignments', ['rejected_at' => now(), 'reject_reason' => $reason], 'mentor_id = ? AND mentee_id = ?', [$mentorId, $menteeId]);
    }

    /** Mentor ids for a mentee, with acceptance flag. */
    public static function mentorsOf(string $menteeId): array
    {
        return DB::fetchAll(
            'SELECT a.*, u.first_name, u.last_name, u.email, u.mobile FROM mentor_assignments a JOIN users u ON u.id = a.mentor_id WHERE a.mentee_id = ? ORDER BY a.created_at',
            [$menteeId]
        );
    }

    public static function menteesOf(string $mentorId, bool $acceptedOnly = false): array
    {
        $sql = 'SELECT a.*, u.first_name, u.last_name, u.email FROM mentor_assignments a JOIN users u ON u.id = a.mentee_id WHERE a.mentor_id = ? AND a.rejected_at IS NULL';
        if ($acceptedOnly) {
            $sql .= ' AND a.accepted_at IS NOT NULL';
        }
        return DB::fetchAll($sql . ' ORDER BY u.first_name', [$mentorId]);
    }

    /** mentee_id => [assignments] for many mentees */
    public static function mapForMentees(array $menteeIds): array
    {
        if (!$menteeIds) {
            return [];
        }
        [$in, $p] = DB::in($menteeIds);
        $out = [];
        foreach (DB::fetchAll("SELECT a.*, u.first_name, u.last_name, u.email FROM mentor_assignments a JOIN users u ON u.id = a.mentor_id WHERE a.mentee_id IN $in", $p) as $r) {
            $out[$r['mentee_id']][] = $r;
        }
        return $out;
    }

    /** All active (not rejected) assignments, for the mentor report job. */
    public static function allActive(bool $acceptedOnly = true): array
    {
        $sql = 'SELECT a.* FROM mentor_assignments a JOIN users m ON m.id = a.mentor_id JOIN users n ON n.id = a.mentee_id WHERE a.rejected_at IS NULL AND m.status = ? AND n.status NOT IN (?, ?)';
        if ($acceptedOnly) {
            $sql .= ' AND a.accepted_at IS NOT NULL';
        }
        return DB::fetchAll($sql, ['approved', 'inactive', 'removed']);
    }
}
