<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\Branch;
use App\Models\BranchCheckpoint;
use App\Models\Company;

class BranchController extends Controller
{
    public function index(Request $request): Response
    {
        $branches = Branch::with(['checkpoints', 'shiftSchedule'])->orderBy('id', 'asc')->get()->map(function ($b) {
            return [
                'id' => $b->id,
                'name' => $b->name,
                'latitude' => (float)$b->latitude,
                'longitude' => (float)$b->longitude,
                'radius_meters' => (float)$b->radius_meters,
                'is_active' => (bool)$b->is_active,
                'geofence_type' => $b->geofence_type ?? 'circle',
                'polygon_coordinates' => $b->polygon_coordinates ? json_decode($b->polygon_coordinates, true) : null,
                'qr_code_enabled' => (bool)$b->qr_code_enabled,
                'qr_code_data' => $b->qr_code_data,
                'shift_schedule_id' => $b->shift_schedule_id,
                'shift_name' => $b->shiftSchedule?->name,
                'shift_hours' => $b->shiftSchedule ? "{$b->shiftSchedule->start_time} - {$b->shiftSchedule->end_time}" : null,
                'timezone_name' => $b->timezone_name ?? 'Asia/Jakarta',
                'timezone_offset' => $b->timezone_offset ?? 7,
                'checkpoints' => $b->checkpoints->map(function ($cp) {
                    return [
                        'id' => $cp->id,
                        'branch_id' => $cp->branch_id,
                        'name' => $cp->name,
                        'latitude' => (float)$cp->latitude,
                        'longitude' => (float)$cp->longitude,
                        'radius_meters' => (float)$cp->radius_meters,
                        'is_active' => (bool)$cp->is_active,
                        'geofence_type' => $cp->geofence_type ?? 'circle',
                        'polygon_coordinates' => $cp->polygon_coordinates ? json_decode($cp->polygon_coordinates, true) : null,
                    ];
                }),
                'checkpoints_count' => $b->checkpoints->count(),
            ];
        });

        $shifts = \App\Models\ShiftSchedule::orderBy('name', 'asc')->get(['id', 'name', 'start_time', 'end_time', 'grace_minutes']);

        return Inertia::render('Admin/Branches/Index', [
            'branches' => $branches,
            'shifts' => $shifts,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'radius_meters' => 'required|numeric|min:5',
            'geofence_type' => 'required|in:circle,polygon',
            'polygon_coordinates' => 'nullable|string',
            'shift_schedule_id' => 'nullable|exists:shift_schedules,id',
            'is_active' => 'boolean',
            'qr_code_enabled' => 'boolean',
            'qr_code_data' => 'nullable|string',
            'timezone_name' => 'nullable|string',
            'timezone_offset' => 'nullable|integer',
        ]);

        $branch = Branch::create($validated);

        return back()->with('success', "Branch '{$branch->name}' created successfully.");
    }

    public function update(Request $request, Branch $branch)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'radius_meters' => 'required|numeric|min:5',
            'geofence_type' => 'required|in:circle,polygon',
            'polygon_coordinates' => 'nullable|string',
            'shift_schedule_id' => 'nullable|exists:shift_schedules,id',
            'is_active' => 'boolean',
            'qr_code_enabled' => 'boolean',
            'qr_code_data' => 'nullable|string',
            'timezone_name' => 'nullable|string',
            'timezone_offset' => 'nullable|integer',
        ]);

        $branch->update($validated);

        return back()->with('success', "Branch '{$branch->name}' updated successfully.");
    }

    public function destroy(Branch $branch)
    {
        $branch->checkpoints()->delete();
        $branch->delete();

        return back()->with('success', 'Branch deleted successfully.');
    }

    public function storeCheckpoint(Request $request, Branch $branch)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'radius_meters' => 'required|numeric|min:5',
            'geofence_type' => 'required|in:circle,polygon',
            'polygon_coordinates' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $branch->checkpoints()->create($validated);

        return back()->with('success', 'Checkpoint added to branch.');
    }

    public function updateCheckpoint(Request $request, BranchCheckpoint $checkpoint)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'radius_meters' => 'required|numeric|min:5',
            'geofence_type' => 'required|in:circle,polygon',
            'polygon_coordinates' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $checkpoint->update($validated);

        return back()->with('success', 'Checkpoint updated successfully.');
    }

    public function destroyCheckpoint(BranchCheckpoint $checkpoint)
    {
        $checkpoint->delete();
        return back()->with('success', 'Checkpoint deleted.');
    }
}
