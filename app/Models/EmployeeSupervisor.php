<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeSupervisor extends Model
{
    use HasFactory;

    protected $table = 'employee_supervisors';
    public $timestamps = false;

    protected $fillable = [
        'supervisor_id',
        'employee_id',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }

    public function supervisor()
    {
        return $this->belongsTo(Employee::class, 'supervisor_id', 'employee_id');
    }
}
