<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\Employee;
use App\Models\ShiftSchedule;
use App\Models\Company;
use App\Models\EmployeeGroup;
use App\Models\PunchLog;
use App\Models\DeviceBinding;
use App\Models\LeaveBalance;
use App\Models\Branch;
use App\Models\AdmsRegisteredEmployee;
use App\Services\AdmsService;
use Carbon\Carbon;

class EmployeeController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->query('search');
        $department = $request->query('department');

        $query = Employee::with(['shiftSchedule', 'company', 'group'])
            ->where('is_deleted', false)
            ->orderBy('employee_id', 'asc');

        $driver = \DB::connection()->getDriverName();
        $likeOp = $driver === 'pgsql' ? 'ilike' : 'like';

        if ($search) {
            $query->where(function ($q) use ($search, $likeOp) {
                $q->where('employee_id', $likeOp, "%{$search}%")
                  ->orWhere('full_name', $likeOp, "%{$search}%");
            });
        }

        if ($department && $department !== 'all') {
            $query->where('department', $department);
        }

        $employees = $query->paginate(25)->through(function ($e) {
            return [
                'id' => $e->id,
                'employee_id' => $e->employee_id,
                'full_name' => $e->full_name,
                'department' => $e->department ?? '-',
                'employee_type' => $e->employee_type,
                'is_active' => $e->is_active,
                'shift_name' => $e->shiftSchedule?->name ?? 'Default',
                'shift_schedule_id' => $e->shift_schedule_id,
                'company_name' => $e->company?->name ?? '-',
                'last_synced' => $e->last_synced?->format('Y-m-d H:i'),
            ];
        });

        $shifts = ShiftSchedule::get(['id', 'name']);
        $departments = Employee::where('is_deleted', false)
            ->whereNotNull('department')
            ->where('department', '!=', '')
            ->distinct()
            ->orderBy('department')
            ->pluck('department');

        return Inertia::render('Admin/Employees/Index', [
            'employees' => $employees,
            'shifts' => $shifts,
            'departments' => $departments,
            'filters' => [
                'search' => $search ?? '',
                'department' => $department ?? 'all',
            ],
        ]);
    }

    public function show(Employee $employee): Response
    {
        $employee->load(['shiftSchedule', 'company', 'group']);

        $devices = DeviceBinding::with('branches')
            ->where('employee_id', $employee->employee_id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($d) {
                return [
                    'id' => $d->id,
                    'device_label' => $d->device_label ?? 'Mobile Phone',
                    'device_uuid' => $d->device_uuid,
                    'registration_status' => $d->registration_status,
                    'is_active' => $d->is_active,
                    'created_at' => $d->created_at ? $d->created_at->format('Y-m-d H:i') : '-',
                    'branches' => $d->branches->pluck('name'),
                ];
            });

        $recentPunches = PunchLog::where('employee_id', $employee->employee_id)
            ->orderBy('timestamp', 'desc')
            ->limit(30)
            ->get()
            ->map(function ($p) {
                return [
                    'id' => $p->id,
                    'punch_type' => $p->punch_type,
                    'timestamp' => $p->timestamp->format('Y-m-d H:i:s'),
                    'latitude' => $p->latitude,
                    'longitude' => $p->longitude,
                    'biometric_verified' => $p->biometric_verified,
                    'adms_status' => $p->adms_status,
                ];
            });

        $leaveBalance = LeaveBalance::where('employee_id', $employee->employee_id)->first();
        $shifts = ShiftSchedule::get(['id', 'name']);
        $branches = Branch::where('is_active', true)->get(['id', 'name']);

        return Inertia::render('Admin/Employees/Show', [
            'employee' => [
                'id' => $employee->id,
                'employee_id' => $employee->employee_id,
                'full_name' => $employee->full_name,
                'department' => $employee->department ?? '-',
                'employee_type' => $employee->employee_type,
                'is_active' => $employee->is_active,
                'shift_name' => $employee->shiftSchedule?->name ?? 'Company Default',
                'shift_schedule_id' => $employee->shift_schedule_id,
                'company_name' => $employee->company?->name ?? '-',
                'last_synced' => $employee->last_synced ? $employee->last_synced->format('Y-m-d H:i:s') : '-',
            ],
            'devices' => $devices,
            'recent_punches' => $recentPunches,
            'leave_balance' => $leaveBalance ? [
                'annual_total' => $leaveBalance->annual_total,
                'annual_used' => $leaveBalance->annual_used,
                'sick_total' => $leaveBalance->sick_total,
                'sick_used' => $leaveBalance->sick_used,
                'annual_remaining' => max(0, $leaveBalance->annual_total - $leaveBalance->annual_used),
                'sick_remaining' => max(0, $leaveBalance->sick_total - $leaveBalance->sick_used),
            ] : [
                'annual_total' => 12,
                'annual_used' => 0,
                'sick_total' => 14,
                'sick_used' => 0,
                'annual_remaining' => 12,
                'sick_remaining' => 14,
            ],
            'shifts' => $shifts,
            'branches' => $branches,
        ]);
    }

    public function search(Request $request)
    {
        $q = trim($request->query('query', ''));
        if (empty($q)) {
            $results = Employee::where('is_deleted', false)
                ->orderBy('full_name', 'asc')
                ->limit(15)
                ->get(['id', 'employee_id', 'full_name', 'department']);
            return response()->json($results);
        }

        $driver = \DB::connection()->getDriverName();
        $likeOp = $driver === 'pgsql' ? 'ilike' : 'like';

        $results = Employee::where('is_deleted', false)
            ->where(function ($query) use ($q, $likeOp) {
                $query->where('employee_id', $likeOp, "%{$q}%")
                      ->orWhere('full_name', $likeOp, "%{$q}%")
                      ->orWhere('department', $likeOp, "%{$q}%");
            })
            ->orderBy('full_name', 'asc')
            ->limit(25)
            ->get(['id', 'employee_id', 'full_name', 'department']);

        return response()->json($results);
    }

    public function updateShift(Request $request, Employee $employee)
    {
        $validated = $request->validate([
            'shift_schedule_id' => 'nullable|exists:shift_schedules,id',
        ]);

        $employee->update(['shift_schedule_id' => $validated['shift_schedule_id']]);

        return back()->with('success', "Shift schedule updated for {$employee->full_name}.");
    }

    public function updateRole(Request $request, Employee $employee)
    {
        $validated = $request->validate([
            'employee_type' => 'required|string|in:regular,mechanic,sales',
        ]);

        $employee->update(['employee_type' => $validated['employee_type']]);

        return back()->with('success', "Employee role updated to {$validated['employee_type']} for {$employee->full_name}.");
    }

    /**
     * Delete an employee from ADMS server and remove their registered record.
     */
    public function deleteFromAdms(Employee $employee)
    {
        $admsService = app(AdmsService::class);
        $result = $admsService->deleteEmployeeFromAdms($employee->employee_id);

        if ($result['success']) {
            // Remove the AdmsRegisteredEmployee record
            AdmsRegisteredEmployee::where('employee_id', $employee->employee_id)->delete();

            return back()->with('success', "Employee {$employee->full_name} (PIN: {$employee->employee_id}) deleted from ADMS successfully.");
        }

        return back()->with('error', "Failed to delete employee from ADMS: {$result['message']}");
    }
    /**
     * Store a newly created employee in the database and register on ADMS.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|string|max:20|unique:employees,employee_id',
            'full_name' => 'required|string|max:255',
            'department' => 'nullable|string|max:255',
            'employee_type' => 'nullable|string|in:regular,mechanic,sales',
            'shift_schedule_id' => 'nullable|exists:shift_schedules,id',
            'company_id' => 'nullable|exists:companies,id',
            'group_id' => 'nullable|exists:employee_groups,id',
        ]);

        $employee = Employee::create([
            'employee_id' => $validated['employee_id'],
            'full_name' => $validated['full_name'],
            'department' => $validated['department'] ?? null,
            'employee_type' => $validated['employee_type'] ?? 'regular',
            'shift_schedule_id' => $validated['shift_schedule_id'] ?? null,
            'company_id' => $validated['company_id'] ?? null,
            'group_id' => $validated['group_id'] ?? null,
            'is_active' => true,
            'is_deleted' => false,
        ]);

        // Register the new employee on ADMS as a background job
        \App\Jobs\RegisterEmployeeOnAdms::dispatch($employee);

        return redirect()->route('admin.employees')
            ->with('success', "Employee {$employee->full_name} created successfully and queued for ADMS registration.");
    }

    /**
     * Update the specified employee and re-register on ADMS if name or type changed.
     */
    public function update(Request $request, Employee $employee)
    {
        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'department' => 'nullable|string|max:255',
            'employee_type' => 'nullable|string|in:regular,mechanic,sales',
            'shift_schedule_id' => 'nullable|exists:shift_schedules,id',
            'company_id' => 'nullable|exists:companies,id',
            'group_id' => 'nullable|exists:employee_groups,id',
        ]);

        $nameChanged = isset($validated['full_name']) && $validated['full_name'] !== $employee->full_name;
        $typeChanged = isset($validated['employee_type']) && $validated['employee_type'] !== $employee->employee_type;

        $employee->update($validated);

        // If name or employee_type changed, re-register on ADMS to keep it in sync
        if ($nameChanged || $typeChanged) {
            \App\Jobs\RegisterEmployeeOnAdms::dispatch($employee);
        }

        return redirect()->route('admin.employees')
            ->with('success', "Employee {$employee->full_name} updated successfully.");
    }
}
