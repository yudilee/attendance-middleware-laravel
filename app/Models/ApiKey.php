<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApiKey extends Model
{
    use HasFactory;

    protected $table = 'api_keys';
    public $timestamps = false;

    protected $fillable = [
        'key_value',
        'label',
        'is_active',
        'created_at',
        'last_used_at',
        'last_used_ip',
        'expires_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function deviceBindings()
    {
        return $this->hasMany(DeviceBinding::class, 'api_key_id');
    }
}
