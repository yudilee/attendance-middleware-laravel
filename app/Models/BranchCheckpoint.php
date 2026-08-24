<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BranchCheckpoint extends Model
{
    use HasFactory;

    protected $table = 'branch_checkpoints';

    protected $fillable = [
        'branch_id',
        'name',
        'latitude',
        'longitude',
        'radius_meters',
        'is_active',
        'geofence_type',
        'polygon_coordinates',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'radius_meters' => 'float',
        'is_active' => 'boolean',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }
}
