<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CanvassPlan extends Model
{
    use HasFactory;

    protected $table = 'canvass_plans';

    protected $fillable = [
        'employee_id',
        'plan_date',
        'target_visits',
        'actual_visits',
        'customer_ids',
        'route_order',
        'notes',
        'status',
        'created_by',
    ];

    protected $casts = [
        'plan_date' => 'date',
        'target_visits' => 'integer',
        'actual_visits' => 'integer',
        'customer_ids' => 'array',
        'route_order' => 'array',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }

    public function getCustomersAttribute()
    {
        if (empty($this->customer_ids)) {
            return collect();
        }
        return Customer::whereIn('id', $this->customer_ids)->get();
    }
}
