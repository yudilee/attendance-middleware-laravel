<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Webhook extends Model
{
    use HasFactory;

    protected $table = 'webhooks';
    public $timestamps = false;

    protected $fillable = [
        'url',
        'events',
        'secret',
        'is_active',
        'created_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function deliveries()
    {
        return $this->hasMany(WebhookDelivery::class, 'webhook_id');
    }
}
