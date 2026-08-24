<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\AppConfig;
use Carbon\Carbon;

class AppStatusController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $minVersion = AppConfig::where('key', 'min_app_version')->value('value') ?? '1.0.0';
        $latestVersion = AppConfig::where('key', 'latest_app_version')->value('value') ?? '1.2.0';
        $maintenance = AppConfig::where('key', 'maintenance_mode')->value('value') === 'true';

        return response()->json([
            'status' => $maintenance ? 'maintenance' : 'ok',
            'min_app_version' => $minVersion,
            'latest_app_version' => $latestVersion,
            'server_time' => Carbon::now('Asia/Jakarta')->toIso8601String(),
            'timezone' => 'Asia/Jakarta',
            'maintenance' => $maintenance,
        ]);
    }
}
