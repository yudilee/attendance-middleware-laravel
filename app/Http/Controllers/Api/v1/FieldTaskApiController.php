<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\FieldTask;
use Illuminate\Http\Request;

class FieldTaskApiController extends Controller
{
    public function index(Request $request)
    {
        $employeeId = $request->query('employee_id');
        $status = $request->query('status');

        $query = FieldTask::with('customer')
            ->orderBy('due_date', 'asc');

        if ($employeeId) {
            $query->where('employee_id', $employeeId);
        }

        if ($status) {
            $query->where('status', $status);
        }

        $tasks = $query->get();

        return response()->json([
            'success' => true,
            'tasks' => $tasks,
        ]);
    }

    public function start(Request $request, $id)
    {
        $task = FieldTask::findOrFail($id);
        $task->update(['status' => 'in_progress']);

        return response()->json([
            'success' => true,
            'message' => 'Task marked as in progress.',
            'task' => $task,
        ]);
    }

    public function complete(Request $request, $id)
    {
        $validated = $request->validate([
            'completed_notes' => 'nullable|string',
            'field_visit_id' => 'nullable|exists:field_visits,id',
        ]);

        $task = FieldTask::findOrFail($id);
        $task->update([
            'status' => 'completed',
            'completed_at' => now(),
            'completed_notes' => $validated['completed_notes'] ?? null,
            'field_visit_id' => $validated['field_visit_id'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Task completed successfully.',
            'task' => $task,
        ]);
    }
}
