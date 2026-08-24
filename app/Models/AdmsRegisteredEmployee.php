<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdmsRegisteredEmployee extends Model
{
    use HasFactory;

    protected $table = 'adms_registered_employees';
    public $timestamps = true;

    protected $fillable = [
        'employee_id',
        'employee_name',
        'registered_at',
        'sync_status',
        'error_message',
        'last_synced_at',
    ];

    protected $casts = [
        'registered_at' => 'datetime',
        'last_synced_at' => 'datetime',
    ];

    /**
     * Get the employee associated with this registration.
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }
}
