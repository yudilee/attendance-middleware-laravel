<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    use HasFactory;

    protected $table = 'employees';
    public $timestamps = false;

    protected $fillable = [
        'adms_id',
        'employee_id',
        'full_name',
        'department',
        'is_active',
        'is_deleted',
        'employee_type',
        'company_id',
        'group_id',
        'shift_schedule_id',
        'last_synced',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_deleted' => 'boolean',
        'last_synced' => 'datetime',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function group()
    {
        return $this->belongsTo(EmployeeGroup::class, 'group_id');
    }

    public function shiftSchedule()
    {
        return $this->belongsTo(ShiftSchedule::class, 'shift_schedule_id');
    }

    public function deviceBindings()
    {
        return $this->hasMany(DeviceBinding::class, 'employee_id', 'employee_id');
    }

    public function punchLogs()
    {
        return $this->hasMany(PunchLog::class, 'employee_id', 'employee_id');
    }

    public function leaveRequests()
    {
        return $this->hasMany(LeaveRequest::class, 'employee_id', 'employee_id');
    }

    public function leaveBalances()
    {
        return $this->hasMany(LeaveBalance::class, 'employee_id', 'employee_id');
    }

    public function overtimeRequests()
    {
        return $this->hasMany(OvertimeRequest::class, 'employee_id', 'employee_id');
    }

    public function supervisors()
    {
        return $this->hasMany(EmployeeSupervisor::class, 'employee_id', 'employee_id');
    }

    public function subordinates()
    {
        return $this->hasMany(EmployeeSupervisor::class, 'supervisor_id', 'employee_id');
    }

    public function scheduleAssignments()
    {
        return $this->hasMany(ScheduleAssignment::class, 'employee_id', 'employee_id');
    }

    public function fieldVisits()
    {
        return $this->hasMany(FieldVisit::class, 'employee_id', 'employee_id');
    }

    public function fieldTasks()
    {
        return $this->hasMany(FieldTask::class, 'employee_id', 'employee_id');
    }

    public function canvassPlans()
    {
        return $this->hasMany(CanvassPlan::class, 'employee_id', 'employee_id');
    }

    public function assignedCustomers()
    {
        return $this->hasMany(Customer::class, 'assigned_employee_id', 'employee_id');
    }

    public function scopeMechanics($query)
    {
        return $query->where('employee_type', 'mechanic');
    }

    public function scopeSales($query)
    {
        return $query->where('employee_type', 'sales');
    }

    public function scopeFieldWorkers($query)
    {
        return $query->whereIn('employee_type', ['mechanic', 'sales']);
    }
}

