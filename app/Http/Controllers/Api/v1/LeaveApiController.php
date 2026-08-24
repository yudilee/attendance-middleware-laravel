<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\LeaveRequest;
use App\Models\LeaveBalance;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class LeaveApiController extends Controller
{
    /**
     * Get Leave Balance & Quota for an employee.
     */
    public function getBalance(Request $request)
    {
        $employeeId = $request->query('employee_id');
        if (!$employeeId) {
            return response()->json(['error' => 'employee_id is required'], 422);
        }

        $currentYear = (int) Carbon::now()->format('Y');

        $balance = LeaveBalance::where('employee_id', $employeeId)
            ->where('year', $currentYear)
            ->first();

        $annualQuota = $balance ? $balance->annual_leave_quota : 12;
        $usedDays = $balance ? $balance->used_annual_leave : 0;

        // Also calculate approved annual leaves in current year if balance record wasn't updated
        $approvedDays = LeaveRequest::where('employee_id', $employeeId)
            ->where('category', 'leave')
            ->where('leave_type', 'annual')
            ->where('status', 'approved')
            ->whereYear('start_date', $currentYear)
            ->get()
            ->sum(function ($l) {
                return ($l->start_date && $l->end_date)
                    ? $l->start_date->diffInDays($l->end_date) + 1
                    : 1;
            });

        $actualUsed = max($usedDays, $approvedDays);
        $remaining = max(0, $annualQuota - $actualUsed);

        // Pending count
        $pendingCount = LeaveRequest::where('employee_id', $employeeId)
            ->where('status', 'pending')
            ->count();

        return response()->json([
            'year' => $currentYear,
            'annual_quota' => $annualQuota,
            'used_days' => $actualUsed,
            'remaining_days' => $remaining,
            'pending_requests' => $pendingCount,
        ]);
    }

    /**
     * Get employee request history (both leaves and permits).
     */
    public function getHistory(Request $request)
    {
        $employeeId = $request->query('employee_id');
        if (!$employeeId) {
            return response()->json(['error' => 'employee_id is required'], 422);
        }

        $category = $request->query('category'); // 'leave', 'permit', or null for all

        $query = LeaveRequest::where('employee_id', $employeeId)
            ->orderBy('created_at', 'desc');

        if ($category && in_array($category, ['leave', 'permit'])) {
            $query->where('category', $category);
        }

        $requests = $query->limit(50)->get()->map(function ($r) {
            $days = ($r->start_date && $r->end_date)
                ? $r->start_date->diffInDays($r->end_date) + 1
                : 1;

            $attachmentUrl = null;
            if ($r->attachment_path) {
                $attachmentUrl = url(Storage::url($r->attachment_path));
            }

            return [
                'id' => $r->id,
                'category' => $r->category ?? 'leave',
                'leave_type' => $r->leave_type,
                'permit_type' => $r->permit_type,
                'start_date' => $r->start_date ? $r->start_date->format('Y-m-d') : null,
                'end_date' => $r->end_date ? $r->end_date->format('Y-m-d') : null,
                'days_count' => $days,
                'expected_time' => $r->expected_time,
                'reason' => $r->reason,
                'status' => $r->status ?? 'pending',
                'attachment_url' => $attachmentUrl,
                'admin_notes' => $r->admin_notes,
                'processed_by' => $r->processed_by ?? $r->approved_by,
                'created_at' => $r->created_at ? $r->created_at->format('Y-m-d H:i') : null,
            ];
        });

        return response()->json([
            'requests' => $requests,
        ]);
    }

    /**
     * Submit a Leave or Permit Request.
     */
    public function submitRequest(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|string',
            'category' => 'required|in:leave,permit',
            'leave_type' => 'nullable|string', // annual, sick, unpaid, maternity, other
            'permit_type' => 'nullable|string', // late_arrival, early_departure, official_duty, other
            'start_date' => 'required|date',
            'end_date' => 'nullable|date',
            'expected_time' => 'nullable|string', // e.g. 09:30 for late arrival
            'reason' => 'required|string|max:1000',
            'attachment' => 'nullable|file|mimes:jpeg,jpg,png,pdf|max:10240', // 10MB
        ]);

        $employee = Employee::where('employee_id', $validated['employee_id'])->first();
        if (!$employee) {
            return response()->json(['error' => 'Employee not found'], 404);
        }

        $category = $validated['category'];
        $startDate = Carbon::parse($validated['start_date']);
        $endDate = !empty($validated['end_date']) ? Carbon::parse($validated['end_date']) : $startDate;

        if ($endDate->lt($startDate)) {
            return response()->json(['error' => 'End date cannot be earlier than start date'], 422);
        }

        // Handle attachment file upload
        $attachmentPath = null;
        if ($request->hasFile('attachment') && $request->file('attachment')->isValid()) {
            $file = $request->file('attachment');
            $filename = 'leave_' . $validated['employee_id'] . '_' . time() . '.' . $file->getClientOriginalExtension();
            $attachmentPath = $file->storeAs('leave_attachments', $filename, 'public');
        }

        // Default leave type or permit type
        $leaveType = $category === 'leave' ? ($validated['leave_type'] ?? 'annual') : 'permit';
        $permitType = $category === 'permit' ? ($validated['permit_type'] ?? 'late_arrival') : null;

        $leaveRequest = LeaveRequest::create([
            'employee_id' => $validated['employee_id'],
            'category' => $category,
            'leave_type' => $leaveType,
            'permit_type' => $permitType,
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'expected_time' => $validated['expected_time'] ?? null,
            'reason' => $validated['reason'],
            'attachment_path' => $attachmentPath,
            'status' => 'pending',
            'created_at' => Carbon::now(),
        ]);

        Log::info("Leave/Permit request created: #{$leaveRequest->id} for employee {$validated['employee_id']} ({$category})");

        return response()->json([
            'success' => true,
            'message' => $category === 'leave'
                ? 'Leave request submitted successfully and is pending approval.'
                : 'Permission / Late arrival request submitted successfully and is pending approval.',
            'request_id' => $leaveRequest->id,
            'category' => $category,
            'status' => 'pending',
        ], 201);
    }
}
