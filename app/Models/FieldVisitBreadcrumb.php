<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FieldVisitBreadcrumb extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'field_visit_id',
        'latitude',
        'longitude',
        'speed',
        'accuracy',
        'heading',
        'recorded_at',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'speed' => 'float',
        'accuracy' => 'float',
        'heading' => 'float',
        'recorded_at' => 'datetime',
    ];

    public function fieldVisit(): BelongsTo
    {
        return $this->belongsTo(FieldVisit::class);
    }
}
