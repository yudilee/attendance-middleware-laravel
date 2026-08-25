<?php

namespace App\Services;

use App\Models\PunchLog;
use App\Models\ShiftSchedule;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\AppConfig;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AttendanceProcessorService
{
    /**
     * Process a collection of raw punches for a single employee on a single day.
     * Evaluates chronological pairing, anti-bounce, machine tracking, and branch-aware lateness.
     */
    public function processDailyPunches(Collection $punches, ?Employee $employee = null): array
    {
        if ($punches->isEmpty()) {
            return [
                'has_records' => false,
                'first_in' => null,
                'last_out' => null,
                'work_duration' => '-',
                'work_minutes' => 0,
                'status' => 'absent',
                'is_late' => false,
                'late_minutes' => 0,
                'total_punches' => 0,
                'in_device' => null,
                'out_device' => null,
            ];
        }

        // 1. Sort punches chronologically
        $sorted = $punches->sortBy('timestamp')->values();

        // 2. Anti-bounce filter: collapse duplicate scans within 120 seconds
        $filtered = collect();
        foreach ($sorted as $p) {
            if ($filtered->isEmpty()) {
                $filtered->push($p);
            } else {
                $prev = $filtered->last();
                $diffSec = abs(Carbon::parse($p->timestamp)->diffInSeconds(Carbon::parse($prev->timestamp)));
                if ($diffSec >= 120) {
                    $filtered->push($p);
                }
            }
        }

        // 3. Resolve Shift & Branch operating hours
        $shift = $employee?->shiftSchedule 
            ?? $employee?->group?->branch?->shiftSchedule 
            ?? $filtered->first()?->branch?->shiftSchedule;

        $startTimeStr = $shift?->start_time ?? AppConfig::where('key', 'default_shift_start')->value('value') ?? '08:00';
        $graceMinutes = (int) ($shift?->grace_minutes ?? AppConfig::where('key', 'late_grace_period_minutes')->value('value') ?? 15);
        $endTimeStr = $shift?->end_time ?? '17:00';

        // 4. Chronological First-In and Last-Out Resolution
        $firstPunch = $filtered->first();
        $firstIn = Carbon::parse($firstPunch->timestamp);
        $lastOut = null;
        $lastPunch = null;

        if ($filtered->count() >= 2) {
            $potentialLast = $filtered->last();
            $potentialLastTime = Carbon::parse($potentialLast->timestamp);
            $diffMinutes = $firstIn->diffInMinutes($potentialLastTime);

            // Recognize as valid Clock Out if >= 30 mins after in OR occurs in afternoon/evening (>= 12:00)
            if ($diffMinutes >= 30 || $potentialLastTime->format('H:i') >= '12:00' || strtolower($potentialLast->punch_type ?? '') === 'out') {
                $lastOut = $potentialLastTime;
                $lastPunch = $potentialLast;
            }
        }

        // 5. Calculate Duration
        $workMinutes = 0;
        $workDuration = '-';
        if ($firstIn && $lastOut && $lastOut->gt($firstIn)) {
            $workMinutes = $firstIn->diffInMinutes($lastOut);
            $hrs = floor($workMinutes / 60);
            $mins = $workMinutes % 60;
            $workDuration = sprintf("%02dh %02dm", $hrs, $mins);
        }

        // 6. Calculate Lateness
        $lateThreshold = Carbon::createFromTimeString($startTimeStr)->addMinutes($graceMinutes)->format('H:i:s');
        $isLate = false;
        $lateMinutes = 0;

        if ($firstIn && $firstIn->format('H:i:s') > $lateThreshold) {
            $isLate = true;
            $shiftStartTime = Carbon::createFromTimeString($startTimeStr);
            $lateMinutes = max(0, $shiftStartTime->diffInMinutes(Carbon::createFromTimeString($firstIn->format('H:i:s'))));
        }

        // 7. Status Resolution
        $status = 'normal';
        if (!$firstIn || !$lastOut) {
            $status = 'incomplete';
        } elseif ($isLate) {
            $status = 'late';
        }

        return [
            'has_records' => true,
            'first_in' => $firstIn ? $firstIn->format('H:i:s') : null,
            'last_out' => $lastOut ? $lastOut->format('H:i:s') : null,
            'first_in_datetime' => $firstIn ? $firstIn->format('Y-m-d H:i:s') : null,
            'last_out_datetime' => $lastOut ? $lastOut->format('Y-m-d H:i:s') : null,
            'work_duration' => $workDuration,
            'work_minutes' => $workMinutes,
            'status' => $status,
            'is_late' => $isLate,
            'late_minutes' => $lateMinutes,
            'total_punches' => $filtered->count(),
            'in_device' => [
                'device_sn' => $firstPunch->device_sn ?? $firstPunch->device_uuid ?? 'N/A',
                'device_name' => $firstPunch->device_name ?? 'Primary Terminal',
                'branch_name' => $firstPunch->branch?->name ?? 'Default Branch',
                'source' => $firstPunch->punch_source ?? ($firstPunch->latitude ? 'mobile_app' : 'adms_fingerprint'),
            ],
            'out_device' => $lastPunch ? [
                'device_sn' => $lastPunch->device_sn ?? $lastPunch->device_uuid ?? 'N/A',
                'device_name' => $lastPunch->device_name ?? 'Primary Terminal',
                'branch_name' => $lastPunch->branch?->name ?? 'Default Branch',
                'source' => $lastPunch->punch_source ?? ($lastPunch->latitude ? 'mobile_app' : 'adms_fingerprint'),
            ] : null,
        ];
    }
}
