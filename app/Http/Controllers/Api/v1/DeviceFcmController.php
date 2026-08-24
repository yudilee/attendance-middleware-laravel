<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\DeviceBinding;

class DeviceFcmController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_uuid' => 'required|string',
            'fcm_token' => 'required|string',
            'employee_id' => 'nullable|string',
        ]);

        $query = DeviceBinding::where('device_uuid', $validated['device_uuid']);
        if (!empty($validated['employee_id'])) {
            $query->where('employee_id', $validated['employee_id']);
        }

        $binding = $query->first();
        if ($binding) {
            $binding->update(['fcm_token' => $validated['fcm_token']]);
        }

        return response()->json(['status' => 'ok', 'message' => 'FCM token updated.']);
    }
}
