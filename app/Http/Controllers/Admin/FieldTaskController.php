<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FieldTask;
use App\Models\CanvassPlan;
use App\Models\Employee;
use App\Models\Customer;
use App\Models\FieldVisit;
use App\Models\AuditLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FieldTaskController extends Controller
{
    public function index(Request $request)
    {
        $employeeId = $request->query('employee_id');
        $taskType = $request->query('task_type');

        $query = FieldTask::with(['employee', 'customer', 'fieldVisit']);

        if ($employeeId) {
            $query->where('employee_id', $employeeId);
        }

        if ($taskType) {
            $query->where('task_type', $taskType);
        }

        $allTasks = $query->orderBy('created_at', 'desc')->get();

        $kanban = [
            'pending' => $allTasks->where('status', 'pending')->values(),
            'in_progress' => $allTasks->where('status', 'in_progress')->values(),
            'completed' => $allTasks->where('status', 'completed')->values(),
            'cancelled' => $allTasks->where('status', 'cancelled')->values(),
        ];

        // Canvass plans for the current week
        $startOfWeek = Carbon::today()->startOfWeek()->toDateString();
        $endOfWeek = Carbon::today()->endOfWeek()->toDateString();

        $canvassPlans = CanvassPlan::with('employee')
            ->whereBetween('plan_date', [$startOfWeek, $endOfWeek])
            ->orderBy('plan_date')
            ->get();

        // Populate customer objects into plans
        $allCustomerIds = $canvassPlans->pluck('customer_ids')->flatten()->filter()->unique()->toArray();
        $customerLookup = Customer::whereIn('id', $allCustomerIds)->get()->keyBy('id');

        $canvassPlans = $canvassPlans->map(function ($p) use ($customerLookup) {
            $custIds = $p->customer_ids ?? [];
            $customers = [];
            foreach ($custIds as $cid) {
                if (isset($customerLookup[$cid])) {
                    $customers[] = $customerLookup[$cid];
                }
            }
            $p->customer_list = $customers;
            return $p;
        });

        // Performance Scoreboard Data
        $mechanicLeaderboard = Employee::where('employee_type', 'mechanic')
            ->withCount(['fieldVisits' => function ($q) {
                $q->whereDate('check_in_at', '>=', Carbon::today()->subDays(30));
            }])
            ->withCount(['fieldTasks' => function ($q) {
                $q->where('status', 'completed');
            }])
            ->get()
            ->map(function ($emp) {
                return [
                    'employee_id' => $emp->employee_id,
                    'name' => $emp->full_name,
                    'department' => $emp->department,
                    'visits_count' => $emp->field_visits_count,
                    'completed_tasks' => $emp->field_tasks_count,
                ];
            })->sortByDesc('visits_count')->values();

        $salesLeaderboard = Employee::where('employee_type', 'sales')
            ->withCount(['fieldVisits' => function ($q) {
                $q->whereDate('check_in_at', '>=', Carbon::today()->subDays(30));
            }])
            ->withCount(['canvassPlans' => function ($q) {
                $q->where('status', 'completed');
            }])
            ->get()
            ->map(function ($emp) {
                return [
                    'employee_id' => $emp->employee_id,
                    'name' => $emp->full_name,
                    'department' => $emp->department,
                    'visits_count' => $emp->field_visits_count,
                    'completed_plans' => $emp->canvass_plans_count,
                ];
            })->sortByDesc('visits_count')->values();

        $employees = Employee::where('is_active', true)
            ->orderBy('full_name')
            ->get(['employee_id', 'full_name', 'employee_type', 'department']);

        $customers = Customer::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'customer_type', 'city']);

        return Inertia::render('Admin/FieldTasks/Index', [
            'kanban' => $kanban,
            'canvassPlans' => $canvassPlans,
            'mechanicLeaderboard' => $mechanicLeaderboard,
            'salesLeaderboard' => $salesLeaderboard,
            'employees' => $employees,
            'customers' => $customers,
            'filters' => [
                'employee_id' => $employeeId,
                'task_type' => $taskType,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|string|exists:employees,employee_id',
            'customer_id' => 'nullable|exists:customers,id',
            'title' => 'required|string|max:200',
            'description' => 'nullable|string',
            'task_type' => 'required|string|in:storing,delivery,repair,inspection,canvass,follow_up',
            'priority' => 'required|string|in:low,medium,high,urgent',
            'due_date' => 'nullable|date',
        ]);

        $validated['status'] = 'pending';
        $validated['assigned_by'] = auth()->user()->name ?? 'Administrator';

        $task = FieldTask::create($validated);

        AuditLog::create([
            'admin_username' => auth()->user()->name ?? 'Administrator',
            'action' => 'Created Field Task',
            'target_type' => 'FieldTask',
            'target_id' => $task->id,
            'details' => "Assigned task: {$task->title} to {$task->employee_id}",
            'ip_address' => $request->ip(),
        ]);

        return redirect()->back()->with('success', 'Task created and dispatched successfully.');
    }

    public function update(Request $request, FieldTask $task)
    {
        $validated = $request->validate([
            'status' => 'required|string|in:pending,in_progress,completed,cancelled',
            'priority' => 'nullable|string|in:low,medium,high,urgent',
            'completed_notes' => 'nullable|string',
        ]);

        if ($validated['status'] === 'completed' && $task->status !== 'completed') {
            $validated['completed_at'] = now();
        }

        $task->update($validated);

        return redirect()->back()->with('success', 'Task updated successfully.');
    }

    public function destroy(Request $request, FieldTask $task)
    {
        $task->delete();
        return redirect()->back()->with('success', 'Task deleted successfully.');
    }

    public function storeCanvassPlan(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|string|exists:employees,employee_id',
            'plan_date' => 'required|date',
            'target_visits' => 'required|integer|min:1',
            'customer_ids' => 'required|array',
            'notes' => 'nullable|string',
        ]);

        $validated['status'] = 'active';
        $validated['created_by'] = auth()->user()->name ?? 'Administrator';

        CanvassPlan::updateOrCreate(
            ['employee_id' => $validated['employee_id'], 'plan_date' => $validated['plan_date']],
            $validated
        );

        return redirect()->back()->with('success', 'Canvass plan saved successfully.');
    }
}
