<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    use HasFactory;

    protected $table = 'holidays';
    public $timestamps = false;

    protected $fillable = [
        'name',
        'date',
        'is_recurring',
        'created_at',
    ];

    protected $casts = [
        'date' => 'date',
        'is_recurring' => 'boolean',
        'created_at' => 'datetime',
    ];
}
