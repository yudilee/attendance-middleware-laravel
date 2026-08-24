<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PunchLog extends Model
{
    use HasFactory;

    protected $table = 'punch_logs';
    public $timestamps = false;

    protected $fillable = [
        'employee_id',
        'device_uuid',
        'timestamp',
        'latitude',
        'longitude',
        'is_mock_location',
        'biometric_verified',
        'punch_type',
        'tz_offset_minutes',
        'adms_status',
        'client_punch_id',
        'gps_time_validated',
        'notes',
        'selfie_filename',
        'server_sync_status',
        'synced_at',
        'sync_error',
        'sync_retry_count',
        'is_auto_generated',
    ];

    protected $casts = [
        'timestamp' => 'datetime',
        'latitude' => 'float',
        'longitude' => 'float',
        'is_mock_location' => 'boolean',
        'biometric_verified' => 'boolean',
        'gps_time_validated' => 'boolean',
        'is_auto_generated' => 'boolean',
        'tz_offset_minutes' => 'integer',
        'sync_retry_count' => 'integer',
        'synced_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }

    public function corrections()
    {
        return $this->hasMany(AttendanceCorrection::class, 'original_punch_id');
    }
}
