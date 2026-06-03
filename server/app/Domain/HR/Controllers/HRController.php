<?php

namespace App\Domain\HR\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Validation\Rule;

class HRController extends Controller
{
    /**
     * Get list of employees
     */
    public function employees(Request $request)
    {
        $employees = DB::table('employees')
            ->where('business_id', $request->user()->business_id)
            ->whereNull('deleted_at')
            ->orderBy('first_name')
            ->paginate(20);

        return response()->json($employees);
    }

    /**
     * Get list of payrolls
     */
    public function payrolls(Request $request)
    {
        $payrolls = DB::table('payrolls')
            ->join('employees', 'payrolls.employee_id', '=', 'employees.id')
            ->where('payrolls.business_id', $request->user()->business_id)
            ->select('payrolls.*', 'employees.first_name', 'employees.last_name', 'employees.employee_id as emp_code')
            ->orderBy('payrolls.month', 'desc')
            ->paginate(20);

        return response()->json($payrolls);
    }

    /**
     * Create Employee
     */
    public function storeEmployee(Request $request)
    {
        $businessId = $request->user()->business_id;

        $validated = $request->validate([
            'employee_id' => 'nullable|string|max:50',
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'nullable|email|max:100',
            'phone' => 'nullable|string|max:20',
            'department' => 'nullable|string|max:100',
            'designation' => 'nullable|string|max:100',
            'basic_salary' => 'required|numeric|min:0',
            'joining_date' => 'nullable|date',
            'is_active' => 'boolean'
        ]);

        $validated['business_id'] = $businessId;
        $validated['created_at'] = Carbon::now();
        $validated['updated_at'] = Carbon::now();

        $id = DB::table('employees')->insertGetId($validated);

        return response()->json(['message' => 'Employee created successfully', 'id' => $id], 201);
    }

    /**
     * Update Employee
     */
    public function updateEmployee(Request $request, $id)
    {
        $businessId = $request->user()->business_id;

        $validated = $request->validate([
            'employee_id' => 'nullable|string|max:50',
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'nullable|email|max:100',
            'phone' => 'nullable|string|max:20',
            'department' => 'nullable|string|max:100',
            'designation' => 'nullable|string|max:100',
            'basic_salary' => 'required|numeric|min:0',
            'joining_date' => 'nullable|date',
            'is_active' => 'boolean'
        ]);

        $updated = DB::table('employees')
            ->where('id', $id)
            ->where('business_id', $businessId)
            ->update(array_merge($validated, ['updated_at' => Carbon::now()]));

        if (!$updated) {
            return response()->json(['message' => 'Employee not found or unauthorized'], 404);
        }

        return response()->json(['message' => 'Employee updated successfully']);
    }

    /**
     * Delete Employee
     */
    public function deleteEmployee(Request $request, $id)
    {
        $businessId = $request->user()->business_id;

        $deleted = DB::table('employees')
            ->where('id', $id)
            ->where('business_id', $businessId)
            ->update(['deleted_at' => Carbon::now()]);

        if (!$deleted) {
            return response()->json(['message' => 'Employee not found or unauthorized'], 404);
        }

        return response()->json(['message' => 'Employee deleted successfully']);
    }

    /**
     * Create Payroll
     */
    public function storePayroll(Request $request)
    {
        $businessId = $request->user()->business_id;

        $validated = $request->validate([
            'employee_id' => [
                'required',
                Rule::exists('employees', 'id')->where('business_id', $businessId)->whereNull('deleted_at')
            ],
            'reference_no' => 'nullable|string|max:100',
            'month' => 'required|string|date_format:Y-m',
            'total_amount' => 'required|numeric|min:0',
            'payment_status' => 'required|in:paid,due'
        ]);

        $validated['business_id'] = $businessId;
        $validated['created_at'] = Carbon::now();
        $validated['updated_at'] = Carbon::now();

        $id = DB::table('payrolls')->insertGetId($validated);

        return response()->json(['message' => 'Payroll created successfully', 'id' => $id], 201);
    }

    /**
     * Update Payroll
     */
    public function updatePayroll(Request $request, $id)
    {
        $businessId = $request->user()->business_id;

        $validated = $request->validate([
            'reference_no' => 'nullable|string|max:100',
            'month' => 'required|string|date_format:Y-m',
            'total_amount' => 'required|numeric|min:0',
            'payment_status' => 'required|in:paid,due'
        ]);

        $updated = DB::table('payrolls')
            ->where('id', $id)
            ->where('business_id', $businessId)
            ->update(array_merge($validated, ['updated_at' => Carbon::now()]));

        if (!$updated) {
            return response()->json(['message' => 'Payroll not found or unauthorized'], 404);
        }

        return response()->json(['message' => 'Payroll updated successfully']);
    }

    /**
     * Delete Payroll
     */
    public function deletePayroll(Request $request, $id)
    {
        $businessId = $request->user()->business_id;

        $deleted = DB::table('payrolls')
            ->where('id', $id)
            ->where('business_id', $businessId)
            ->delete(); // Payrolls table doesn't have soft deletes based on migration

        if (!$deleted) {
            return response()->json(['message' => 'Payroll not found or unauthorized'], 404);
        }

        return response()->json(['message' => 'Payroll deleted successfully']);
    }
}
