<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\Employee;
use App\Models\DeviceBinding;
use App\Models\PunchLog;
use App\Models\Branch;
use App\Models\AppConfig;
use App\Services\AdmsService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $today = Carbon::today('Asia/Jakarta');

        $totalEmployees = Employee::where('is_deleted', false)->count();
        $activeDevices = DeviceBinding::where('is_active', true)->where('registration_status', 'approved')->count();
        $pendingApprovals = DeviceBinding::where('registration_status', 'pending_approval')->count();
        
        $todayPunchesIn = PunchLog::whereDate('timestamp', $today)->where('punch_type', 'In')->count();
        $todayPunchesOut = PunchLog::whereDate('timestamp', $today)->where('punch_type', 'Out')->count();

        // Unique employees who punched In today
        $uniqueEmployeesInToday = PunchLog::whereDate('timestamp', $today)
            ->where('punch_type', 'In')
            ->distinct('employee_id')
            ->count('employee_id');

        // Dynamic late arrival threshold
        $graceMinutes = (int) (AppConfig::where('key', 'late_grace_period_minutes')->value('value') ?? 15);
        $shiftStart = AppConfig::where('key', 'default_shift_start')->value('value') ?? '08:00';
        $lateThreshold = Carbon::createFromTimeString($shiftStart)->addMinutes($graceMinutes)->format('H:i:s');

        // Late arrivals (punched in after shift start + grace period)
        $todayLateCount = PunchLog::whereDate('timestamp', $today)
            ->where('punch_type', 'In')
            ->whereTime('timestamp', '>', $lateThreshold)
            ->distinct('employee_id')
            ->count('employee_id');

        $todayAbsentCount = max(0, $totalEmployees - $uniqueEmployeesInToday);
        $attendanceRate = $totalEmployees > 0 ? round(($uniqueEmployeesInToday / $totalEmployees) * 100, 1) : 0;

        // 7-day Attendance Trend
        $trendDates = [];
        $trendIn = [];
        $trendOut = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::today('Asia/Jakarta')->subDays($i);
            $dateStr = $date->format('Y-m-d');
            $trendDates[] = $date->format('D, M j');

            $inCount = PunchLog::whereDate('timestamp', $dateStr)->where('punch_type', 'In')->count();
            $outCount = PunchLog::whereDate('timestamp', $dateStr)->where('punch_type', 'Out')->count();

            $trendIn[] = $inCount;
            $trendOut[] = $outCount;
        }

        // Top Departments attendance breakdown today
        $deptBreakdown = DB::table('employees')
            ->where('is_deleted', false)
            ->whereNotNull('department')
            ->where('department', '!=', '')
            ->select('department', DB::raw('count(*) as total'))
            ->groupBy('department')
            ->orderByDesc('total')
            ->limit(6)
            ->get()
            ->map(function ($dept) use ($today) {
                $checkedIn = PunchLog::join('employees', 'punch_logs.employee_id', '=', 'employees.employee_id')
                    ->where('employees.department', $dept->department)
                    ->whereDate('punch_logs.timestamp', $today)
                    ->where('punch_logs.punch_type', 'In')
                    ->distinct('punch_logs.employee_id')
                    ->count('punch_logs.employee_id');

                $pct = $dept->total > 0 ? round(($checkedIn / $dept->total) * 100) : 0;

                return [
                    'department' => $dept->department,
                    'total' => $dept->total,
                    'checked_in' => $checkedIn,
                    'percentage' => $pct,
                ];
            });

        $recentPunches = PunchLog::with('employee')
            ->orderBy('timestamp', 'desc')
            ->limit(15)
            ->get()
            ->map(function ($p) {
                return [
                    'id' => $p->id,
                    'employee_id' => $p->employee_id,
                    'employee_name' => $p->employee?->full_name ?? "Employee {$p->employee_id}",
                    'department' => $p->employee?->department ?? '-',
                    'punch_type' => $p->punch_type,
                    'timestamp' => $p->timestamp->format('Y-m-d H:i:s'),
                    'latitude' => $p->latitude,
                    'longitude' => $p->longitude,
                    'adms_status' => $p->adms_status,
                    'biometric_verified' => $p->biometric_verified,
                ];
            });

        $lastSyncTime = AppConfig::where('key', 'last_adms_sync_time')->value('value');
        $lastSyncStatus = AppConfig::where('key', 'last_adms_sync_status')->value('value');
        $autoSyncEnabled = AppConfig::where('key', 'adms_auto_sync_enabled')->value('value');

        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'total_employees' => $totalEmployees,
                'active_devices' => $activeDevices,
                'pending_approvals' => $pendingApprovals,
                'today_punches_in' => $todayPunchesIn,
                'today_punches_out' => $todayPunchesOut,
                'today_unique_in' => $uniqueEmployeesInToday,
                'today_late_count' => $todayLateCount,
                'today_absent_count' => $todayAbsentCount,
                'attendance_rate' => $attendanceRate,
                'late_threshold_time' => substr($lateThreshold, 0, 5),
                'last_sync_time' => $lastSyncTime ?? 'Never',
                'last_sync_status' => $lastSyncStatus ?? 'idle',
                'auto_sync_enabled' => $autoSyncEnabled === 'true',
            ],
            'charts' => [
                'trend_dates' => $trendDates,
                'trend_in' => $trendIn,
                'trend_out' => $trendOut,
                'dept_breakdown' => $deptBreakdown,
            ],
            'recent_punches' => $recentPunches,
        ]);
    }

    public function syncAdms(AdmsService $admsService)
    {
        $punchResult = $admsService->syncPendingPunches();
        $empResult = $admsService->syncEmployees();

        $messages = [];
        if ($punchResult['count'] > 0) {
            $messages[] = $punchResult['message'];
        }
        $messages[] = $empResult['message'];

        return back()->with('success', implode(' | ', $messages));
    }

    public function syncAdmsPushNames(AdmsService $admsService)
    {
        $result = $admsService->syncAllEmployeesToAdms();

        $message = $result['message'] ?? 'Sync completed.';
        if ($result['success']) {
            return back()->with('success', $message);
        }

        return back()->with('error', $message);
    }
}
