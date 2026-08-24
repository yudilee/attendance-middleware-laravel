<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\FieldVisit;
use App\Models\FieldVisitPhoto;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FieldVisitApiController extends Controller
{
    public function checkIn(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|string|exists:employees,employee_id',
            'customer_id' => 'nullable|exists:customers,id',
            'visit_type' => 'required|string|in:storing,canvassing,delivery,service,survey,other',
            'purpose' => 'nullable|string',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'device_uuid' => 'nullable|string',
            'is_mock_location' => 'boolean',
        ]);

        $visit = FieldVisit::create([
            'employee_id' => $validated['employee_id'],
            'customer_id' => $validated['customer_id'] ?? null,
            'visit_type' => $validated['visit_type'],
            'purpose' => $validated['purpose'] ?? null,
            'check_in_at' => now(),
            'check_in_lat' => $validated['latitude'],
            'check_in_lng' => $validated['longitude'],
            'status' => 'in_progress',
            'device_uuid' => $validated['device_uuid'] ?? null,
            'is_mock_location' => $validated['is_mock_location'] ?? false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Field check-in recorded successfully.',
            'visit_id' => $visit->id,
            'check_in_at' => $visit->check_in_at->toIso8601String(),
        ], 201);
    }

    public function checkOut(Request $request)
    {
        $validated = $request->validate([
            'visit_id' => 'required|exists:field_visits,id',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'notes' => 'nullable|string',
            'result' => 'nullable|string',
        ]);

        $visit = FieldVisit::findOrFail($validated['visit_id']);

        $checkOutTime = now();
        $durationMinutes = $visit->check_in_at ? (int) $visit->check_in_at->diffInMinutes($checkOutTime) : null;

        $visit->update([
            'check_out_at' => $checkOutTime,
            'check_out_lat' => $validated['latitude'],
            'check_out_lng' => $validated['longitude'],
            'duration_minutes' => $durationMinutes,
            'status' => 'completed',
            'notes' => $validated['notes'] ?? $visit->notes,
            'result' => $validated['result'] ?? null,
        ]);

        // Auto-recalculate total distance traveled
        $distanceKm = $visit->recalculateDistance();

        return response()->json([
            'success' => true,
            'message' => 'Field check-out completed successfully.',
            'visit_id' => $visit->id,
            'duration_minutes' => $durationMinutes,
            'total_distance_km' => $distanceKm,
            'check_out_at' => $checkOutTime->toIso8601String(),
        ]);
    }

    /**
     * Record single or batch GPS breadcrumb pings during an active visit
     */
    public function recordBreadcrumbs(Request $request, $id)
    {
        $visit = FieldVisit::findOrFail($id);

        $points = [];
        if ($request->has('breadcrumbs') && is_array($request->input('breadcrumbs'))) {
            // Batch upload
            $points = $request->input('breadcrumbs');
        } elseif ($request->has('latitude') && $request->has('longitude')) {
            // Single point upload
            $points[] = [
                'latitude' => $request->input('latitude'),
                'longitude' => $request->input('longitude'),
                'speed' => $request->input('speed'),
                'accuracy' => $request->input('accuracy'),
                'heading' => $request->input('heading'),
                'recorded_at' => $request->input('recorded_at') ?? now(),
            ];
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Missing coordinates (latitude/longitude or breadcrumbs array required).',
            ], 422);
        }

        $insertedCount = 0;
        foreach ($points as $pt) {
            if (isset($pt['latitude'], $pt['longitude'])) {
                $visit->breadcrumbs()->create([
                    'latitude' => (float) $pt['latitude'],
                    'longitude' => (float) $pt['longitude'],
                    'speed' => isset($pt['speed']) ? (float) $pt['speed'] : null,
                    'accuracy' => isset($pt['accuracy']) ? (float) $pt['accuracy'] : null,
                    'heading' => isset($pt['heading']) ? (float) $pt['heading'] : null,
                    'recorded_at' => isset($pt['recorded_at']) ? Carbon::parse($pt['recorded_at']) : now(),
                ]);
                $insertedCount++;
            }
        }

        $totalDistance = $visit->recalculateDistance();

        return response()->json([
            'success' => true,
            'message' => "Successfully recorded {$insertedCount} GPS waypoint(s).",
            'total_distance_km' => $totalDistance,
            'total_waypoints' => $visit->breadcrumbs()->count(),
        ]);
    }

    /**
     * Retrieve GPS breadcrumb route for a specific field visit
     */
    public function getBreadcrumbs($id)
    {
        $visit = FieldVisit::with(['customer', 'employee:id,employee_id,name'])
            ->findOrFail($id);

        $breadcrumbs = $visit->breadcrumbs()
            ->select(['id', 'latitude', 'longitude', 'speed', 'accuracy', 'heading', 'recorded_at'])
            ->get();

        return response()->json([
            'success' => true,
            'visit_id' => $visit->id,
            'visit_type' => $visit->visit_type,
            'employee' => $visit->employee,
            'customer' => $visit->customer,
            'check_in' => [
                'at' => $visit->check_in_at?->toIso8601String(),
                'lat' => $visit->check_in_lat,
                'lng' => $visit->check_in_lng,
            ],
            'check_out' => [
                'at' => $visit->check_out_at?->toIso8601String(),
                'lat' => $visit->check_out_lat,
                'lng' => $visit->check_out_lng,
            ],
            'duration_minutes' => $visit->duration_minutes,
            'total_distance_km' => $visit->total_distance_km,
            'waypoints_count' => $breadcrumbs->count(),
            'breadcrumbs' => $breadcrumbs,
        ]);
    }

    public function uploadPhoto(Request $request, $id)
    {
        $request->validate([
            'photo' => 'required|image|max:10240', // 10MB max
            'caption' => 'nullable|string|max:200',
            'photo_type' => 'nullable|string|in:check_in,check_out,evidence,before,after',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);

        $visit = FieldVisit::findOrFail($id);

        $file = $request->file('photo');
        $filename = $file->store('visit-photos', 'public');

        $photo = FieldVisitPhoto::create([
            'field_visit_id' => $visit->id,
            'filename' => $filename,
            'caption' => $request->input('caption'),
            'photo_type' => $request->input('photo_type', 'evidence'),
            'latitude' => $request->input('latitude'),
            'longitude' => $request->input('longitude'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Photo uploaded successfully.',
            'photo_id' => $photo->id,
            'url' => Storage::url($filename),
        ]);
    }

    public function history(Request $request)
    {
        $employeeId = $request->query('employee_id');
        $date = $request->query('date');

        $query = FieldVisit::with(['customer', 'photos'])
            ->orderBy('check_in_at', 'desc');

        if ($employeeId) {
            $query->where('employee_id', $employeeId);
        }

        if ($date) {
            $query->whereDate('check_in_at', $date);
        }

        $visits = $query->paginate(20);

        return response()->json([
            'success' => true,
            'visits' => $visits,
        ]);
    }
}
