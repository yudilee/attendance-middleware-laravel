<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppConfig;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AppSettingController extends Controller
{
    /**
     * Display general system settings and mobile app release management.
     */
    public function index(Request $request): Response
    {
        $configs = AppConfig::all()->pluck('value', 'key');

        return Inertia::render('Admin/Settings/Index', [
            'settings' => [
                'late_grace_period_minutes' => (int) ($configs['late_grace_period_minutes'] ?? 15),
                'default_shift_start' => $configs['default_shift_start'] ?? '08:00',
                'app_latest_version' => $configs['app_latest_version'] ?? '1.0.0',
                'app_min_version' => $configs['app_min_version'] ?? '1.0.0',
                'app_force_update' => filter_var($configs['app_force_update'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'app_download_url' => $configs['app_download_url'] ?? '',
                'app_changelog' => $configs['app_changelog'] ?? "• Initial release with GPS geofencing & biometric verification.\n• Offline punch caching and automatic sync.\n• Real-time ADMS server integration.",
            ],
        ]);
    }

    /**
     * Update system settings and mobile release configurations.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'late_grace_period_minutes' => 'required|integer|min:0|max:180',
            'default_shift_start' => 'required|string|max:5',
            'app_latest_version' => 'required|string|max:20',
            'app_min_version' => 'required|string|max:20',
            'app_force_update' => 'required|boolean',
            'app_download_url' => 'nullable|string|url|max:500',
            'app_changelog' => 'nullable|string|max:5000',
        ]);

        foreach ($validated as $key => $value) {
            $valStr = is_bool($value) ? ($value ? 'true' : 'false') : (string) ($value ?? '');
            AppConfig::updateOrCreate(
                ['key' => $key],
                ['value' => $valStr, 'description' => "System setting for {$key}"]
            );
        }

        return redirect()->back()->with('success', 'System configurations and mobile release info updated successfully.');
    }
}
