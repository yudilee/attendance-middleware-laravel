<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    use HasFactory;

    protected $table = 'branches';
    public $timestamps = false;

    protected $fillable = [
        'name',
        'latitude',
        'longitude',
        'radius_meters',
        'is_active',
        'geofence_type',
        'polygon_coordinates',
        'qr_code_enabled',
        'qr_code_data',
        'nfc_enabled',
        'nfc_tag_data',
        'company_id',
        'shift_schedule_id',
        'timezone_offset',
        'timezone_name',
        'updated_at',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'radius_meters' => 'float',
        'is_active' => 'boolean',
        'qr_code_enabled' => 'boolean',
        'nfc_enabled' => 'boolean',
        'timezone_offset' => 'integer',
        'updated_at' => 'datetime',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function shiftSchedule()
    {
        return $this->belongsTo(ShiftSchedule::class, 'shift_schedule_id');
    }

    public function checkpoints()
    {
        return $this->hasMany(BranchCheckpoint::class, 'branch_id');
    }

    public function employeeGroups()
    {
        return $this->hasMany(EmployeeGroup::class, 'branch_id');
    }

    public function deviceBindings()
    {
        return $this->belongsToMany(
            DeviceBinding::class,
            'device_branch_assignments',
            'branch_id',
            'binding_id'
        )->withPivot('assigned_at');
    }
}
