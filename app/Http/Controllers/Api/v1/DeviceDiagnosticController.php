<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\DeviceBinding;
use App\Models\Employee;
use App\Models\Branch;
use Carbon\Carbon;

class DeviceDiagnosticController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $apiKey = $request->attributes->get('api_key');
        $deviceUuid = $request->query('device_uuid');
        $employeeId = $request->query('employee_id');

        $binding = null;
        if ($deviceUuid && $employeeId) {
            $binding = DeviceBinding::where('device_uuid', $deviceUuid)->where('employee_id', $employeeId)->first();
        } elseif ($deviceUuid) {
            $binding = DeviceBinding::where('device_uuid', $deviceUuid)->first();
        } elseif ($employeeId) {
            $binding = DeviceBinding::where('employee_id', $employeeId)->first();
        }

        $assignedBranchesCount = 0;
        if ($binding) {
            $assignedBranchesCount = $binding->branches()->count();
            if ($assignedBranchesCount === 0) {
                $assignedBranchesCount = Branch::where('is_active', true)->count();
            }
        }

        return response()->json([
            'status' => 'ok',
            'api_key_valid' => true,
            'api_key_label' => $apiKey?->label ?? 'Valid Key',
            'device_registered' => $binding !== null,
            'device_status' => $binding?->registration_status ?? 'not_registered',
            'device_is_active' => $binding?->is_active ?? false,
            'employee_id' => $binding?->employee_id ?? $employeeId,
            'assigned_branches_count' => $assignedBranchesCount,
            'server_time' => Carbon::now('Asia/Jakarta')->toIso8601String(),
            'timezone_offset' => 7,
        ]);
    }
}
