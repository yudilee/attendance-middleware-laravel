<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PunchType extends Model
{
    use HasFactory;

    protected $table = 'punch_types';
    public $timestamps = false;

    protected $fillable = [
        'code',
        'label',
        'adms_status_code',
        'is_active',
        'display_order',
        'icon',
        'color_hex',
        'requires_geofence',
        'created_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'requires_geofence' => 'boolean',
        'display_order' => 'integer',
        'created_at' => 'datetime',
    ];
}
