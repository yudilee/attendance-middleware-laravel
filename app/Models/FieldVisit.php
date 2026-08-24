<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FieldVisit extends Model
{
    use HasFactory;

    protected $table = 'field_visits';

    protected $fillable = [
        'employee_id',
        'customer_id',
        'visit_type',
        'purpose',
        'check_in_at',
        'check_in_lat',
        'check_in_lng',
        'check_out_at',
        'check_out_lat',
        'check_out_lng',
        'duration_minutes',
        'total_distance_km',
        'status',
        'notes',
        'result',
        'device_uuid',
        'is_mock_location',
        'odoo_activity_id',
        'odoo_lead_id',
        'odoo_last_synced_at',
    ];

    protected $casts = [
        'check_in_at' => 'datetime',
        'check_out_at' => 'datetime',
        'check_in_lat' => 'float',
        'check_in_lng' => 'float',
        'check_out_lat' => 'float',
        'check_out_lng' => 'float',
        'duration_minutes' => 'integer',
        'total_distance_km' => 'float',
        'is_mock_location' => 'boolean',
        'odoo_activity_id' => 'integer',
        'odoo_lead_id' => 'integer',
        'odoo_last_synced_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function photos()
    {
        return $this->hasMany(FieldVisitPhoto::class, 'field_visit_id');
    }

    public function tasks()
    {
        return $this->hasMany(FieldTask::class, 'field_visit_id');
    }

    public function breadcrumbs()
    {
        return $this->hasMany(FieldVisitBreadcrumb::class, 'field_visit_id')->orderBy('recorded_at', 'asc');
    }

    /**
     * Calculate and update total distance (km) using Haversine formula across breadcrumbs
     */
    public function recalculateDistance(): float
    {
        $points = $this->breadcrumbs()->get(['latitude', 'longitude'])->toArray();
        if (count($points) < 2) {
            // Fallback to straight line from check_in to check_out
            if ($this->check_in_lat && $this->check_out_lat) {
                $dist = $this->haversineGreatCircleDistance($this->check_in_lat, $this->check_in_lng, $this->check_out_lat, $this->check_out_lng);
                $this->update(['total_distance_km' => round($dist, 2)]);
                return round($dist, 2);
            }
            return 0.0;
        }

        $totalKm = 0.0;
        for ($i = 0; $i < count($points) - 1; $i++) {
            $totalKm += $this->haversineGreatCircleDistance(
                $points[$i]['latitude'],
                $points[$i]['longitude'],
                $points[$i + 1]['latitude'],
                $points[$i + 1]['longitude']
            );
        }

        $totalKm = round($totalKm, 2);
        $this->update(['total_distance_km' => $totalKm]);
        return $totalKm;
    }

    private function haversineGreatCircleDistance($latitudeFrom, $longitudeFrom, $latitudeTo, $longitudeTo, $earthRadius = 6371)
    {
        $latFrom = deg2rad($latitudeFrom);
        $lonFrom = deg2rad($longitudeFrom);
        $latTo = deg2rad($latitudeTo);
        $lonTo = deg2rad($longitudeTo);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
            cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));
        return $angle * $earthRadius;
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeToday($query)
    {
        return $query->whereDate('check_in_at', today());
    }
}
