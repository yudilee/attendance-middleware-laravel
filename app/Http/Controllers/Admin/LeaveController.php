<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\LeaveRequest;
use App\Models\LeaveBalance;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class LeaveController extends Controller
{
    public function index(Request $request): Response
    {
        $statusFilter = $request->query('status', 'all');
        $categoryFilter = $request->query('category', 'all');
        $search = $request->query('search');

        $driver = DB::connection()->getDriverName();
        $likeOp = $driver === 'pgsql' ? 'ilike' : 'like';

        $query = LeaveRequest::with('employee')
            ->orderBy('created_at', 'desc');

        if ($statusFilter && $statusFilter !== 'all') {
            $query->where('status', $statusFilter);
        }

        if ($categoryFilter && $categoryFilter !== 'all') {
            $query->where('category', $categoryFilter);
        }

        if ($search) {
            $query->where(function ($q) use ($search, $likeOp) {
                $q->where('employee_id', $likeOp, "%{$search}%")
                  ->orWhere('leave_type', $likeOp, "%{$search}%")
                  ->orWhere('permit_type', $likeOp, "%{$search}%")
                  ->orWhere('reason', $likeOp, "%{$search}%")
                  ->orWhereHas('employee', function ($eq) use ($search, $likeOp) {
                      $eq->where('full_name', $likeOp, "%{$search}%");
                  });
            });
        }

        $leaves = $query->paginate(20)->through(function ($l) {
            $attachmentUrl = null;
            if ($l->attachment_path) {
                $attachmentUrl = url(Storage::url($l->attachment_path));
            }

            return [
                'id' => $l->id,
                'employee_id' => $l->employee_id,
                'employee_name' => $l->employee?->full_name ?? "Employee {$l->employee_id}",
                'department' => $l->employee?->department ?? '-',
                'category' => $l->category ?? 'leave',
                'leave_type' => $l->leave_type,
                'permit_type' => $l->permit_type,
                'start_date' => $l->start_date ? $l->start_date->format('Y-m-d') : '-',
                'end_date' => $l->end_date ? $l->end_date->format('Y-m-d') : '-',
                'days_count' => ($l->start_date && $l->end_date) ? $l->start_date->diffInDays($l->end_date) + 1 : 1,
                'expected_time' => $l->expected_time,
                'reason' => $l->reason,
                'status' => $l->status ?? 'pending',
                'attachment_url' => $attachmentUrl,
                'admin_notes' => $l->admin_notes,
                'approved_by' => $l->approved_by ?? $l->processed_by,
                'processed_at' => $l->processed_at ? $l->processed_at->format('Y-m-d H:i') : null,
                'created_at' => $l->created_at ? $l->created_at->format('Y-m-d H:i') : '-',
            ];
        });

        $stats = [
            'total' => LeaveRequest::count(),
            'pending' => LeaveRequest::where('status', 'pending')->count(),
            'approved' => LeaveRequest::where('status', 'approved')->count(),
            'rejected' => LeaveRequest::where('status', 'rejected')->count(),
            'total_leaves' => LeaveRequest::where('category', 'leave')->count(),
            'total_permits' => LeaveRequest::where('category', 'permit')->count(),
        ];

        $employees = Employee::where('is_active', true)
            ->where('is_deleted', false)
            ->orderBy('full_name')
            ->get(['employee_id', 'full_name', 'department']);

        return Inertia::render('Admin/Leaves/Index', [
            'leaves' => $leaves,
            'stats' => $stats,
            'employees' => $employees,
            'filters' => [
                'status' => $statusFilter,
                'category' => $categoryFilter,
                'search' => $search ?? '',
            ],
        ]);
    }

    public function approve(Request $request, LeaveRequest $leave)
    {
        $adminName = auth()->user()?->name ?? 'HR Admin';
        $adminNotes = $request->input('admin_notes');

        $leave->update([
            'status' => 'approved',
            'approved_by' => $adminName,
            'processed_by' => $adminName,
            'admin_notes' => $adminNotes,
            'processed_at' => Carbon::now(),
        ]);

        $label = $leave->category === 'permit' ? 'Permit / Late arrival' : 'Leave';
        return back()->with('success', "{$label} request #{$leave->id} approved successfully.");
    }

    public function reject(Request $request, LeaveRequest $leave)
    {
        $adminName = auth()->user()?->name ?? 'HR Admin';
        $adminNotes = $request->input('admin_notes');

        $leave->update([
            'status' => 'rejected',
            'approved_by' => $adminName,
            'processed_by' => $adminName,
            'admin_notes' => $adminNotes,
            'processed_at' => Carbon::now(),
        ]);

        $label = $leave->category === 'permit' ? 'Permit / Late arrival' : 'Leave';
        return back()->with('success', "{$label} request #{$leave->id} rejected.");
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|string',
            'category' => 'required|in:leave,permit',
            'leave_type' => 'nullable|string',
            'permit_type' => 'nullable|string',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date',
            'expected_time' => 'nullable|string',
            'reason' => 'nullable|string',
            'status' => 'nullable|in:pending,approved',
            'admin_notes' => 'nullable|string',
        ]);

        $startDate = Carbon::parse($validated['start_date']);
        $endDate = !empty($validated['end_date']) ? Carbon::parse($validated['end_date']) : $startDate;

        LeaveRequest::create([
            'employee_id' => $validated['employee_id'],
            'category' => $validated['category'],
            'leave_type' => $validated['category'] === 'leave' ? ($validated['leave_type'] ?? 'annual') : 'permit',
            'permit_type' => $validated['category'] === 'permit' ? ($validated['permit_type'] ?? 'late_arrival') : null,
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'expected_time' => $validated['expected_time'] ?? null,
            'reason' => $validated['reason'] ?? '',
            'status' => $validated['status'] ?? 'approved',
            'approved_by' => auth()->user()?->name ?? 'HR Admin',
            'processed_by' => auth()->user()?->name ?? 'HR Admin',
            'admin_notes' => $validated['admin_notes'] ?? null,
            'processed_at' => Carbon::now(),
            'created_at' => Carbon::now(),
        ]);

        return back()->with('success', "Request recorded successfully for employee {$validated['employee_id']}.");
    }
}
