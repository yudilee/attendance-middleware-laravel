<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\PunchLog;
use Carbon\Carbon;

class PunchHistoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $employeeId = $request->query('employee_id');
        if (!$employeeId) {
            return response()->json(['detail' => 'employee_id query parameter required.'], 422);
        }

        $limit = (int)($request->query('limit', 50));
        $punches = PunchLog::where('employee_id', $employeeId)
            ->orderBy('timestamp', 'desc')
            ->limit($limit)
            ->get();

        $data = $punches->map(function ($p) {
            return [
                'id' => $p->id,
                'punch_type' => $p->punch_type,
                'timestamp' => $p->timestamp->toIso8601String(),
                'latitude' => $p->latitude,
                'longitude' => $p->longitude,
                'adms_status' => $p->adms_status,
                'biometric_verified' => $p->biometric_verified,
                'selfie_filename' => $p->selfie_filename,
                'notes' => $p->notes,
            ];
        });

        return response()->json([
            'status' => 'ok',
            'employee_id' => $employeeId,
            'count' => count($data),
            'data' => $data,
        ]);
    }
}
