<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\AuditLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->query('search');

        $driver = DB::connection()->getDriverName();
        $likeOp = $driver === 'pgsql' ? 'ilike' : 'like';

        $query = AuditLog::orderBy('created_at', 'desc');

        if ($search) {
            $query->where(function ($q) use ($search, $likeOp) {
                $q->where('admin_username', $likeOp, "%{$search}%")
                  ->orWhere('action', $likeOp, "%{$search}%")
                  ->orWhere('target_type', $likeOp, "%{$search}%")
                  ->orWhere('details', $likeOp, "%{$search}%");
            });
        }

        $logs = $query->paginate(30)->through(function ($l) {
            return [
                'id' => $l->id,
                'admin_username' => $l->admin_username ?? 'System',
                'action' => $l->action,
                'target_type' => $l->target_type,
                'target_id' => $l->target_id,
                'details' => $l->details,
                'ip_address' => $l->ip_address,
                'created_at' => $l->created_at ? $l->created_at->format('Y-m-d H:i:s') : '-',
            ];
        });

        return Inertia::render('Admin/AuditLogs/Index', [
            'logs' => $logs,
            'filters' => [
                'search' => $search ?? '',
            ],
        ]);
    }
}
