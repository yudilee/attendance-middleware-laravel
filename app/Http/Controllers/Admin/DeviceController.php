<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\DeviceBinding;
use App\Models\BiometricTerminal;
use App\Models\Employee;
use App\Models\Branch;
use App\Models\ApiKey;
use Carbon\Carbon;
use Illuminate\Support\Str;

class DeviceController extends Controller
{
    public function index(Request $request): Response
    {
        $statusFilter = $request->query('status');
        $search = $request->query('search');

        $query = DeviceBinding::with(['employee', 'branches', 'apiKey'])
            ->orderBy('created_at', 'desc');

        if ($statusFilter && $statusFilter !== 'all') {
            $query->where('registration_status', $statusFilter);
        }

        $driver = \DB::connection()->getDriverName();
        $likeOp = $driver === 'pgsql' ? 'ilike' : 'like';

        if ($search) {
            $query->where(function ($q) use ($search, $likeOp) {
                $q->where('employee_id', $likeOp, "%{$search}%")
                  ->orWhere('device_label', $likeOp, "%{$search}%")
                  ->orWhere('device_uuid', $likeOp, "%{$search}%")
                  ->orWhereHas('employee', function ($eq) use ($search, $likeOp) {
                      $eq->where('full_name', $likeOp, "%{$search}%");
                  });
            });
        }

        $devices = $query->paginate(20)->through(function ($d) {
            return [
                'id' => $d->id,
                'employee_id' => $d->employee_id,
                'employee_name' => $d->employee?->full_name ?? 'Unassigned',
                'department' => $d->employee?->department ?? '-',
                'device_label' => $d->device_label ?? 'Mobile Device',
                'device_uuid' => $d->device_uuid,
                'registration_status' => $d->registration_status,
                'is_active' => $d->is_active,
                'approved_at' => $d->approved_at?->format('Y-m-d H:i'),
                'approved_by' => $d->approved_by,
                'created_at' => $d->created_at?->format('Y-m-d H:i'),
                'branches' => $d->branches->pluck('name'),
                'api_key_label' => $d->apiKey?->label,
            ];
        });

        $terminals = BiometricTerminal::with('branch')
            ->orderBy('last_activity_at', 'desc')
            ->get()
            ->map(function ($t) {
                return [
                    'id' => $t->id,
                    'device_sn' => $t->device_sn,
                    'device_name' => $t->device_name,
                    'ip_address' => $t->ip_address,
                    'branch_id' => $t->branch_id,
                    'branch_name' => $t->branch?->name,
                    'is_active' => $t->is_active,
                    'last_activity_at' => $t->last_activity_at?->format('Y-m-d H:i:s'),
                ];
            });

        $branches = Branch::where('is_active', true)->get(['id', 'name']);

        return Inertia::render('Admin/Devices/Index', [
            'devices' => $devices,
            'terminals' => $terminals,
            'branches' => $branches,
            'filters' => [
                'status' => $statusFilter ?? 'all',
                'search' => $search ?? '',
            ],
        ]);
    }

    public function storeTerminal(Request $request)
    {
        $validated = $request->validate([
            'device_sn' => 'required|string|max:100|unique:biometric_terminals,device_sn',
            'device_name' => 'nullable|string|max:150',
            'ip_address' => 'nullable|string|max:50',
            'branch_id' => 'nullable|exists:branches,id',
            'is_active' => 'boolean',
        ]);

        BiometricTerminal::create($validated);

        return back()->with('success', 'Biometric terminal registered successfully.');
    }

    public function updateTerminal(Request $request, BiometricTerminal $terminal)
    {
        $validated = $request->validate([
            'device_name' => 'nullable|string|max:150',
            'ip_address' => 'nullable|string|max:50',
            'branch_id' => 'nullable|exists:branches,id',
            'is_active' => 'boolean',
        ]);

        $terminal->update($validated);

        // Retroactively update recent punch logs from this device to reflect the branch
        if (isset($validated['branch_id'])) {
            \App\Models\PunchLog::where('device_sn', $terminal->device_sn)
                ->where('timestamp', '>=', Carbon::now()->subDays(30))
                ->update(['branch_id' => $validated['branch_id']]);
        }

        return back()->with('success', "Terminal {$terminal->device_sn} updated.");
    }

    public function destroyTerminal(BiometricTerminal $terminal)
    {
        $terminal->delete();

        return back()->with('success', "Biometric terminal {$terminal->device_sn} removed.");
    }

    public function approve(DeviceBinding $device)
    {
        $device->update([
            'registration_status' => 'approved',
            'approved_at' => Carbon::now(),
            'approved_by' => auth()->user()?->name ?? 'admin',
            'is_active' => true,
        ]);

        if ($device->branches()->count() === 0) {
            $device->branches()->sync(Branch::where('is_active', true)->pluck('id'));
        }

        return back()->with('success', "Device for employee {$device->employee_id} has been approved.");
    }

    public function suspend(DeviceBinding $device)
    {
        $device->update([
            'registration_status' => 'suspended',
            'is_active' => false,
        ]);

        return back()->with('success', "Device {$device->device_uuid} suspended.");
    }

    public function destroy(DeviceBinding $device)
    {
        $device->branches()->detach();
        $device->delete();

        return back()->with('success', "Device binding deleted.");
    }

    public function generateQr(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|string',
            'label' => 'nullable|string',
            'branch_ids' => 'nullable|array',
        ]);

        $plainKey = 'mob_' . Str::random(32);
        $hashedKey = 'sha256:' . hash('sha256', $plainKey);

        $emp = Employee::where('employee_id', $validated['employee_id'])->first();
        $customLabel = !empty($validated['label']) ? $validated['label'] : (($emp?->full_name ?? $validated['employee_id']) . "'s Device");
        $storedLabel = "[PIN: {$validated['employee_id']}] {$customLabel}";

        $apiKey = ApiKey::create([
            'key_value' => $hashedKey,
            'label' => $storedLabel,
            'is_active' => true,
            'created_at' => Carbon::now(),
        ]);

        $serverUrl = config('app.url', 'https://attendance.hartonomotor-group.com');

        $qrPayload = [
            'url' => $serverUrl,
            'server_url' => $serverUrl,
            'token' => $plainKey,
            'api_key' => $plainKey,
            'key' => $plainKey,
            'employee_id' => $validated['employee_id'],
            'employee_name' => $emp?->full_name ?? '',
            'created_at' => Carbon::now()->toIso8601String(),
        ];

        return response()->json([
            'status' => 'ok',
            'qr_data' => json_encode($qrPayload),
            'raw_payload' => $qrPayload,
        ]);
    }
}
