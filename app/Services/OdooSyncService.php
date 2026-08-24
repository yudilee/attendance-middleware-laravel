<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\FieldVisit;
use App\Models\FieldTask;
use App\Models\Employee;
use App\Models\OdooSyncLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class OdooSyncService
{
    protected OdooService $odoo;

    public function __construct(OdooService $odoo)
    {
        $this->odoo = $odoo;
    }

    public function pullCustomers(): OdooSyncLog
    {
        $log = OdooSyncLog::create([
            'sync_type' => 'customers_pull',
            'direction' => 'pull',
            'started_at' => now(),
            'status' => 'running',
        ]);

        try {
            $partners = $this->odoo->searchRead(
                'res.partner',
                [['active', '=', true], ['customer_rank', '>', 0]],
                ['id', 'name', 'street', 'city', 'phone', 'email', 'company_type', 'write_date'],
                200
            );

            $created = 0;
            $updated = 0;

            foreach ($partners as $p) {
                $existing = Customer::where('odoo_partner_id', $p['id'])->first();

                $type = ($p['company_type'] ?? '') === 'company' ? 'dealer' : 'end_customer';

                if ($existing) {
                    $existing->update([
                        'name' => $p['name'],
                        'address' => $p['street'] ?: $existing->address,
                        'city' => $p['city'] ?: $existing->city,
                        'phone' => $p['phone'] ?: $existing->phone,
                        'email' => $p['email'] ?: $existing->email,
                        'odoo_last_synced_at' => now(),
                    ]);
                    $updated++;
                } else {
                    Customer::create([
                        'name' => $p['name'],
                        'address' => $p['street'] ?: null,
                        'city' => $p['city'] ?: null,
                        'phone' => $p['phone'] ?: null,
                        'email' => $p['email'] ?: null,
                        'customer_type' => $type,
                        'odoo_partner_id' => $p['id'],
                        'odoo_last_synced_at' => now(),
                        'is_active' => true,
                    ]);
                    $created++;
                }
            }

            $log->update([
                'records_processed' => count($partners),
                'records_created' => $created,
                'records_updated' => $updated,
                'status' => 'completed',
                'completed_at' => now(),
            ]);
        } catch (\Exception $e) {
            $log->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);
        }

        return $log;
    }

    public function pushCustomers(): OdooSyncLog
    {
        $log = OdooSyncLog::create([
            'sync_type' => 'customers_push',
            'direction' => 'push',
            'started_at' => now(),
            'status' => 'running',
        ]);

        try {
            $unsynced = Customer::whereNull('odoo_partner_id')->where('is_active', true)->limit(50)->get();
            $created = 0;
            $failed = 0;

            foreach ($unsynced as $c) {
                try {
                    $partnerId = $this->odoo->create('res.partner', [
                        'name' => $c->name,
                        'street' => $c->address ?: '',
                        'city' => $c->city ?: '',
                        'phone' => $c->phone ?: '',
                        'email' => $c->email ?: '',
                        'customer_rank' => 1,
                        'comment' => "Created via Attendance Field App. Location: {$c->latitude}, {$c->longitude}",
                    ]);

                    if ($partnerId) {
                        $c->update([
                            'odoo_partner_id' => $partnerId,
                            'odoo_last_synced_at' => now(),
                        ]);
                        $created++;
                    }
                } catch (\Exception $e) {
                    $failed++;
                    Log::warning("Failed to push customer ID {$c->id} to Odoo: " . $e->getMessage());
                }
            }

            $log->update([
                'records_processed' => $unsynced->count(),
                'records_created' => $created,
                'records_failed' => $failed,
                'status' => 'completed',
                'completed_at' => now(),
            ]);
        } catch (\Exception $e) {
            $log->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);
        }

        return $log;
    }

    public function pushVisits(): OdooSyncLog
    {
        $log = OdooSyncLog::create([
            'sync_type' => 'visits_push',
            'direction' => 'push',
            'started_at' => now(),
            'status' => 'running',
        ]);

        try {
            $visits = FieldVisit::with(['employee', 'customer'])
                ->where('status', 'completed')
                ->whereNull('odoo_lead_id')
                ->limit(50)
                ->get();

            $created = 0;
            $failed = 0;

            foreach ($visits as $v) {
                try {
                    $empName = $v->employee ? $v->employee->full_name : $v->employee_id;
                    $custName = $v->customer ? $v->customer->name : 'External Client';
                    $partnerId = $v->customer ? $v->customer->odoo_partner_id : false;

                    // Create CRM Lead / Opportunity in Odoo
                    $leadData = [
                        'name' => "Field Visit: {$custName} ({$v->visit_type})",
                        'contact_name' => $custName,
                        'partner_id' => $partnerId ?: false,
                        'description' => "Visit by: {$empName} (PIN: {$v->employee_id})\n"
                            . "Type: {$v->visit_type}\n"
                            . "Purpose: {$v->purpose}\n"
                            . "Duration: {$v->duration_minutes} mins\n"
                            . "Check-In: {$v->check_in_at}\n"
                            . "Check-Out: {$v->check_out_at}\n"
                            . "Notes/Result: " . ($v->result ?: $v->notes ?: '-'),
                    ];

                    $leadId = $this->odoo->create('crm.lead', $leadData);

                    if ($leadId) {
                        $v->update([
                            'odoo_lead_id' => $leadId,
                            'odoo_last_synced_at' => now(),
                        ]);
                        $created++;
                    }
                } catch (\Exception $e) {
                    $failed++;
                    Log::warning("Failed to push field visit ID {$v->id} to Odoo: " . $e->getMessage());
                }
            }

            $log->update([
                'records_processed' => $visits->count(),
                'records_created' => $created,
                'records_failed' => $failed,
                'status' => 'completed',
                'completed_at' => now(),
            ]);
        } catch (\Exception $e) {
            $log->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);
        }

        return $log;
    }

    public function pullEmployees(): OdooSyncLog
    {
        $log = OdooSyncLog::create([
            'sync_type' => 'employees_pull',
            'direction' => 'pull',
            'started_at' => now(),
            'status' => 'running',
        ]);

        try {
            $odooEmployees = $this->odoo->searchRead(
                'hr.employee',
                [['active', '=', true]],
                ['id', 'name', 'identification_id', 'department_id', 'job_title', 'work_email'],
                300
            );

            $updated = 0;
            $created = 0;

            foreach ($odooEmployees as $oe) {
                $empPin = $oe['identification_id'] ?: null;
                if (!$empPin) continue;

                $deptName = is_array($oe['department_id']) ? $oe['department_id'][1] : null;

                $emp = Employee::where('employee_id', $empPin)->first();
                if ($emp) {
                    $emp->update([
                        'full_name' => $oe['name'] ?: $emp->full_name,
                        'department' => $deptName ?: $emp->department,
                        'last_synced' => now(),
                    ]);
                    $updated++;
                }
            }

            $log->update([
                'records_processed' => count($odooEmployees),
                'records_created' => $created,
                'records_updated' => $updated,
                'status' => 'completed',
                'completed_at' => now(),
            ]);
        } catch (\Exception $e) {
            $log->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);
        }

        return $log;
    }

    public function runFullSync(): array
    {
        if (!$this->odoo->isEnabled()) {
            return [
                'success' => false,
                'message' => 'Odoo integration is currently disabled in system configuration.',
            ];
        }

        $auth = $this->odoo->authenticate();
        if (!$auth) {
            return [
                'success' => false,
                'message' => 'Odoo authentication failed. Please check credentials.',
            ];
        }

        $results = [];
        $results['customers_pull'] = $this->pullCustomers();
        $results['customers_push'] = $this->pushCustomers();
        $results['visits_push'] = $this->pushVisits();
        $results['employees_pull'] = $this->pullEmployees();

        return [
            'success' => true,
            'message' => 'Full Odoo synchronization completed.',
            'results' => $results,
        ];
    }
}
