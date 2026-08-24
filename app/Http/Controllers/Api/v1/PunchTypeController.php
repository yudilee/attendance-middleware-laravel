<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\PunchType;

class PunchTypeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $types = PunchType::where('is_active', true)
            ->orderBy('display_order')
            ->get();

        if ($types->isEmpty()) {
            // Return default seed if none in DB
            $types = [
                ['code' => 'In', 'label' => 'Clock In', 'icon' => 'login', 'color_hex' => '#22c55e'],
                ['code' => 'Out', 'label' => 'Clock Out', 'icon' => 'logout', 'color_hex' => '#dc2626'],
            ];
        }

        return response()->json(['status' => 'ok', 'data' => $types]);
    }
}
