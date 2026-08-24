<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemErrorLog extends Model
{
    use HasFactory;

    protected $table = 'system_error_logs';
    public $timestamps = false;

    protected $fillable = [
        'error_message',
        'stack_trace',
        'component',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];
}
