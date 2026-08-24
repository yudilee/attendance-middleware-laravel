<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OvertimeRequest extends Model
{
    use HasFactory;

    protected $table = 'overtime_requests';
    public $timestamps = false;

    protected $fillable = [
        'employee_id',
        'date',
        'hours_requested',
        'reason',
        'status',
        'approved_by',
        'created_at',
    ];

    protected $casts = [
        'date' => 'date',
        'hours_requested' => 'float',
        'created_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }
}
