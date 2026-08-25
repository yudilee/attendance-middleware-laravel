<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\PunchLog;
use App\Models\Employee;
use App\Models\AppConfig;
use Carbon\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function __construct(
        protected \App\Services\AttendanceProcessorService $attendanceProcessor
    ) {}

    public function index(Request $request): Response
    {
        $viewMode = $request->query('view_mode', 'catering'); // 'catering' | 'daily_summary' | 'raw'
        $startDate = $request->query('start_date', Carbon::today('Asia/Jakarta')->format('Y-m-d'));
        $endDate = $request->query('end_date', Carbon::today('Asia/Jakarta')->format('Y-m-d'));
        $targetDate = $startDate; // For single-day catering view
        $cutoffTime = $request->query('cutoff_time', '10:00');
        $branchFilter = $request->query('branch_id');
        $department = $request->query('department');
        $employeeSearch = $request->query('employee_search');
        $cateringStatus = $request->query('catering_status', 'all'); // 'all' | 'eligible' | 'on_leave' | 'not_in'

        $driver = DB::connection()->getDriverName();
        $likeOp = $driver === 'pgsql' ? 'ilike' : 'like';

        $branches = \App\Models\Branch::where('is_active', true)->orderBy('name')->get(['id', 'name']);

        $punchesData = null;
        $summariesData = null;
        $cateringData = null;

        if ($viewMode === 'catering') {
            // -------------------------------------------------------------
            // 🍱 DAILY CATERING & LUNCH ORDER REPORT
            // -------------------------------------------------------------
            $employeesQuery = Employee::with(['group.branch'])
                ->where('is_deleted', false)
                ->orderBy('full_name', 'asc');

            if ($department && $department !== 'all') {
                $employeesQuery->where('department', $department);
            }

            if ($employeeSearch) {
                $employeesQuery->where(function ($q) use ($employeeSearch, $likeOp) {
                    $q->where('employee_id', $likeOp, "%{$employeeSearch}%")
                      ->orWhere('full_name', $likeOp, "%{$employeeSearch}%");
                });
            }

            $allEmployees = $employeesQuery->get();

            // Fetch all punches for the target date
            $dayPunches = PunchLog::with('branch')
                ->whereDate('timestamp', $targetDate)
                ->orderBy('timestamp', 'asc')
                ->get()
                ->groupBy('employee_id');

            // Fetch all approved leaves for the target date
            $dayLeaves = \App\Models\LeaveRequest::where('status', 'approved')
                ->whereDate('start_date', '<=', $targetDate)
                ->whereDate('end_date', '>=', $targetDate)
                ->get()
                ->keyBy('employee_id');

            $branchPortions = [];
            $deptPortions = [];
            $totalEligible = 0;
            $totalOnLeave = 0;
            $totalNotIn = 0;

            $roster = [];

            foreach ($allEmployees as $emp) {
                $empPunches = $dayPunches->get($emp->employee_id, collect());
                $firstPunch = $empPunches->first();
                $leave = $dayLeaves->get($emp->employee_id);

                // Determine branch
                $branchName = $firstPunch?->branch?->name
                    ?? $emp->group?->branch?->name
                    ?? 'Default Branch';
                $branchId = $firstPunch?->branch_id
                    ?? $emp->group?->branch_id;

                // Branch filter check
                if ($branchFilter && $branchFilter !== 'all' && (int)$branchFilter !== (int)$branchId) {
                    continue;
                }

                $clockInTime = $firstPunch ? $firstPunch->timestamp->format('H:i:s') : null;
                $dept = $emp->department ?: 'General';

                $status = 'not_in';
                $statusLabel = 'Not Clocked In';
                $isEligible = false;

                if ($leave) {
                    $status = 'on_leave';
                    $statusLabel = "On Leave ({$leave->leave_type})";
                    $totalOnLeave++;
                } elseif ($firstPunch) {
                    $punchTimeStr = $firstPunch->timestamp->format('H:i');
                    $isLateCutoff = $punchTimeStr > $cutoffTime;

                    $status = 'eligible';
                    $statusLabel = $isLateCutoff ? "Clocked In ({$clockInTime} - Late)" : "Clocked In ({$clockInTime})";
                    $isEligible = true;
                    $totalEligible++;

                    // Tally branch and department portions
                    $branchPortions[$branchName] = ($branchPortions[$branchName] ?? 0) + 1;
                    $deptPortions[$dept] = ($deptPortions[$dept] ?? 0) + 1;
                } else {
                    $totalNotIn++;
                }

                if ($cateringStatus !== 'all' && $status !== $cateringStatus) {
                    continue;
                }

                $roster[] = [
                    'employee_id' => $emp->employee_id,
                    'full_name' => $emp->full_name,
                    'department' => $dept,
                    'branch_name' => $branchName,
                    'clock_in_time' => $clockInTime ?? '-',
                    'in_source' => $firstPunch ? ($firstPunch->punch_source ?? ($firstPunch->latitude ? 'Mobile GPS' : 'Fingerprint')) : '-',
                    'device_name' => $firstPunch?->device_name ?? '-',
                    'status' => $status,
                    'status_label' => $statusLabel,
                    'is_eligible' => $isEligible,
                ];
            }

            // Convert branch & dept breakdowns to formatted array
            ksort($branchPortions);
            ksort($deptPortions);

            $branchBreakdown = [];
            foreach ($branchPortions as $bName => $cnt) {
                $branchBreakdown[] = ['name' => $bName, 'count' => $cnt];
            }

            $deptBreakdown = [];
            foreach ($deptPortions as $dName => $cnt) {
                $deptBreakdown[] = ['name' => $dName, 'count' => $cnt];
            }

            // Manual pagination for roster
            $page = (int)$request->query('page', 1);
            $perPage = 30;
            $rosterCollection = collect($roster);
            $paginatedRoster = new \Illuminate\Pagination\LengthAwarePaginator(
                $rosterCollection->forPage($page, $perPage)->values(),
                $rosterCollection->count(),
                $perPage,
                $page,
                ['path' => $request->url(), 'query' => $request->query()]
            );

            $cateringData = [
                'target_date' => $targetDate,
                'cutoff_time' => $cutoffTime,
                'total_eligible' => $totalEligible,
                'total_on_leave' => $totalOnLeave,
                'total_not_in' => $totalNotIn,
                'total_headcount' => count($allEmployees),
                'branch_breakdown' => $branchBreakdown,
                'dept_breakdown' => $deptBreakdown,
                'roster' => $paginatedRoster,
            ];
        } elseif ($viewMode === 'daily_summary') {
            // Fetch distinct employee + date groups
            $dateCol = "DATE(punch_logs.timestamp)";
            $groupsQuery = DB::table('punch_logs')
                ->join('employees', 'punch_logs.employee_id', '=', 'employees.employee_id')
                ->whereDate('punch_logs.timestamp', '>=', $startDate)
                ->whereDate('punch_logs.timestamp', '<=', $endDate);

            if ($department && $department !== 'all') {
                $groupsQuery->where('employees.department', $department);
            }

            if ($employeeSearch) {
                $groupsQuery->where(function ($q) use ($employeeSearch, $likeOp) {
                    $q->where('employees.employee_id', $likeOp, "%{$employeeSearch}%")
                      ->orWhere('employees.full_name', $likeOp, "%{$employeeSearch}%");
                });
            }

            $distinctDays = $groupsQuery->select(
                DB::raw("{$dateCol} as punch_date"),
                'employees.employee_id',
                'employees.full_name',
                'employees.department'
            )
            ->groupBy(DB::raw("{$dateCol}"), 'employees.employee_id', 'employees.full_name', 'employees.department')
            ->orderBy(DB::raw("{$dateCol}"), 'desc')
            ->orderBy('employees.full_name', 'asc')
            ->paginate(25);

            $reports = $distinctDays->through(function ($r) {
                $punches = PunchLog::with(['branch', 'employee.shiftSchedule', 'employee.group.branch.shiftSchedule'])
                    ->where('employee_id', $r->employee_id)
                    ->whereDate('timestamp', $r->punch_date)
                    ->orderBy('timestamp', 'asc')
                    ->get();

                $employee = $punches->first()?->employee ?? Employee::where('employee_id', $r->employee_id)->first();
                $processed = $this->attendanceProcessor->processDailyPunches($punches, $employee);

                return [
                    'date' => $r->punch_date,
                    'employee_id' => $r->employee_id,
                    'employee_name' => $r->full_name,
                    'department' => $r->department ?? '-',
                    'first_in' => $processed['first_in'] ?? '-',
                    'last_out' => $processed['last_out'] ?? '-',
                    'work_hours' => $processed['work_duration'],
                    'status' => $processed['status'],
                    'total_punches' => $processed['total_punches'],
                    'in_device' => $processed['in_device'],
                    'out_device' => $processed['out_device'],
                ];
            });

            $summariesData = $reports;
        } else {
            // Raw Punch Logs View
            $query = PunchLog::with(['employee', 'branch'])
                ->whereDate('timestamp', '>=', $startDate)
                ->whereDate('timestamp', '<=', $endDate)
                ->orderBy('timestamp', 'desc');

            if ($department && $department !== 'all') {
                $query->whereHas('employee', function ($q) use ($department) {
                    $q->where('department', $department);
                });
            }

            if ($employeeSearch) {
                $query->where(function ($q) use ($employeeSearch, $likeOp) {
                    $q->where('employee_id', $likeOp, "%{$employeeSearch}%")
                      ->orWhereHas('employee', function ($eq) use ($employeeSearch, $likeOp) {
                          $eq->where('full_name', $likeOp, "%{$employeeSearch}%");
                      });
                });
            }

            $reports = $query->paginate(25)->through(function ($p) {
                return [
                    'id' => $p->id,
                    'employee_id' => $p->employee_id,
                    'employee_name' => $p->employee?->full_name ?? 'Unknown',
                    'department' => $p->employee?->department ?? '-',
                    'punch_type' => $p->punch_type,
                    'timestamp' => $p->timestamp->format('Y-m-d H:i:s'),
                    'latitude' => $p->latitude,
                    'longitude' => $p->longitude,
                    'device_sn' => $p->device_sn ?? $p->device_uuid ?? 'N/A',
                    'device_name' => $p->device_name ?? ($p->latitude ? 'Mobile App' : 'Biometric Terminal'),
                    'branch_name' => $p->branch?->name ?? 'Default Branch',
                    'punch_source' => $p->punch_source ?? ($p->latitude ? 'mobile_app' : 'adms_fingerprint'),
                    'biometric_verified' => $p->biometric_verified,
                    'adms_status' => $p->adms_status,
                ];
            });

            $punchesData = $reports;
        }

        $departments = Employee::where('is_deleted', false)
            ->whereNotNull('department')
            ->distinct()
            ->orderBy('department')
            ->pluck('department');

        return Inertia::render('Admin/Reports/Index', [
            'view_mode' => $viewMode,
            'punches' => $punchesData,
            'summaries' => $summariesData,
            'catering' => $cateringData,
            'departments' => $departments,
            'branches' => $branches,
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'cutoff_time' => $cutoffTime,
                'branch_id' => $branchFilter ?? 'all',
                'department' => $department ?? 'all',
                'employee_search' => $employeeSearch ?? '',
                'catering_status' => $cateringStatus,
                'view_mode' => $viewMode,
            ],
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $startDate = $request->query('start_date', Carbon::today('Asia/Jakarta')->format('Y-m-d'));
        $endDate = $request->query('end_date', Carbon::today('Asia/Jakarta')->format('Y-m-d'));
        $cutoffTime = $request->query('cutoff_time', '10:00');
        $department = $request->query('department');
        $branchFilter = $request->query('branch_id');
        $viewMode = $request->query('view_mode', 'catering');

        $fileName = "attendance_{$viewMode}_{$startDate}.csv";

        return response()->streamDownload(function () use ($startDate, $endDate, $cutoffTime, $department, $branchFilter, $viewMode) {
            $handle = fopen('php://output', 'w');

            if ($viewMode === 'catering') {
                // 🍱 Catering Lunch Export
                fputcsv($handle, ['--- DAILY CATERING & LUNCH MANIFEST ---']);
                fputcsv($handle, ['Report Date:', $startDate, 'Morning Cutoff Time:', $cutoffTime]);
                fputcsv($handle, []);

                $allEmployees = Employee::with(['group.branch'])
                    ->where('is_deleted', false)
                    ->orderBy('department', 'asc')
                    ->orderBy('full_name', 'asc')
                    ->get();

                $dayPunches = PunchLog::with('branch')
                    ->whereDate('timestamp', $startDate)
                    ->orderBy('timestamp', 'asc')
                    ->get()
                    ->groupBy('employee_id');

                $dayLeaves = \App\Models\LeaveRequest::where('status', 'approved')
                    ->whereDate('start_date', '<=', $startDate)
                    ->whereDate('end_date', '>=', $startDate)
                    ->get()
                    ->keyBy('employee_id');

                $branchPortions = [];
                $rows = [];

                foreach ($allEmployees as $emp) {
                    $empPunches = $dayPunches->get($emp->employee_id, collect());
                    $firstPunch = $empPunches->first();
                    $leave = $dayLeaves->get($emp->employee_id);

                    $branchName = $firstPunch?->branch?->name
                        ?? $emp->group?->branch?->name
                        ?? 'Default Branch';
                    $branchId = $firstPunch?->branch_id
                        ?? $emp->group?->branch_id;

                    if ($branchFilter && $branchFilter !== 'all' && (int)$branchFilter !== (int)$branchId) {
                        continue;
                    }

                    $clockInTime = $firstPunch ? $firstPunch->timestamp->format('H:i:s') : '-';
                    $dept = $emp->department ?: 'General';

                    if ($leave) {
                        $lunchDecision = 'NO (On Leave: ' . $leave->leave_type . ')';
                    } elseif ($firstPunch) {
                        $lunchDecision = 'YES (Present - Order Portion)';
                        $branchPortions[$branchName] = ($branchPortions[$branchName] ?? 0) + 1;
                    } else {
                        $lunchDecision = 'NO (Not Clocked In)';
                    }

                    $rows[] = [
                        $emp->employee_id,
                        $emp->full_name,
                        $dept,
                        $branchName,
                        $clockInTime,
                        $firstPunch ? ($firstPunch->punch_source ?? ($firstPunch->latitude ? 'Mobile GPS' : 'Fingerprint')) : '-',
                        $firstPunch?->device_name ?? '-',
                        $lunchDecision,
                    ];
                }

                // Summary by Branch
                fputcsv($handle, ['BRANCH', 'TOTAL LUNCH PORTIONS NEEDED']);
                $totalAll = 0;
                foreach ($branchPortions as $bName => $cnt) {
                    fputcsv($handle, [$bName, $cnt]);
                    $totalAll += $cnt;
                }
                fputcsv($handle, ['TOTAL ALL BRANCHES', $totalAll]);
                fputcsv($handle, []);

                // Detailed Manifest
                fputcsv($handle, ['PIN', 'Employee Name', 'Department', 'Branch', 'Clock In Time', 'Punch Source', 'Machine / Terminal', 'Catering Lunch Order']);
                foreach ($rows as $r) {
                    fputcsv($handle, $r);
                }
            } elseif ($viewMode === 'daily_summary') {
                fputcsv($handle, ['Date', 'PIN', 'Name', 'Department', 'Clock In', 'In Machine / Branch', 'Clock Out', 'Out Machine / Branch', 'Work Duration', 'Status']);

                $groupsQuery = DB::table('punch_logs')
                    ->join('employees', 'punch_logs.employee_id', '=', 'employees.employee_id')
                    ->whereDate('punch_logs.timestamp', '>=', $startDate)
                    ->whereDate('punch_logs.timestamp', '<=', $endDate);

                if ($department && $department !== 'all') {
                    $groupsQuery->where('employees.department', $department);
                }

                $dateCol = "DATE(punch_logs.timestamp)";
                $groupsQuery->select(
                    DB::raw("{$dateCol} as punch_date"),
                    'employees.employee_id',
                    'employees.full_name',
                    'employees.department'
                )
                ->groupBy(DB::raw("{$dateCol}"), 'employees.employee_id', 'employees.full_name', 'employees.department')
                ->orderBy(DB::raw("{$dateCol}"), 'desc')
                ->orderBy('employees.full_name', 'asc');

                $groupsQuery->chunk(300, function ($rows) use ($handle) {
                    foreach ($rows as $r) {
                        $punches = PunchLog::with(['branch', 'employee.shiftSchedule', 'employee.group.branch.shiftSchedule'])
                            ->where('employee_id', $r->employee_id)
                            ->whereDate('timestamp', $r->punch_date)
                            ->orderBy('timestamp', 'asc')
                            ->get();

                        $employee = $punches->first()?->employee ?? Employee::where('employee_id', $r->employee_id)->first();
                        $processed = $this->attendanceProcessor->processDailyPunches($punches, $employee);

                        $inMachine = $processed['in_device'] ? "{$processed['in_device']['device_name']} ({$processed['in_device']['branch_name']})" : '-';
                        $outMachine = $processed['out_device'] ? "{$processed['out_device']['device_name']} ({$processed['out_device']['branch_name']})" : '-';

                        fputcsv($handle, [
                            $r->punch_date,
                            $r->employee_id,
                            $r->full_name,
                            $r->department ?? '-',
                            $processed['first_in'] ?? '-',
                            $inMachine,
                            $processed['last_out'] ?? '-',
                            $outMachine,
                            $processed['work_duration'],
                            ucfirst($processed['status']),
                        ]);
                    }
                });
            } else {
                fputcsv($handle, ['ID', 'PIN', 'Name', 'Department', 'Type', 'Date & Time', 'Device SN', 'Machine / Source', 'Branch', 'Latitude', 'Longitude', 'Biometric', 'ADMS Status']);

                $query = PunchLog::with(['employee', 'branch'])
                    ->whereDate('timestamp', '>=', $startDate)
                    ->whereDate('timestamp', '<=', $endDate)
                    ->orderBy('timestamp', 'desc');

                if ($department && $department !== 'all') {
                    $query->whereHas('employee', function ($q) use ($department) {
                        $q->where('department', $department);
                    });
                }

                $query->chunk(500, function ($rows) use ($handle) {
                    foreach ($rows as $r) {
                        fputcsv($handle, [
                            $r->id,
                            $r->employee_id,
                            $r->employee?->full_name ?? '',
                            $r->employee?->department ?? '',
                            $r->punch_type,
                            $r->timestamp->format('Y-m-d H:i:s'),
                            $r->device_sn ?? $r->device_uuid ?? 'N/A',
                            $r->device_name ?? ($r->latitude ? 'Mobile App' : 'Biometric Terminal'),
                            $r->branch?->name ?? 'Default Branch',
                            $r->latitude,
                            $r->longitude,
                            $r->biometric_verified ? 'Yes' : 'No',
                            $r->adms_status,
                        ]);
                    }
                });
            }

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
        ]);
    }
}
