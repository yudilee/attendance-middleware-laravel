<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceCorrection extends Model
{
    use HasFactory;

    protected $table = 'attendance_corrections';

    protected $fillable = [
        'employee_id',
        'original_punch_id',
        'correction_type',
        'description',
        'proposed_timestamp',
        'proposed_punch_type',
        'status',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
    ];

    protected $casts = [
        'proposed_timestamp' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }

    public function punchLog()
    {
        return $this->belongsTo(PunchLog::class, 'original_punch_id');
    }
}
