<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaveBalance extends Model
{
    use HasFactory;

    protected $table = 'leave_balances';
    public $timestamps = false;

    protected $fillable = [
        'employee_id',
        'annual_total',
        'annual_used',
        'sick_total',
        'sick_used',
        'year',
    ];

    protected $casts = [
        'annual_total' => 'integer',
        'annual_used' => 'integer',
        'sick_total' => 'integer',
        'sick_used' => 'integer',
        'year' => 'integer',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }
}
