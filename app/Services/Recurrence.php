<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Event;
use App\Models\SubEvent;

/**
 * Keeps the right number of upcoming occurrences (sub events) open for an event series.
 */
final class Recurrence
{
    /** Create missing future occurrences. Returns the number created. */
    public static function materialize(array $event): int
    {
        if ($event['status'] !== 'active') {
            return 0;
        }
        $created = 0;
        if ($event['event_type'] === 'single') {
            if (!SubEvent::existsAt($event['id'], $event['start_at'])) {
                self::spawn($event, $event['start_at']);
                $created++;
            }
            return $created;
        }
        $wanted = max(1, (int) $event['active_events']);
        $existing = SubEvent::futureCountForEvent($event['id']);
        if ($existing >= $wanted) {
            return 0;
        }
        foreach (self::nextDates($event, $wanted + 2) as $date) {
            if ($existing >= $wanted) {
                break;
            }
            if (!SubEvent::existsAt($event['id'], $date)) {
                self::spawn($event, $date);
                $created++;
                $existing++;
            }
        }
        return $created;
    }

    /** Upcoming occurrence datetimes (Y-m-d H:i:s) on or after now. */
    public static function nextDates(array $event, int $count): array
    {
        $start = new \DateTimeImmutable($event['start_at']);
        $now = new \DateTimeImmutable('now');
        $interval = match ($event['recurrence'] ?? 'weekly') {
            'monthly' => new \DateInterval('P1M'),
            'yearly' => new \DateInterval('P1Y'),
            default => new \DateInterval('P1W'),
        };
        $cursor = $start;
        $guard = 0;
        while ($cursor < $now && $guard++ < 2000) {
            $cursor = $cursor->add($interval);
        }
        $out = [];
        for ($i = 0; $i < $count; $i++) {
            $out[] = $cursor->format('Y-m-d H:i:s');
            $cursor = $cursor->add($interval);
        }
        return $out;
    }

    private static function spawn(array $event, string $startAt): void
    {
        SubEvent::create([
            'event_id' => $event['id'],
            'team_id' => $event['team_id'],
            'name' => $event['name'],
            'description' => $event['description'],
            'city' => $event['city'],
            'min_participants' => $event['min_participants'],
            'start_at' => $startAt,
            'event_length' => $event['event_length'],
            'status' => 'active',
        ]);
    }

    /** Push edited series details to all future occurrences (times are kept). */
    public static function propagate(array $event): void
    {
        foreach (SubEvent::futureForEvent($event['id']) as $s) {
            SubEvent::update($s['id'], [
                'name' => $event['name'],
                'description' => $event['description'],
                'city' => $event['city'],
                'min_participants' => $event['min_participants'],
                'event_length' => $event['event_length'],
            ]);
        }
    }

    /** Run for every active event (cron). */
    public static function materializeAll(): int
    {
        $n = 0;
        foreach (\App\Core\DB::fetchAll("SELECT * FROM events WHERE status = 'active'") as $e) {
            $n += self::materialize($e);
        }
        return $n;
    }
}
