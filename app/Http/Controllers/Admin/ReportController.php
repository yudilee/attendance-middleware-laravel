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
        $viewMode = $request->query('view_mode', 'raw'); // 'raw' | 'daily_summary'
        $startDate = $request->query('start_date', Carbon::today('Asia/Jakarta')->subDays(7)->format('Y-m-d'));
        $endDate = $request->query('end_date', Carbon::today('Asia/Jakarta')->format('Y-m-d'));
        $department = $request->query('department');
        $employeeSearch = $request->query('employee_search');

        $driver = DB::connection()->getDriverName();
        $likeOp = $driver === 'pgsql' ? 'ilike' : 'like';

        if ($viewMode === 'daily_summary') {
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

            // For each distinct date + employee, fetch all punches and process via AttendanceProcessorService
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

            $punchesData = null;
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
            $summariesData = null;
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
            'departments' => $departments,
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'department' => $department ?? 'all',
                'employee_search' => $employeeSearch ?? '',
                'view_mode' => $viewMode,
            ],
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $startDate = $request->query('start_date', Carbon::today('Asia/Jakarta')->subDays(7)->format('Y-m-d'));
        $endDate = $request->query('end_date', Carbon::today('Asia/Jakarta')->format('Y-m-d'));
        $department = $request->query('department');
        $viewMode = $request->query('view_mode', 'raw');

        $fileName = "attendance_{$viewMode}_{$startDate}_to_{$endDate}.csv";

        return response()->streamDownload(function () use ($startDate, $endDate, $department, $viewMode) {
            $handle = fopen('php://output', 'w');

            $graceMinutes = (int) (AppConfig::where('key', 'late_grace_period_minutes')->value('value') ?? 15);
            $shiftStart = AppConfig::where('key', 'default_shift_start')->value('value') ?? '08:00';
            $lateThreshold = Carbon::createFromTimeString($shiftStart)->addMinutes($graceMinutes)->format('H:i');

            if ($viewMode === 'daily_summary') {
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
