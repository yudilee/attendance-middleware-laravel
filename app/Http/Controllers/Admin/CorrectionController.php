<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\AttendanceCorrection;
use App\Models\PunchLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CorrectionController extends Controller
{
    public function index(Request $request): Response
    {
        $statusFilter = $request->query('status', 'all');
        $search = $request->query('search');

        $driver = DB::connection()->getDriverName();
        $likeOp = $driver === 'pgsql' ? 'ilike' : 'like';

        $query = AttendanceCorrection::with('employee')
            ->orderBy('created_at', 'desc');

        if ($statusFilter && $statusFilter !== 'all') {
            $query->where('status', $statusFilter);
        }

        if ($search) {
            $query->where(function ($q) use ($search, $likeOp) {
                $q->where('employee_id', $likeOp, "%{$search}%")
                  ->orWhere('description', $likeOp, "%{$search}%")
                  ->orWhereHas('employee', function ($eq) use ($search, $likeOp) {
                      $eq->where('full_name', $likeOp, "%{$search}%");
                  });
            });
        }

        $corrections = $query->paginate(20)->through(function ($c) {
            return [
                'id' => $c->id,
                'employee_id' => $c->employee_id,
                'employee_name' => $c->employee?->full_name ?? "Employee {$c->employee_id}",
                'department' => $c->employee?->department ?? '-',
                'correction_type' => $c->correction_type,
                'description' => $c->description,
                'proposed_timestamp' => $c->proposed_timestamp ? $c->proposed_timestamp->format('Y-m-d H:i:s') : '-',
                'proposed_punch_type' => $c->proposed_punch_type,
                'status' => $c->status ?? 'pending',
                'reviewed_by' => $c->reviewed_by,
                'reviewed_at' => $c->reviewed_at ? $c->reviewed_at->format('Y-m-d H:i') : null,
                'review_notes' => $c->review_notes,
                'created_at' => $c->created_at ? $c->created_at->format('Y-m-d H:i') : '-',
            ];
        });

        $stats = [
            'pending' => AttendanceCorrection::where('status', 'pending')->count(),
            'approved' => AttendanceCorrection::where('status', 'approved')->count(),
            'rejected' => AttendanceCorrection::where('status', 'rejected')->count(),
        ];

        return Inertia::render('Admin/Corrections/Index', [
            'corrections' => $corrections,
            'stats' => $stats,
            'filters' => [
                'status' => $statusFilter,
                'search' => $search ?? '',
            ],
        ]);
    }

    public function approve(AttendanceCorrection $correction)
    {
        // When approving, create the actual PunchLog if it was a missing punch
        if ($correction->proposed_timestamp) {
            PunchLog::create([
                'employee_id' => $correction->employee_id,
                'punch_type' => $correction->proposed_punch_type ?? 'In',
                'timestamp' => $correction->proposed_timestamp,
                'source' => 'manual_correction',
                'adms_status' => 'pending',
                'created_at' => Carbon::now(),
            ]);
        }

        $correction->update([
            'status' => 'approved',
            'reviewed_by' => auth()->user()?->name ?? 'admin',
            'reviewed_at' => Carbon::now(),
        ]);

        return back()->with('success', "Correction request #{$correction->id} approved and punch log registered.");
    }

    public function reject(AttendanceCorrection $correction, Request $request)
    {
        $correction->update([
            'status' => 'rejected',
            'reviewed_by' => auth()->user()?->name ?? 'admin',
            'reviewed_at' => Carbon::now(),
            'review_notes' => $request->input('notes', 'Rejected by administrator'),
        ]);

        return back()->with('success', "Correction request #{$correction->id} rejected.");
    }

    public function manualPunch(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|string',
            'punch_type' => 'required|in:In,Out',
            'timestamp' => 'required|date',
            'notes' => 'nullable|string',
        ]);

        $punch = PunchLog::create([
            'employee_id' => $validated['employee_id'],
            'punch_type' => $validated['punch_type'],
            'timestamp' => $validated['timestamp'],
            'source' => 'manual_admin',
            'adms_status' => 'pending',
            'created_at' => Carbon::now(),
        ]);

        return back()->with('success', "Manual punch recorded for employee {$validated['employee_id']}.");
    }
}
