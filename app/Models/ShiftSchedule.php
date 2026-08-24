<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShiftSchedule extends Model
{
    use HasFactory;

    protected $table = 'shift_schedules';
    public $timestamps = false;

    protected $fillable = [
        'name',
        'start_time',
        'end_time',
        'grace_minutes',
        'min_work_hours',
        'overtime_after_hours',
        'working_days',
        'is_default',
        'schedule_type',
        'interval_days',
        'anchor_date',
        'overtime_multiplier_1',
        'overtime_multiplier_2',
        'overtime_threshold_2_hours',
        'weekend_overtime_multiplier',
        'holiday_overtime_multiplier',
        'monthly_overtime_cap_hours',
        'auto_clockout_enabled',
        'auto_clockout_buffer_minutes',
        'created_at',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'auto_clockout_enabled' => 'boolean',
        'anchor_date' => 'date',
        'created_at' => 'datetime',
        'grace_minutes' => 'integer',
        'min_work_hours' => 'float',
        'overtime_after_hours' => 'float',
        'overtime_multiplier_1' => 'float',
        'overtime_multiplier_2' => 'float',
        'overtime_threshold_2_hours' => 'float',
        'weekend_overtime_multiplier' => 'float',
        'holiday_overtime_multiplier' => 'float',
        'monthly_overtime_cap_hours' => 'float',
    ];

    public function companies()
    {
        return $this->hasMany(Company::class, 'shift_schedule_id');
    }

    public function branches()
    {
        return $this->hasMany(Branch::class, 'shift_schedule_id');
    }

    public function employeeGroups()
    {
        return $this->hasMany(EmployeeGroup::class, 'shift_schedule_id');
    }

    public function employees()
    {
        return $this->hasMany(Employee::class, 'shift_schedule_id');
    }
}
