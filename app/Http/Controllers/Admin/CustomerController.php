<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Company;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->query('search');
        $type = $request->query('type');
        $assignedId = $request->query('assigned_id');
        $status = $request->query('status');

        $query = Customer::with(['assignedEmployee', 'company'])
            ->withCount('fieldVisits');

        if ($search) {
            $isPgsql = config('database.default') === 'pgsql';
            $likeOp = $isPgsql ? 'ilike' : 'like';
            $query->where(function ($q) use ($search, $likeOp) {
                $q->where('name', $likeOp, "%{$search}%")
                  ->orWhere('city', $likeOp, "%{$search}%")
                  ->orWhere('address', $likeOp, "%{$search}%")
                  ->orWhere('phone', $likeOp, "%{$search}%");
            });
        }

        if ($type) {
            $query->where('customer_type', $type);
        }

        if ($assignedId) {
            $query->where('assigned_employee_id', $assignedId);
        }

        if ($status !== null && $status !== '') {
            $query->where('is_active', $status === '1' || $status === 'true');
        }

        $customers = $query->orderBy('name')->paginate(20)->withQueryString();

        $employees = Employee::where('is_active', true)
            ->orderBy('full_name')
            ->get(['employee_id', 'full_name', 'department', 'employee_type']);

        $companies = Company::where('is_active', true)->get(['id', 'name']);

        return Inertia::render('Admin/Customers/Index', [
            'customers' => $customers,
            'employees' => $employees,
            'companies' => $companies,
            'filters' => [
                'search' => $search,
                'type' => $type,
                'assigned_id' => $assignedId,
                'status' => $status,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:200',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:150',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'customer_type' => 'required|string|in:dealer,end_customer,warehouse,workshop,prospect,other',
            'assigned_employee_id' => 'nullable|string|exists:employees,employee_id',
            'company_id' => 'nullable|exists:companies,id',
            'is_active' => 'boolean',
            'notes' => 'nullable|string',
        ]);

        $customer = Customer::create($validated);

        AuditLog::create([
            'admin_username' => auth()->user()->name ?? 'Administrator',
            'action' => 'Created customer / location',
            'target_type' => 'Customer',
            'target_id' => $customer->id,
            'details' => "Added customer: {$customer->name} ({$customer->customer_type})",
            'ip_address' => $request->ip(),
        ]);

        return redirect()->back()->with('success', 'Customer created successfully.');
    }

    public function update(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:200',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:150',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'customer_type' => 'required|string|in:dealer,end_customer,warehouse,workshop,prospect,other',
            'assigned_employee_id' => 'nullable|string|exists:employees,employee_id',
            'company_id' => 'nullable|exists:companies,id',
            'is_active' => 'boolean',
            'notes' => 'nullable|string',
        ]);

        $customer->update($validated);

        AuditLog::create([
            'admin_username' => auth()->user()->name ?? 'Administrator',
            'action' => 'Updated customer / location',
            'target_type' => 'Customer',
            'target_id' => $customer->id,
            'details' => "Updated customer: {$customer->name}",
            'ip_address' => $request->ip(),
        ]);

        return redirect()->back()->with('success', 'Customer updated successfully.');
    }

    public function destroy(Request $request, Customer $customer)
    {
        $name = $customer->name;
        $customer->delete();

        AuditLog::create([
            'admin_username' => auth()->user()->name ?? 'Administrator',
            'action' => 'Deleted customer',
            'target_type' => 'Customer',
            'target_id' => $customer->id,
            'details' => "Deleted customer: {$name}",
            'ip_address' => $request->ip(),
        ]);

        return redirect()->back()->with('success', 'Customer deleted successfully.');
    }

    public function importCsv(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:5120',
        ]);

        $path = $request->file('file')->getRealPath();
        $file = fopen($path, 'r');
        $header = fgetcsv($file);

        $imported = 0;
        while (($row = fgetcsv($file)) !== false) {
            if (empty($row[0])) continue;

            // Map columns: Name, Address, City, Phone, Email, Lat, Lng, Type
            Customer::create([
                'name' => $row[0] ?? 'Unnamed Customer',
                'address' => $row[1] ?? null,
                'city' => $row[2] ?? null,
                'phone' => $row[3] ?? null,
                'email' => $row[4] ?? null,
                'latitude' => !empty($row[5]) ? (float)$row[5] : null,
                'longitude' => !empty($row[6]) ? (float)$row[6] : null,
                'customer_type' => in_array($row[7] ?? '', ['dealer','end_customer','warehouse','workshop','prospect']) ? $row[7] : 'dealer',
                'is_active' => true,
            ]);
            $imported++;
        }
        fclose($file);

        return redirect()->back()->with('success', "Imported {$imported} customers successfully.");
    }
}
