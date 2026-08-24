<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    use HasFactory;

    protected $table = 'companies';

    protected $fillable = [
        'name',
        'code',
        'is_active',
        'shift_schedule_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function shiftSchedule()
    {
        return $this->belongsTo(ShiftSchedule::class, 'shift_schedule_id');
    }

    public function branches()
    {
        return $this->hasMany(Branch::class, 'company_id');
    }

    public function employees()
    {
        return $this->hasMany(Employee::class, 'company_id');
    }
}
