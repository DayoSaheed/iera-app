<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;

/** Idempotency and history for scheduled jobs. */
final class JobRun
{
    /** Try to claim a (job, period) slot. Returns the run id, or null if already run. */
    public static function claim(string $job, string $periodKey): ?int
    {
        try {
            DB::insert('job_runs', [
                'job' => $job,
                'period_key' => $periodKey,
                'started_at' => now(),
                'status' => 'running',
            ]);
            return DB::lastInsertId();
        } catch (\PDOException) {
            return null;
        }
    }

    public static function finish(int $id, string $status, ?string $message = null): void
    {
        DB::update('job_runs', ['finished_at' => now(), 'status' => $status, 'message' => $message ? substr($message, 0, 5000) : null], 'id = ?', [$id]);
    }

    public static function last(string $job): ?array
    {
        return DB::fetch('SELECT * FROM job_runs WHERE job = ? ORDER BY id DESC LIMIT 1', [$job]);
    }

    public static function recent(int $limit = 100): array
    {
        return DB::fetchAll("SELECT * FROM job_runs ORDER BY id DESC LIMIT $limit");
    }

    public static function purge(int $days = 60): void
    {
        DB::delete('job_runs', 'started_at < ?', [date('Y-m-d H:i:s', time() - $days * 86400)]);
    }
}
