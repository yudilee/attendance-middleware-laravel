<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\AppConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppUpdateController extends Controller
{
    /**
     * Check if a newer version of the mobile app is available.
     */
    public function check(Request $request): JsonResponse
    {
        $currentVersion = $request->query('current_version', '1.0.0');
        $configs = AppConfig::all()->pluck('value', 'key');

        $latestVersion = $configs['app_latest_version'] ?? '1.0.0';
        $minVersion = $configs['app_min_version'] ?? '1.0.0';
        $forceUpdateSetting = filter_var($configs['app_force_update'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $downloadUrl = $configs['app_download_url'] ?? '';
        $changelog = $configs['app_changelog'] ?? "• Performance improvements and bug fixes.";

        // Compare semantic version numbers
        $hasUpdate = version_compare($latestVersion, $currentVersion, '>');
        $isBelowMin = version_compare($currentVersion, $minVersion, '<');
        $isForced = $hasUpdate && ($forceUpdateSetting || $isBelowMin);

        return response()->json([
            'status' => 'ok',
            'has_update' => $hasUpdate,
            'is_forced' => $isForced,
            'latest_version' => $latestVersion,
            'min_supported_version' => $minVersion,
            'current_version' => $currentVersion,
            'download_url' => $downloadUrl,
            'changelog' => $changelog,
            'title' => $isForced ? 'Important Update Required' : 'New Update Available',
        ]);
    }
}
