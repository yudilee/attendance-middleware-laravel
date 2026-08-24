<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FieldVisit;
use App\Models\Employee;
use App\Models\Customer;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FieldVisitController extends Controller
{
    public function index(Request $request)
    {
        $employeeId = $request->query('employee_id');
        $visitType = $request->query('visit_type');
        $status = $request->query('status');
        $startDate = $request->query('start_date', Carbon::today()->subDays(7)->toDateString());
        $endDate = $request->query('end_date', Carbon::today()->toDateString());

        $query = FieldVisit::with(['employee', 'customer', 'photos'])
            ->whereDate('check_in_at', '>=', $startDate)
            ->whereDate('check_in_at', '<=', $endDate);

        if ($employeeId) {
            $query->where('employee_id', $employeeId);
        }

        if ($visitType) {
            $query->where('visit_type', $visitType);
        }

        if ($status) {
            $query->where('status', $status);
        }

        $visits = $query->orderBy('check_in_at', 'desc')->paginate(20)->withQueryString();

        // Active / Live check-ins right now
        $activeVisits = FieldVisit::with(['employee', 'customer'])
            ->where('status', 'in_progress')
            ->whereDate('check_in_at', '>=', Carbon::today()->subDays(1))
            ->get();

        // Customer Locations for Map display
        $customerPins = Customer::where('is_active', true)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get(['id', 'name', 'customer_type', 'city', 'latitude', 'longitude', 'phone']);

        // Analytics KPIs
        $today = Carbon::today()->toDateString();
        $totalToday = FieldVisit::whereDate('check_in_at', $today)->count();
        $completedToday = FieldVisit::whereDate('check_in_at', $today)->where('status', 'completed')->count();
        $activeNow = $activeVisits->count();
        $avgDuration = FieldVisit::whereDate('check_in_at', $today)
            ->whereNotNull('duration_minutes')
            ->avg('duration_minutes') ?? 0;

        // Analytics 7-day velocity
        $sevenDays = [];
        $storingCounts = [];
        $canvassingCounts = [];
        for ($i = 6; $i >= 0; $i--) {
            $d = Carbon::today()->subDays($i)->toDateString();
            $label = Carbon::today()->subDays($i)->format('D, d M');
            $sevenDays[] = $label;
            $storingCounts[] = FieldVisit::whereDate('check_in_at', $d)->where('visit_type', 'storing')->count();
            $canvassingCounts[] = FieldVisit::whereDate('check_in_at', $d)->where('visit_type', 'canvassing')->count();
        }

        // Visits per employee (top 5 this week)
        $topEmployees = FieldVisit::with('employee')
            ->selectRaw('employee_id, count(*) as visit_count')
            ->whereDate('check_in_at', '>=', Carbon::today()->subDays(7))
            ->groupBy('employee_id')
            ->orderByDesc('visit_count')
            ->limit(5)
            ->get()
            ->map(function ($item) {
                return [
                    'name' => $item->employee->full_name ?? $item->employee_id,
                    'type' => $item->employee->employee_type ?? 'regular',
                    'count' => $item->visit_count,
                ];
            });

        $employees = Employee::where('is_active', true)
            ->orderBy('full_name')
            ->get(['employee_id', 'full_name', 'employee_type']);

        return Inertia::render('Admin/FieldVisits/Index', [
            'visits' => $visits,
            'activeVisits' => $activeVisits,
            'customerPins' => $customerPins,
            'employees' => $employees,
            'kpis' => [
                'total_today' => $totalToday,
                'completed_today' => $completedToday,
                'active_now' => $activeNow,
                'avg_duration' => round($avgDuration),
            ],
            'chartData' => [
                'labels' => $sevenDays,
                'storing' => $storingCounts,
                'canvassing' => $canvassingCounts,
                'topEmployees' => $topEmployees,
            ],
            'filters' => [
                'employee_id' => $employeeId,
                'visit_type' => $visitType,
                'status' => $status,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
        ]);
    }

    public function liveData()
    {
        $activeVisits = FieldVisit::with(['employee', 'customer'])
            ->where('status', 'in_progress')
            ->whereDate('check_in_at', '>=', Carbon::today()->subDays(1))
            ->get();

        return response()->json($activeVisits);
    }

    public function exportCsv(Request $request)
    {
        $employeeId = $request->query('employee_id');
        $visitType = $request->query('visit_type');
        $status = $request->query('status');
        $startDate = $request->query('start_date', Carbon::today()->subDays(30)->toDateString());
        $endDate = $request->query('end_date', Carbon::today()->toDateString());

        $query = FieldVisit::with(['employee', 'customer'])
            ->whereDate('check_in_at', '>=', $startDate)
            ->whereDate('check_in_at', '<=', $endDate);

        if ($employeeId) $query->where('employee_id', $employeeId);
        if ($visitType) $query->where('visit_type', $visitType);
        if ($status) $query->where('status', $status);

        $visits = $query->orderBy('check_in_at', 'desc')->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="field_visits_' . date('Ymd_His') . '.csv"',
        ];

        return new StreamedResponse(function () use ($visits) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ID', 'Employee ID', 'Employee Name', 'Role', 'Customer / Location', 'Visit Type', 'Purpose', 'Check-In Time', 'Check-Out Time', 'Duration (Mins)', 'Distance (KM)', 'Status', 'Check-In GPS', 'Check-Out GPS', 'Result / Notes']);

            foreach ($visits as $v) {
                fputcsv($handle, [
                    $v->id,
                    $v->employee_id,
                    $v->employee->full_name ?? '-',
                    $v->employee->employee_type ?? 'regular',
                    $v->customer->name ?? 'External Location',
                    $v->visit_type,
                    $v->purpose,
                    $v->check_in_at ? $v->check_in_at->format('Y-m-d H:i:s') : '',
                    $v->check_out_at ? $v->check_out_at->format('Y-m-d H:i:s') : '',
                    $v->duration_minutes ?? '',
                    $v->total_distance_km ?? '',
                    $v->status,
                    ($v->check_in_lat && $v->check_in_lng) ? "{$v->check_in_lat},{$v->check_in_lng}" : '',
                    ($v->check_out_lat && $v->check_out_lng) ? "{$v->check_out_lat},{$v->check_out_lng}" : '',
                    $v->result ?: $v->notes,
                ]);
            }
            fclose($handle);
        }, 200, $headers);
    }

    public function breadcrumbs(FieldVisit $fieldVisit)
    {
        $fieldVisit->load(['customer', 'employee', 'photos']);
        $breadcrumbs = $fieldVisit->breadcrumbs()->get();

        return response()->json([
            'visit' => $fieldVisit,
            'breadcrumbs' => $breadcrumbs,
            'total_distance_km' => $fieldVisit->total_distance_km ?? $fieldVisit->recalculateDistance(),
        ]);
    }
}
