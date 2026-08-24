<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdmsRegisteredEmployee extends Model
{
    use HasFactory;

    protected $table = 'adms_registered_employees';
    public $timestamps = false;

    protected $fillable = [
        'employee_id',
        'employee_name',
        'registered_at',
    ];

    protected $casts = [
        'registered_at' => 'datetime',
    ];
}
