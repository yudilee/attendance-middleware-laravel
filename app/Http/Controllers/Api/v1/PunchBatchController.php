<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\PunchLog;
use App\Models\DeviceBinding;
use App\Models\Branch;
use App\Services\GeofenceService;
use Carbon\Carbon;

class PunchBatchController extends Controller
{
    public function __construct(protected GeofenceService $geofenceService) {}

    public function store(Request $request): JsonResponse
    {
        $punches = $request->input('punches');
        if (!is_array($punches) || empty($punches)) {
            return response()->json(['detail' => 'Invalid or empty punches list.'], 422);
        }

        $results = [];
        $syncedCount = 0;

        foreach ($punches as $item) {
            $clientPunchId = $item['client_punch_id'] ?? null;
            $employeeId = $item['employee_id'] ?? null;
            $deviceUuid = $item['device_uuid'] ?? null;
            $lat = (float)($item['latitude'] ?? 0.0);
            $lon = (float)($item['longitude'] ?? 0.0);
            $mock = !empty($item['is_mock_location']);

            if (!$employeeId || !$deviceUuid) {
                $results[] = ['client_punch_id' => $clientPunchId, 'status' => 'error', 'detail' => 'Missing employee_id or device_uuid'];
                continue;
            }

            if ($mock) {
                $results[] = ['client_punch_id' => $clientPunchId, 'status' => 'rejected', 'detail' => 'Mock location rejected'];
                continue;
            }

            // Check duplicate
            if ($clientPunchId) {
                $existing = PunchLog::where('client_punch_id', $clientPunchId)->first();
                if ($existing) {
                    $results[] = ['client_punch_id' => $clientPunchId, 'status' => 'ok', 'punch_id' => $existing->id];
                    $syncedCount++;
                    continue;
                }
            }

            $timestamp = !empty($item['timestamp']) ? Carbon::parse($item['timestamp']) : Carbon::now('Asia/Jakarta');

            $log = PunchLog::create([
                'employee_id' => $employeeId,
                'device_uuid' => $deviceUuid,
                'timestamp' => $timestamp,
                'latitude' => $lat,
                'longitude' => $lon,
                'is_mock_location' => false,
                'biometric_verified' => $item['biometric_verified'] ?? false,
                'punch_type' => $item['punch_type'] ?? 'In',
                'tz_offset_minutes' => $item['tz_offset_minutes'] ?? 420,
                'client_punch_id' => $clientPunchId,
                'adms_status' => 'pending',
                'notes' => $item['notes'] ?? 'Batch offline sync',
                'gps_time_validated' => true,
            ]);

            $results[] = ['client_punch_id' => $clientPunchId, 'status' => 'ok', 'punch_id' => $log->id];
            $syncedCount++;
        }

        return response()->json([
            'status' => 'ok',
            'synced_count' => $syncedCount,
            'total_submitted' => count($punches),
            'results' => $results,
        ]);
    }
}
