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
            // Daily Summary View (Grouped by employee & date)
            $query = PunchLog::join('employees', 'punch_logs.employee_id', '=', 'employees.employee_id')
                ->whereDate('punch_logs.timestamp', '>=', $startDate)
                ->whereDate('punch_logs.timestamp', '<=', $endDate);

            if ($department && $department !== 'all') {
                $query->where('employees.department', $department);
            }

            if ($employeeSearch) {
                $query->where(function ($q) use ($employeeSearch, $likeOp) {
                    $q->where('employees.employee_id', $likeOp, "%{$employeeSearch}%")
                      ->orWhere('employees.full_name', $likeOp, "%{$employeeSearch}%");
                });
            }

            $dateCol = $driver === 'pgsql' ? "DATE(punch_logs.timestamp)" : "DATE(punch_logs.timestamp)";

            $summaryQuery = $query->select(
                DB::raw("{$dateCol} as punch_date"),
                'employees.employee_id',
                'employees.full_name',
                'employees.department',
                DB::raw("MIN(CASE WHEN punch_logs.punch_type = 'In' THEN punch_logs.timestamp END) as first_in"),
                DB::raw("MAX(CASE WHEN punch_logs.punch_type = 'Out' THEN punch_logs.timestamp END) as last_out"),
                DB::raw("COUNT(punch_logs.id) as total_punches")
            )
            ->groupBy(DB::raw("{$dateCol}"), 'employees.employee_id', 'employees.full_name', 'employees.department')
            ->orderBy(DB::raw("{$dateCol}"), 'desc')
            ->orderBy('employees.full_name', 'asc');

            $graceMinutes = (int) (AppConfig::where('key', 'late_grace_period_minutes')->value('value') ?? 15);
            $shiftStart = AppConfig::where('key', 'default_shift_start')->value('value') ?? '08:00';
            $lateThreshold = Carbon::createFromTimeString($shiftStart)->addMinutes($graceMinutes)->format('H:i');

            $reports = $summaryQuery->paginate(25)->through(function ($r) use ($lateThreshold) {
                $firstIn = $r->first_in ? Carbon::parse($r->first_in) : null;
                $lastOut = $r->last_out ? Carbon::parse($r->last_out) : null;

                $workHours = null;
                if ($firstIn && $lastOut && $lastOut->gt($firstIn)) {
                    $minutes = $firstIn->diffInMinutes($lastOut);
                    $hrs = floor($minutes / 60);
                    $mins = $minutes % 60;
                    $workHours = sprintf("%02dh %02dm", $hrs, $mins);
                }

                $status = 'normal';
                if ($firstIn && $firstIn->format('H:i') > $lateThreshold) {
                    $status = 'late';
                } elseif (!$firstIn || !$lastOut) {
                    $status = 'incomplete';
                }

                return [
                    'date' => $r->punch_date,
                    'employee_id' => $r->employee_id,
                    'employee_name' => $r->full_name,
                    'department' => $r->department ?? '-',
                    'first_in' => $firstIn ? $firstIn->format('H:i:s') : '-',
                    'last_out' => $lastOut ? $lastOut->format('H:i:s') : '-',
                    'work_hours' => $workHours ?? '-',
                    'status' => $status,
                    'total_punches' => $r->total_punches,
                ];
            });

            $punchesData = null;
            $summariesData = $reports;
        } else {
            // Raw Punch Logs View
            $query = PunchLog::with('employee')
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
                fputcsv($handle, ['Date', 'PIN', 'Name', 'Department', 'Clock In', 'Clock Out', 'Work Duration', 'Status']);

                $query = PunchLog::join('employees', 'punch_logs.employee_id', '=', 'employees.employee_id')
                    ->whereDate('punch_logs.timestamp', '>=', $startDate)
                    ->whereDate('punch_logs.timestamp', '<=', $endDate);

                if ($department && $department !== 'all') {
                    $query->where('employees.department', $department);
                }

                $query->select(
                    DB::raw("DATE(punch_logs.timestamp) as punch_date"),
                    'employees.employee_id',
                    'employees.full_name',
                    'employees.department',
                    DB::raw("MIN(CASE WHEN punch_logs.punch_type = 'In' THEN punch_logs.timestamp END) as first_in"),
                    DB::raw("MAX(CASE WHEN punch_logs.punch_type = 'Out' THEN punch_logs.timestamp END) as last_out")
                )
                ->groupBy(DB::raw("DATE(punch_logs.timestamp)"), 'employees.employee_id', 'employees.full_name', 'employees.department')
                ->orderBy(DB::raw("DATE(punch_logs.timestamp)"), 'desc')
                ->chunk(500, function ($rows) use ($handle, $lateThreshold) {
                    foreach ($rows as $r) {
                        $firstIn = $r->first_in ? Carbon::parse($r->first_in) : null;
                        $lastOut = $r->last_out ? Carbon::parse($r->last_out) : null;
                        $workHours = ($firstIn && $lastOut) ? round($firstIn->diffInMinutes($lastOut) / 60, 2) . ' hrs' : '-';
                        $status = ($firstIn && $firstIn->format('H:i') > $lateThreshold) ? 'Late' : 'Normal';

                        fputcsv($handle, [
                            $r->punch_date,
                            $r->employee_id,
                            $r->full_name,
                            $r->department,
                            $firstIn ? $firstIn->format('H:i:s') : '-',
                            $lastOut ? $lastOut->format('H:i:s') : '-',
                            $workHours,
                            $status,
                        ]);
                    }
                });
            } else {
                fputcsv($handle, ['ID', 'PIN', 'Name', 'Department', 'Type', 'Date & Time', 'Latitude', 'Longitude', 'Biometric', 'ADMS Status']);

                $query = PunchLog::with('employee')
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
