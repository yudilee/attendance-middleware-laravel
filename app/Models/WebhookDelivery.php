<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebhookDelivery extends Model
{
    use HasFactory;

    protected $table = 'webhook_deliveries';
    public $timestamps = false;

    protected $fillable = [
        'webhook_id',
        'event',
        'payload',
        'response_status',
        'delivered_at',
        'error',
    ];

    protected $casts = [
        'response_status' => 'integer',
        'delivered_at' => 'datetime',
    ];

    public function webhook()
    {
        return $this->belongsTo(Webhook::class, 'webhook_id');
    }
}
