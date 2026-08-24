<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\PunchLog;
use App\Models\DeviceBinding;
use App\Models\Branch;
use App\Models\PunchType;
use App\Services\GeofenceService;
use App\Services\AdmsService;
use Carbon\Carbon;

class PunchController extends Controller
{
    public function __construct(
        protected GeofenceService $geofenceService,
        protected AdmsService $admsService,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => 'required|string',
            'device_uuid' => 'required|string',
            'timestamp' => 'nullable|string',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'is_mock_location' => 'nullable|boolean',
            'biometric_verified' => 'nullable|boolean',
            'punch_type' => 'nullable|string',
            'tz_offset_minutes' => 'nullable|integer',
            'client_punch_id' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        // 1. Mock location rejection
        if (!empty($validated['is_mock_location'])) {
            return response()->json([
                'status' => 'rejected',
                'detail' => 'Mock location detected. Please disable GPS spoofing to clock in.',
            ], 403);
        }

        // 2. Idempotency check via client_punch_id
        if (!empty($validated['client_punch_id'])) {
            $existing = PunchLog::where('client_punch_id', $validated['client_punch_id'])->first();
            if ($existing) {
                return response()->json([
                    'status' => 'ok',
                    'punch_id' => $existing->id,
                    'message' => 'Punch already recorded (duplicate idempotency).',
                    'server_time' => Carbon::now('Asia/Jakarta')->toIso8601String(),
                ]);
            }
        }

        $employeeId = trim($validated['employee_id']);
        $deviceUuid = trim($validated['device_uuid']);
        $lat = (float)$validated['latitude'];
        $lon = (float)$validated['longitude'];
        $requestedType = strtolower(trim($validated['punch_type'] ?? 'auto'));

        // Smart sequence resolution for today
        $todayStart = Carbon::today('Asia/Jakarta');
        $latestPunch = PunchLog::where('employee_id', $employeeId)
            ->where('timestamp', '>=', $todayStart)
            ->orderBy('timestamp', 'desc')
            ->first();

        $resolvedType = 'In';
        if (in_array($requestedType, ['auto', 'in'])) {
            if ($latestPunch) {
                $lastType = strtolower(trim($latestPunch->punch_type));
                $secondsSinceLast = Carbon::now('Asia/Jakarta')->diffInSeconds($latestPunch->timestamp);
                if (in_array($lastType, ['in', 'check in']) && ($secondsSinceLast >= 120 || $requestedType === 'auto')) {
                    $resolvedType = 'Out';
                } else {
                    $resolvedType = 'In';
                }
            } else {
                $resolvedType = 'In';
            }
        } elseif (in_array($requestedType, ['out', 'check out'])) {
            $resolvedType = 'Out';
        } else {
            $resolvedType = ucfirst($requestedType);
        }

        $punchTypeCode = $resolvedType;

        // 3. Find device binding & authorized branches
        $binding = DeviceBinding::with('branches.checkpoints')
            ->where('device_uuid', $deviceUuid)
            ->where('employee_id', $employeeId)
            ->first();

        $branches = $binding?->branches;
        if (!$branches || $branches->isEmpty()) {
            $branches = Branch::where('is_active', true)->with('checkpoints')->get();
        }

        // 4. Geofence verification
        $geoResult = $this->geofenceService->validateLocation($lat, $lon, $branches);

        if (!$geoResult['valid']) {
            return response()->json([
                'status' => 'rejected',
                'detail' => "Location is outside the authorized branch geofence boundary ({$geoResult['distance']}m away).",
                'distance_meters' => $geoResult['distance'],
            ], 422);
        }

        // 5. Create Punch Log
        $timestamp = !empty($validated['timestamp'])
            ? Carbon::parse($validated['timestamp'])
            : Carbon::now('Asia/Jakarta');

        $punchLog = PunchLog::create([
            'employee_id' => $employeeId,
            'device_uuid' => $deviceUuid,
            'timestamp' => $timestamp,
            'latitude' => $lat,
            'longitude' => $lon,
            'is_mock_location' => false,
            'biometric_verified' => $validated['biometric_verified'] ?? false,
            'punch_type' => $punchTypeCode,
            'tz_offset_minutes' => $validated['tz_offset_minutes'] ?? 420,
            'adms_status' => 'pending',
            'client_punch_id' => $validated['client_punch_id'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'gps_time_validated' => true,
        ]);

        // Attempt immediate push to ADMS
        $this->admsService->pushPunchLog($punchLog);

        $matchedBranch = $geoResult['matched_branch'];

        return response()->json([
            'status' => 'ok',
            'punch_id' => $punchLog->id,
            'matched_branch_id' => $matchedBranch?->id,
            'matched_branch_name' => $matchedBranch?->name ?? 'Default Branch',
            'server_time' => Carbon::now('Asia/Jakarta')->toIso8601String(),
            'message' => 'Punch successfully recorded.',
        ]);
    }
}
