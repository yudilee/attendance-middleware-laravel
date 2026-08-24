<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaveRequest extends Model
{
    use HasFactory;

    protected $table = 'leave_requests';
    public $timestamps = false;

    protected $fillable = [
        'employee_id',
        'category',
        'leave_type',
        'permit_type',
        'start_date',
        'end_date',
        'expected_time',
        'reason',
        'attachment_path',
        'status',
        'approved_by',
        'admin_notes',
        'processed_at',
        'processed_by',
        'created_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'created_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }
}
