<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScheduleAssignment extends Model
{
    use HasFactory;

    protected $table = 'schedule_assignments';
    public $timestamps = false;

    protected $fillable = [
        'employee_id',
        'shift_schedule_id',
        'effective_date',
        'end_date',
        'created_by',
        'created_at',
    ];

    protected $casts = [
        'effective_date' => 'date',
        'end_date' => 'date',
        'created_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }

    public function shiftSchedule()
    {
        return $this->belongsTo(ShiftSchedule::class, 'shift_schedule_id');
    }
}
