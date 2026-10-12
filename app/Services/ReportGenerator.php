<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\MentorAssignment;
use App\Models\Report;
use App\Models\SubEvent;

final class ReportGenerator
{
    /** After an event ends, create one pending event report per attendee. */
    public static function eventReports(): int
    {
        $n = 0;
        foreach (SubEvent::endedWithoutReports() as $s) {
            foreach (SubEvent::attendance($s['id']) as $a) {
                if (Report::create('event_report', $s['id'], $a['user_id'])) {
                    $n++;
                }
            }
            SubEvent::markReported($s['id']);
        }
        return $n;
    }

    /** ISO week key used for weekly mentor reports, e.g. 2026-W41 */
    public static function weekKey(?int $time = null): string
    {
        return date('o-\WW', $time ?? time());
    }

    /** Weekly: one pending mentor report per accepted mentor/mentee pair. */
    public static function mentorReports(?string $weekKey = null): int
    {
        $weekKey ??= self::weekKey();
        $n = 0;
        foreach (MentorAssignment::allActive(true) as $a) {
            if (Report::create('mentor', $a['mentee_id'], $a['mentor_id'], $weekKey)) {
                $n++;
            }
        }
        return $n;
    }

    /** Create the first mentor report right after a mentor accepts a mentee. */
    public static function firstMentorReport(string $mentorId, string $menteeId): void
    {
        Report::create('mentor', $menteeId, $mentorId, self::weekKey());
    }

    /** When a volunteer registers attendance for an event that already ended, open their report immediately. */
    public static function lateAttendance(array $subEvent, string $userId): void
    {
        if (SubEvent::endTime($subEvent) <= time()) {
            Report::create('event_report', $subEvent['id'], $userId);
        }
    }
}
