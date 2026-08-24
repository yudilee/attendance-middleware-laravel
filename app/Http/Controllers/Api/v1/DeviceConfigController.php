<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\DeviceBinding;
use App\Models\Employee;
use App\Models\Branch;
use App\Models\ShiftSchedule;
use Carbon\Carbon;

class DeviceConfigController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $deviceUuid = $request->query('device_uuid');
        $employeeId = $request->query('employee_id');

        if (!$deviceUuid && !$employeeId) {
            return response()->json(['detail' => 'device_uuid or employee_id required.'], 422);
        }

        // Look for binding
        $query = DeviceBinding::with(['branches.checkpoints', 'employee.shiftSchedule', 'employee.group.shiftSchedule', 'employee.company.shiftSchedule']);

        if ($deviceUuid && $employeeId) {
            $binding = (clone $query)->where('device_uuid', $deviceUuid)->where('employee_id', $employeeId)->first();
        } elseif ($deviceUuid) {
            $binding = (clone $query)->where('device_uuid', $deviceUuid)->first();
        } else {
            $binding = (clone $query)->where('employee_id', $employeeId)->first();
        }

        if (!$binding) {
            return response()->json([
                'status' => 'not_registered',
                'device_status' => 'not_registered',
                'detail' => 'Device not registered. Please register this device.',
            ]);
        }

        // Check registration status
        $status = $binding->registration_status;
        if (!$binding->is_active || $status === 'suspended') {
            return response()->json([
                'status' => 'suspended',
                'device_status' => 'suspended',
                'detail' => 'Device access has been suspended by administration.',
            ]);
        }

        if ($status === 'pending_approval') {
            return response()->json([
                'status' => 'pending_approval',
                'device_status' => 'pending_approval',
                'detail' => 'Device is pending admin approval.',
                'employee_id' => $binding->employee_id,
                'device_label' => $binding->device_label,
            ]);
        }

        // Active / Approved state
        $employee = $binding->employee;
        $branches = $binding->branches;

        // Fallback: If no branches assigned specifically to this binding, provide all active branches or employee branch
        if ($branches->isEmpty()) {
            $branches = Branch::where('is_active', true)->with('checkpoints')->get();
        }

        // Resolve Shift Schedule: Employee -> Group -> Branch -> Company Default -> Global Default
        $shiftSchedule = $employee?->shiftSchedule
            ?? $employee?->group?->shiftSchedule
            ?? $employee?->company?->shiftSchedule
            ?? ShiftSchedule::where('is_default', true)->first()
            ?? ShiftSchedule::first();

        $branchesData = $branches->map(function ($b) {
            return [
                'id' => $b->id,
                'name' => $b->name,
                'latitude' => $b->latitude,
                'longitude' => $b->longitude,
                'radius_meters' => $b->radius_meters,
                'geofence_type' => $b->geofence_type,
                'polygon_coordinates' => $b->polygon_coordinates ? json_decode($b->polygon_coordinates, true) : null,
                'qr_code_enabled' => $b->qr_code_enabled,
                'qr_code_data' => $b->qr_code_data,
                'nfc_enabled' => $b->nfc_enabled,
                'nfc_tag_data' => $b->nfc_tag_data,
                'checkpoints' => $b->checkpoints->where('is_active', true)->map(function ($cp) {
                    return [
                        'id' => $cp->id,
                        'name' => $cp->name,
                        'latitude' => $cp->latitude,
                        'longitude' => $cp->longitude,
                        'radius_meters' => $cp->radius_meters,
                        'geofence_type' => $cp->geofence_type,
                        'polygon_coordinates' => $cp->polygon_coordinates ? json_decode($cp->polygon_coordinates, true) : null,
                    ];
                })->values(),
            ];
        });

        return response()->json([
            'status' => 'active',
            'device_status' => 'active',
            'employee_id' => $binding->employee_id,
            'employee_name' => $employee?->full_name ?? 'Employee',
            'department' => $employee?->department ?? 'General',
            'device_label' => $binding->device_label,
            'approved_at' => $binding->approved_at?->toIso8601String(),
            'branches' => $branchesData,
            'shift_schedule' => $shiftSchedule ? [
                'name' => $shiftSchedule->name,
                'start_time' => $shiftSchedule->start_time,
                'end_time' => $shiftSchedule->end_time,
                'grace_minutes' => $shiftSchedule->grace_minutes,
                'min_work_hours' => $shiftSchedule->min_work_hours,
                'working_days' => $shiftSchedule->working_days,
            ] : null,
            'server_time' => Carbon::now('Asia/Jakarta')->toIso8601String(),
        ]);
    }
}
