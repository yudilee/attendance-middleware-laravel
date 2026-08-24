<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppConfig;
use App\Models\OdooSyncLog;
use App\Services\OdooService;
use App\Services\OdooSyncService;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OdooSyncController extends Controller
{
    public function index(OdooService $odoo)
    {
        $settings = [
            'enabled' => AppConfig::where('key', 'odoo_sync_enabled')->value('value') === '1'
                ?: config('odoo.enabled', false),
            'url' => AppConfig::where('key', 'odoo_url')->value('value') ?: config('odoo.url'),
            'db' => AppConfig::where('key', 'odoo_db')->value('value') ?: config('odoo.db'),
            'username' => AppConfig::where('key', 'odoo_username')->value('value') ?: config('odoo.username'),
            'password' => AppConfig::where('key', 'odoo_password')->value('value') ? '••••••••' : '',
            'sync_interval' => (int) (AppConfig::where('key', 'odoo_sync_interval')->value('value') ?: config('odoo.sync_interval', 15)),
        ];

        $logs = OdooSyncLog::orderBy('started_at', 'desc')->paginate(15);

        return Inertia::render('Admin/OdooSync/Index', [
            'settings' => $settings,
            'logs' => $logs,
        ]);
    }

    public function updateSettings(Request $request)
    {
        $validated = $request->validate([
            'enabled' => 'required|boolean',
            'url' => 'required|url',
            'db' => 'required|string|max:100',
            'username' => 'required|string|max:100',
            'password' => 'nullable|string|max:100',
            'sync_interval' => 'required|integer|min:5|max:1440',
        ]);

        AppConfig::updateOrCreate(['key' => 'odoo_sync_enabled'], ['value' => $validated['enabled'] ? '1' : '0', 'description' => 'Enable Odoo Sync']);
        AppConfig::updateOrCreate(['key' => 'odoo_url'], ['value' => $validated['url'], 'description' => 'Odoo Server URL']);
        AppConfig::updateOrCreate(['key' => 'odoo_db'], ['value' => $validated['db'], 'description' => 'Odoo Database Name']);
        AppConfig::updateOrCreate(['key' => 'odoo_username'], ['value' => $validated['username'], 'description' => 'Odoo API Username']);
        AppConfig::updateOrCreate(['key' => 'odoo_sync_interval'], ['value' => (string)$validated['sync_interval'], 'description' => 'Sync Interval Minutes']);

        if (!empty($validated['password']) && $validated['password'] !== '••••••••') {
            AppConfig::updateOrCreate(['key' => 'odoo_password'], ['value' => $validated['password'], 'description' => 'Odoo API Password']);
        }

        AuditLog::create([
            'admin_username' => auth()->user()->name ?? 'Administrator',
            'action' => 'Updated Odoo Integration Settings',
            'target_type' => 'AppConfig',
            'target_id' => 'odoo_sync',
            'details' => "Updated Odoo URL: {$validated['url']} (Enabled: " . ($validated['enabled'] ? 'Yes' : 'No') . ")",
            'ip_address' => $request->ip(),
        ]);

        return redirect()->back()->with('success', 'Odoo connection settings updated successfully.');
    }

    public function testConnection(OdooService $odoo)
    {
        $res = $odoo->testConnection();
        return response()->json($res);
    }

    public function triggerSync(Request $request, OdooSyncService $sync)
    {
        $type = $request->input('type', 'full');

        switch ($type) {
            case 'customers_pull':
                $log = $sync->pullCustomers();
                break;
            case 'customers_push':
                $log = $sync->pushCustomers();
                break;
            case 'visits_push':
                $log = $sync->pushVisits();
                break;
            case 'employees_pull':
                $log = $sync->pullEmployees();
                break;
            case 'full':
            default:
                $res = $sync->runFullSync();
                break;
        }

        return redirect()->back()->with('success', "Sync operation '{$type}' triggered successfully.");
    }
}
