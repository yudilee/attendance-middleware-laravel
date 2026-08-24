<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppConfig extends Model
{
    use HasFactory;

    protected $table = 'app_configs';
    public $timestamps = false;

    protected $fillable = [
        'key',
        'value',
        'description',
    ];
}
