<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\ShiftSchedule;

class ShiftController extends Controller
{
    public function index(Request $request): Response
    {
        $shifts = ShiftSchedule::all();

        return Inertia::render('Admin/Shifts/Index', [
            'shifts' => $shifts,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'start_time' => 'required|string|max:5',
            'end_time' => 'required|string|max:5',
            'grace_minutes' => 'required|integer',
            'min_work_hours' => 'required|numeric',
            'overtime_after_hours' => 'required|numeric',
            'working_days' => 'required|string',
            'is_default' => 'boolean',
        ]);

        ShiftSchedule::create($validated);

        return back()->with('success', 'Shift schedule created.');
    }

    public function update(Request $request, ShiftSchedule $shift)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'start_time' => 'required|string|max:5',
            'end_time' => 'required|string|max:5',
            'grace_minutes' => 'required|integer',
            'min_work_hours' => 'required|numeric',
            'overtime_after_hours' => 'required|numeric',
            'working_days' => 'required|string',
            'is_default' => 'boolean',
        ]);

        $shift->update($validated);

        return back()->with('success', 'Shift schedule updated.');
    }

    public function destroy(ShiftSchedule $shift)
    {
        $shift->delete();
        return back()->with('success', 'Shift schedule deleted.');
    }
}
