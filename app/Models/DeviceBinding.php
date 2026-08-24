<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeviceBinding extends Model
{
    use HasFactory;

    protected $table = 'device_bindings';
    public $timestamps = false;

    protected $fillable = [
        'employee_id',
        'device_uuid',
        'branch_id',
        'api_key_id',
        'device_label',
        'registration_status',
        'approved_at',
        'approved_by',
        'notes',
        'is_active',
        'fcm_token',
        'device_secret',
        'created_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'approved_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }

    public function apiKey()
    {
        return $this->belongsTo(ApiKey::class, 'api_key_id');
    }

    public function branches()
    {
        return $this->belongsToMany(
            Branch::class,
            'device_branch_assignments',
            'binding_id',
            'branch_id'
        )->withPivot('assigned_at');
    }
}
