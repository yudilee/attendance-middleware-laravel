<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CanvassPlan;
use Illuminate\Http\Request;

class CustomerApiController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->query('search');
        $type = $request->query('type');
        $employeeId = $request->query('employee_id');

        $query = Customer::where('is_active', true);

        if ($search) {
            $isPgsql = config('database.default') === 'pgsql';
            $likeOp = $isPgsql ? 'ilike' : 'like';
            $query->where(function ($q) use ($search, $likeOp) {
                $q->where('name', $likeOp, "%{$search}%")
                  ->orWhere('city', $likeOp, "%{$search}%")
                  ->orWhere('address', $likeOp, "%{$search}%");
            });
        }

        if ($type) {
            $query->where('customer_type', $type);
        }

        if ($employeeId) {
            $query->where('assigned_employee_id', $employeeId);
        }

        $customers = $query->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'customers' => $customers,
        ]);
    }

    public function todayCanvassPlan(Request $request)
    {
        $employeeId = $request->query('employee_id');
        if (!$employeeId) {
            return response()->json(['success' => false, 'message' => 'employee_id is required'], 400);
        }

        $today = now()->toDateString();
        $plan = CanvassPlan::where('employee_id', $employeeId)
            ->where('plan_date', $today)
            ->first();

        if (!$plan) {
            return response()->json([
                'success' => true,
                'has_plan' => false,
                'plan' => null,
            ]);
        }

        $customers = [];
        if (!empty($plan->customer_ids)) {
            $customers = Customer::whereIn('id', $plan->customer_ids)->get();
        }

        return response()->json([
            'success' => true,
            'has_plan' => true,
            'plan' => [
                'id' => $plan->id,
                'plan_date' => $plan->plan_date,
                'target_visits' => $plan->target_visits,
                'actual_visits' => $plan->actual_visits,
                'notes' => $plan->notes,
                'customers' => $customers,
            ],
        ]);
    }
}
