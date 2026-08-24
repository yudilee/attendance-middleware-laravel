<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdmsCredential extends Model
{
    use HasFactory;

    protected $table = 'adms_credentials';
    public $timestamps = false;

    protected $fillable = [
        'url',
        'username',
        'password',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
