<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeGroup extends Model
{
    use HasFactory;

    protected $table = 'employee_groups';

    protected $fillable = [
        'name',
        'branch_id',
        'shift_schedule_id',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function shiftSchedule()
    {
        return $this->belongsTo(ShiftSchedule::class, 'shift_schedule_id');
    }

    public function employees()
    {
        return $this->hasMany(Employee::class, 'group_id');
    }
}
