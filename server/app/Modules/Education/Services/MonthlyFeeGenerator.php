<?php

namespace App\Modules\Education\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use App\Modules\Education\Exceptions\DuplicateBillingException;

class MonthlyFeeGenerator
{
    /**
     * Generate a monthly tuition invoice. 
     * Relies on native DB Unique constraints for race-condition idempotency.
     */
    public function generateForStudent(int $businessId, int $studentId, int $month, int $year, string $feeType, float $amount): bool
    {
        try {
            DB::table('student_invoices')->insert([
                'business_id' => $businessId,
                'student_id' => $studentId,
                'billing_month' => $month,
                'billing_year' => $year,
                'fee_type' => $feeType,
                'amount' => $amount,
                'status' => 'Unpaid',
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            return true;
        } catch (QueryException $e) {
            // Error 1062 is Duplicate Entry for unique key
            if ($e->errorInfo[1] == 1062) {
                // Return gracefully without blowing up the job queue
                return false;
            }
            
            throw $e;
        }
    }

    /**
     * Bulk process all active enrollments for a given month/year.
     */
    public function runBatchGeneration(int $businessId, int $month, int $year): array
    {
        $enrollments = DB::table('student_enrollments')
            ->where('business_id', $businessId)
            ->where('status', 'Active')
            ->get();

        $successCount = 0;
        $skippedCount = 0;

        foreach ($enrollments as $enrollment) {
            $result = $this->generateForStudent(
                $businessId,
                $enrollment->student_id,
                $month,
                $year,
                'Monthly Tuition',
                $enrollment->monthly_fee
            );

            if ($result) {
                $successCount++;
            } else {
                $skippedCount++;
            }
        }

        return [
            'success_count' => $successCount,
            'skipped_duplicate_count' => $skippedCount
        ];
    }
}
