<?php

namespace App\Domains\HRMS\Helpers;

use App\Domains\HRMS\Models\LeaveRequest;
use App\Domains\HRMS\Models\WfhRequest;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class SessionConflictChecker
{
    /**
     * Checks if a new date+session request conflicts with any existing Leave or WFH request.
     *
     * Returns null if no conflict, or a human-readable error string if conflict found.
     *
     * Session conflict matrix for the same date:
     *  - full_day   vs anything   → conflict
     *  - first_half vs first_half → conflict
     *  - second_half vs second_half → conflict
     *  - first_half vs second_half → NO conflict (complementary)
     *  - second_half vs first_half → NO conflict (complementary)
     */
    public static function hasConflict(
        int    $employeeId,
        Carbon $newStart,
        Carbon $newEnd,
        string $newStartType,
        string $newEndType,
        ?int   $excludeLeaveId = null,
        ?int   $excludeWfhId   = null
    ): ?string {
        // Expand the new request into {date → session} pairs
        $newSlots = self::expandToSlots($newStart, $newEnd, $newStartType, $newEndType);

        // --- Check existing Leave requests ---
        $leaveQuery = LeaveRequest::where('employee_id', $employeeId)
            ->whereIn('status', ['pending', 'approved', 'cancellation_requested']);

        if ($excludeLeaveId) {
            $leaveQuery->where('id', '!=', $excludeLeaveId);
        }

        foreach ($leaveQuery->get() as $leave) {
            $existingSlots = self::expandToSlots(
                Carbon::parse($leave->start_date),
                Carbon::parse($leave->end_date),
                $leave->start_date_type ?? 'full_day',
                $leave->end_date_type   ?? 'full_day'
            );

            $conflict = self::findConflictingSlot($newSlots, $existingSlots);
            if ($conflict) {
                return "You already have a Leave request ({$conflict['existing_label']}) on {$conflict['date']}. Cannot book {$conflict['new_label']} on the same date.";
            }
        }

        // --- Check existing WFH requests ---
        $wfhQuery = WfhRequest::where('employee_id', $employeeId)
            ->whereIn('status', ['pending', 'approved', 'cancellation_requested']);

        if ($excludeWfhId) {
            $wfhQuery->where('id', '!=', $excludeWfhId);
        }

        foreach ($wfhQuery->get() as $wfh) {
            $existingSlots = self::expandToSlots(
                Carbon::parse($wfh->start_date),
                Carbon::parse($wfh->end_date),
                $wfh->start_date_type ?? 'full_day',
                $wfh->end_date_type   ?? 'full_day'
            );

            $conflict = self::findConflictingSlot($newSlots, $existingSlots);
            if ($conflict) {
                return "You already have a WFH request ({$conflict['existing_label']}) on {$conflict['date']}. Cannot book {$conflict['new_label']} on the same date.";
            }
        }

        return null; // No conflict
    }

    /**
     * Expand a date range into an array of ['date' => Carbon, 'session' => string].
     * Start date uses start_date_type, end date uses end_date_type,
     * and all dates in between are full_day.
     */
    private static function expandToSlots(
        Carbon $start,
        Carbon $end,
        string $startType,
        string $endType
    ): array {
        $slots = [];

        if ($start->isSameDay($end)) {
            // Single day — use start type (end type is irrelevant)
            $slots[] = [
                'date'    => $start->copy(),
                'session' => $startType,
            ];
            return $slots;
        }

        // Multi-day: iterate each day
        $period = CarbonPeriod::create($start, $end);
        foreach ($period as $date) {
            if ($date->isSameDay($start)) {
                $session = $startType;
            } elseif ($date->isSameDay($end)) {
                $session = $endType;
            } else {
                $session = 'full_day'; // Middle days are always full day
            }
            $slots[] = [
                'date'    => $date->copy(),
                'session' => $session,
            ];
        }

        return $slots;
    }

    /**
     * Find first conflicting slot between new and existing slot arrays.
     * Returns conflict info or null.
     */
    private static function findConflictingSlot(array $newSlots, array $existingSlots): ?array
    {
        foreach ($newSlots as $newSlot) {
            foreach ($existingSlots as $existingSlot) {
                if (!$newSlot['date']->isSameDay($existingSlot['date'])) {
                    continue;
                }

                if (self::sessionsConflict($newSlot['session'], $existingSlot['session'])) {
                    return [
                        'date'           => $newSlot['date']->format('d M Y'),
                        'new_label'      => self::sessionLabel($newSlot['session']),
                        'existing_label' => self::sessionLabel($existingSlot['session']),
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Returns true if two sessions conflict on the same date.
     *
     * first_half  + second_half = complementary → no conflict
     * second_half + first_half  = complementary → no conflict
     * everything else           = conflict
     */
    private static function sessionsConflict(string $new, string $existing): bool
    {
        // The only non-conflicting combination is first_half ↔ second_half
        if ($new === 'first_half' && $existing === 'second_half') {
            return false;
        }
        if ($new === 'second_half' && $existing === 'first_half') {
            return false;
        }
        return true;
    }

    private static function sessionLabel(string $session): string
    {
        return match($session) {
            'first_half'  => 'First Half',
            'second_half' => 'Second Half',
            default       => 'Full Day',
        };
    }
}
