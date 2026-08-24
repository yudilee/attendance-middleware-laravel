<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\DeviceBinding;
use App\Models\Employee;
use App\Models\Branch;
use App\Models\ApiKey;
use Carbon\Carbon;

class DeviceOnboardController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $rawKey = $request->header('X-API-Key')
            ?? $request->input('api_key')
            ?? $request->input('token')
            ?? $request->input('key');

        $employeeId = $request->input('employee_id')
            ?? $request->input('pin')
            ?? $request->input('emp_id');

        if ($employeeId) {
            $employeeId = trim((string)$employeeId);
        }

        $deviceUuid = trim((string)($request->input('device_uuid') ?? 'DEFAULT_DEVICE'));
        $deviceLabel = $request->input('device_label') ?? 'Mobile Device';

        $apiKey = $request->attributes->get('api_key');

        if (!$apiKey && $rawKey) {
            $plain = trim((string)$rawKey);
            $hashed = 'sha256:' . hash('sha256', $plain);
            $apiKey = ApiKey::where('key_value', $hashed)
                ->orWhere('key_value', $plain)
                ->first();
        }

        if (!$employeeId && $apiKey) {
            // 1. Try extracting numeric PIN from label (e.g. "[PIN: 000011748]" or "App Key for 000011392")
            if (preg_match('/(?:PIN\s*:?\s*|for\s+|\[PIN:\s*)(\d+)/i', $apiKey->label, $m)) {
                $employeeId = $m[1];
            }
            // 2. Try looking up existing DeviceBinding linked to this apiKey
            if (!$employeeId) {
                $employeeId = DeviceBinding::where('api_key_id', $apiKey->id)->value('employee_id');
            }
            // 3. Try matching full name from label (e.g. "YUDI SANTOSO's Device" -> "YUDI SANTOSO")
            if (!$employeeId) {
                $cleanName = trim(preg_replace('/\'s\s+Device.*$/i', '', $apiKey->label));
                $cleanName = trim(preg_replace('/^App\s+Key\s+for\s+/i', '', $cleanName));
                if (!empty($cleanName)) {
                    $matchedEmp = Employee::where('full_name', 'ilike', "%{$cleanName}%")->first();
                    if ($matchedEmp) {
                        $employeeId = $matchedEmp->employee_id;
                    }
                }
            }
        }

        if (!$employeeId) {
            return response()->json([
                'status' => 'error',
                'detail' => 'Employee ID or valid token is required for onboarding. Please scan the QR code from the admin portal.',
            ], 422);
        }

        // Check if employee exists
        $employee = Employee::where('employee_id', $employeeId)->first();
        if (!$employee) {
            // Auto-create stub employee if needed
            $employee = Employee::create([
                'employee_id' => $employeeId,
                'full_name' => 'Employee ' . $employeeId,
                'is_active' => true,
            ]);
        }

        // Upsert device binding
        $binding = DeviceBinding::updateOrCreate(
            ['device_uuid' => $deviceUuid, 'employee_id' => $employeeId],
            [
                'api_key_id' => $apiKey?->id,
                'device_label' => $deviceLabel,
                'registration_status' => 'approved',
                'approved_at' => Carbon::now(),
                'approved_by' => 'qr_onboard',
                'is_active' => true,
                'fcm_token' => $request->input('fcm_token'),
            ]
        );

        // Auto-assign all active branches if none exist
        if ($binding->branches()->count() === 0) {
            $branchIds = Branch::where('is_active', true)->pluck('id');
            $binding->branches()->sync($branchIds);
        }

        return response()->json([
            'status' => 'active',
            'device_status' => 'active',
            'message' => 'Device successfully onboarded and approved.',
            'employee_id' => $employeeId,
            'employee_name' => $employee->full_name,
            'api_key' => $rawKey ?? '',
            'device_uuid' => $deviceUuid,
            'approved_at' => $binding->approved_at?->toIso8601String(),
        ]);
    }
}
