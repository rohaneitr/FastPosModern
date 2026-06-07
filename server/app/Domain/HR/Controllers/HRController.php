<?php

namespace App\Domain\HR\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Domain\HR\Models\EmployeeProfile;
use App\Domain\HR\Models\Attendance;
use App\Domain\HR\Models\Payroll;
use App\Domain\IAM\Models\User;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class HRController extends Controller
{
    /**
     * Get list of employees (Users + Profiles)
     */
    public function employees(Request $request)
    {
        $users = User::where('business_id', $request->user()->business_id)
            ->with('employeeProfile')
            ->get();
            
        return response()->json($users);
    }

    /**
     * Update/Create Employee Profile
     */
    public function updateEmployeeProfile(Request $request, $userId)
    {
        $user = User::where('business_id', $request->user()->business_id)->findOrFail($userId);
        
        $validated = $request->validate([
            'base_salary' => 'required|numeric|min:0',
            'joining_date' => 'nullable|date',
            'designation' => 'nullable|string|max:255',
            'nid_number' => 'nullable|string|max:255',
            'emergency_contact' => 'nullable|string|max:255',
        ]);

        $profile = EmployeeProfile::updateOrCreate(
            ['user_id' => $user->id, 'business_id' => $user->business_id],
            $validated
        );

        return response()->json(['message' => 'Employee profile updated', 'profile' => $profile]);
    }

    /**
     * Daily Attendance: Clock In
     */
    public function clockIn(Request $request)
    {
        $user = $request->user();
        $date = Carbon::today()->toDateString();

        $attendance = Attendance::firstOrCreate(
            ['user_id' => $user->id, 'business_id' => $user->business_id, 'date' => $date],
            ['status' => 'Present']
        );

        if ($attendance->clock_in) {
            return response()->json(['message' => 'Already clocked in today'], 400);
        }

        $attendance->update(['clock_in' => Carbon::now()]);

        return response()->json(['message' => 'Clocked in successfully', 'attendance' => $attendance]);
    }

    /**
     * Daily Attendance: Clock Out
     */
    public function clockOut(Request $request)
    {
        $user = $request->user();
        $date = Carbon::today()->toDateString();

        $attendance = Attendance::where([
            'user_id' => $user->id,
            'business_id' => $user->business_id,
            'date' => $date
        ])->first();

        if (!$attendance || !$attendance->clock_in) {
            return response()->json(['message' => 'Must clock in first'], 400);
        }

        $attendance->update(['clock_out' => Carbon::now()]);

        return response()->json(['message' => 'Clocked out successfully', 'attendance' => $attendance]);
    }

    /**
     * Get Attendance Grid
     */
    public function getAttendance(Request $request)
    {
        $month = $request->get('month', Carbon::now()->format('Y-m'));
        
        $attendances = Attendance::where('business_id', $request->user()->business_id)
            ->where('date', 'like', $month . '%')
            ->with('user')
            ->orderBy('date', 'desc')
            ->get();
            
        return response()->json($attendances);
    }
    
    /**
     * Update Attendance (Manual Override)
     */
    public function updateAttendance(Request $request, $id)
    {
        $attendance = Attendance::where('business_id', $request->user()->business_id)->findOrFail($id);
        
        $validated = $request->validate([
            'clock_in' => 'nullable|date',
            'clock_out' => 'nullable|date',
            'status' => 'required|string|in:Present,Absent,Late,Half-Day',
        ]);
        
        $attendance->update([
            'clock_in' => $validated['clock_in'] ? Carbon::parse($validated['clock_in']) : null,
            'clock_out' => $validated['clock_out'] ? Carbon::parse($validated['clock_out']) : null,
            'status' => $validated['status']
        ]);
        
        return response()->json(['message' => 'Attendance updated successfully', 'attendance' => $attendance]);
    }
    
    /**
     * Get Payrolls
     */
    public function payrolls(Request $request)
    {
        $payrolls = Payroll::where('business_id', $request->user()->business_id)
            ->with('user')
            ->orderBy('month', 'desc')
            ->get();
            
        return response()->json($payrolls);
    }

    /**
     * Smart Payroll Generator
     */
    public function generatePayroll(Request $request)
    {
        $businessId = $request->user()->business_id;
        
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'month' => 'required|date_format:Y-m', // e.g., '2026-06'
            'bonus_commission' => 'numeric|min:0',
            'deductions_fines' => 'numeric|min:0',
        ]);
        
        $userId = $validated['user_id'];
        $month = $validated['month'];

        // Get Employee Profile
        $profile = EmployeeProfile::where('user_id', $userId)->where('business_id', $businessId)->first();
        if (!$profile) {
            return response()->json(['message' => 'Employee profile not found. Set base salary first.'], 404);
        }

        $baseSalary = $profile->base_salary;
        
        // Calculate days
        $startOfMonth = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $endOfMonth = $startOfMonth->copy()->endOfMonth();
        
        // Let's assume total working days in a month is the actual days in the month
        // or a fixed standard (e.g., 22). Let's use days in month for simplicity.
        $totalWorkingDays = $startOfMonth->daysInMonth;
        
        // Count Present Days
        $presentDays = Attendance::where('user_id', $userId)
            ->where('business_id', $businessId)
            ->whereBetween('date', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])
            ->whereNotNull('clock_in')
            ->count();
            
        // Gross Salary
        $grossSalary = ($baseSalary / $totalWorkingDays) * $presentDays;
        
        $bonus = $validated['bonus_commission'] ?? 0;
        $deductions = $validated['deductions_fines'] ?? 0;
        
        $netSalary = $grossSalary + $bonus - $deductions;

        // Save Payroll
        $payroll = Payroll::updateOrCreate(
            ['user_id' => $userId, 'business_id' => $businessId, 'month' => $month],
            [
                'reference_no' => 'PAY-' . strtoupper(uniqid()),
                'base_salary' => $baseSalary,
                'total_working_days' => $totalWorkingDays,
                'present_days' => $presentDays,
                'gross_salary' => $grossSalary,
                'bonus_commission' => $bonus,
                'deductions_fines' => $deductions,
                'net_salary' => $netSalary,
                'payment_status' => 'due'
            ]
        );

        return response()->json(['message' => 'Payroll generated successfully', 'payroll' => $payroll]);
    }

    /**
     * Mark as Paid & Financial Bridge
     */
    public function payPayroll(Request $request, $id)
    {
        $businessId = $request->user()->business_id;
        $payroll = Payroll::where('business_id', $businessId)->findOrFail($id);
        
        if ($payroll->payment_status === 'paid') {
            return response()->json(['message' => 'Already paid'], 400);
        }
        
        $paymentMethod = $request->input('payment_method', 'Cash');

        DB::transaction(function () use ($payroll, $businessId, $paymentMethod, $request) {
            // Create Expense
            $expenseId = DB::table('expenses')->insertGetId([
                'business_id' => $businessId,
                'expense_category_id' => $this->getOrCreateSalaryCategory($businessId),
                'amount' => $payroll->net_salary,
                'reference_no' => $payroll->reference_no,
                'note' => "Payroll for {$payroll->month}",
                'expense_date' => Carbon::now()->toDateString(),
                'created_by' => $request->user()->id,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ]);
            
            // Mark Paid
            $payroll->update([
                'payment_status' => 'paid',
                'expense_id' => $expenseId
            ]);
        });
        
        return response()->json(['message' => 'Payroll marked as paid and expense created', 'payroll' => $payroll]);
    }

    private function getOrCreateSalaryCategory($businessId)
    {
        $category = DB::table('expense_categories')
            ->where('business_id', $businessId)
            ->where('name', 'Salaries & Wages')
            ->first();
            
        if ($category) {
            return $category->id;
        }
        
        return DB::table('expense_categories')->insertGetId([
            'business_id' => $businessId,
            'name' => 'Salaries & Wages',
            'description' => 'Auto-generated category for Payroll',
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now()
        ]);
    }
}
