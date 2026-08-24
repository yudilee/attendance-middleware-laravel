<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FieldVisitPhoto extends Model
{
    use HasFactory;

    protected $table = 'field_visit_photos';
    public $timestamps = false;

    protected $fillable = [
        'field_visit_id',
        'filename',
        'caption',
        'photo_type',
        'latitude',
        'longitude',
        'created_at',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'created_at' => 'datetime',
    ];

    public function fieldVisit()
    {
        return $this->belongsTo(FieldVisit::class, 'field_visit_id');
    }
}
