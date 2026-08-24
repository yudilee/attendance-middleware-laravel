<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory;

    protected $table = 'customers';

    protected $fillable = [
        'name',
        'address',
        'city',
        'phone',
        'email',
        'latitude',
        'longitude',
        'customer_type',
        'assigned_employee_id',
        'company_id',
        'is_active',
        'notes',
        'odoo_partner_id',
        'odoo_last_synced_at',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'is_active' => 'boolean',
        'odoo_partner_id' => 'integer',
        'odoo_last_synced_at' => 'datetime',
    ];

    public function assignedEmployee()
    {
        return $this->belongsTo(Employee::class, 'assigned_employee_id', 'employee_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function fieldVisits()
    {
        return $this->hasMany(FieldVisit::class, 'customer_id');
    }

    public function fieldTasks()
    {
        return $this->hasMany(FieldTask::class, 'customer_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByType($query, $type)
    {
        if ($type) {
            return $query->where('customer_type', $type);
        }
        return $query;
    }
}
