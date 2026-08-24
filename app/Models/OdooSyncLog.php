<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OdooSyncLog extends Model
{
    use HasFactory;

    protected $table = 'odoo_sync_logs';
    public $timestamps = false;

    protected $fillable = [
        'sync_type',
        'direction',
        'records_processed',
        'records_created',
        'records_updated',
        'records_failed',
        'error_message',
        'started_at',
        'completed_at',
        'status',
    ];

    protected $casts = [
        'records_processed' => 'integer',
        'records_created' => 'integer',
        'records_updated' => 'integer',
        'records_failed' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];
}
