<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdmsTarget extends Model
{
    use HasFactory;

    protected $table = 'adms_targets';
    public $timestamps = false;

    protected $fillable = [
        'server_url',
        'serial_number',
        'device_name',
        'is_active',
        'timezone_offset',
        'last_contact',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'timezone_offset' => 'integer',
        'last_contact' => 'datetime',
    ];
}
