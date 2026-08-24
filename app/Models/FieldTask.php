<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FieldTask extends Model
{
    use HasFactory;

    protected $table = 'field_tasks';

    protected $fillable = [
        'employee_id',
        'customer_id',
        'title',
        'description',
        'task_type',
        'priority',
        'status',
        'due_date',
        'completed_at',
        'completed_notes',
        'assigned_by',
        'field_visit_id',
        'odoo_activity_id',
        'odoo_last_synced_at',
    ];

    protected $casts = [
        'due_date' => 'date',
        'completed_at' => 'datetime',
        'odoo_activity_id' => 'integer',
        'odoo_last_synced_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function fieldVisit()
    {
        return $this->belongsTo(FieldVisit::class, 'field_visit_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }
}
