<?php

namespace Tests\Feature\Education;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use App\Modules\Education\Services\MonthlyFeeGenerator;
use App\Modules\Education\Services\GradingCalculationService;

class EdTechCoreTest extends TestCase
{
    use RefreshDatabase;

    protected int $businessId;
    protected int $studentId;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->businessId = DB::table('businesses')->insertGetId([
            'name' => 'FastPOS Academy',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $this->studentId = DB::table('contacts')->insertGetId([
            'business_id' => $this->businessId,
            'type' => 'customer', // Student is technically a customer
            'first_name' => 'John',
            'last_name' => 'Doe',
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }

    public function test_double_billing_overlap_block()
    {
        $generator = new MonthlyFeeGenerator();

        // 1. Run first generation - Should succeed
        $firstRunResult = $generator->generateForStudent(
            $this->businessId,
            $this->studentId,
            10, // October
            2026,
            'Monthly Tuition',
            500.00
        );

        $this->assertTrue($firstRunResult);

        // Verify DB State
        $count = DB::table('student_invoices')->where('student_id', $this->studentId)->count();
        $this->assertEquals(1, $count);

        // 2. Programmatically trigger the second run immediately
        // This simulates a cron overlap or double-click. 
        // The service MUST return false gracefully via caught SQL exception.
        $secondRunResult = $generator->generateForStudent(
            $this->businessId,
            $this->studentId,
            10, // October
            2026,
            'Monthly Tuition',
            500.00
        );

        $this->assertFalse($secondRunResult);

        // Assert that the database physically rejected the duplicate insert
        $countAfterOverlap = DB::table('student_invoices')->where('student_id', $this->studentId)->count();
        $this->assertEquals(1, $countAfterOverlap);
    }

    public function test_dynamic_json_gpa_calculation()
    {
        $gradingService = new GradingCalculationService();

        // Submit an exam payload with varying credits and marks
        $marksPayload = [
            // Physics: 85/100 (85%) => A+ (4.0) | Credit: 3 | Weighted: 12.0
            ['subject' => 'Physics', 'marks_obtained' => 85, 'total_marks' => 100, 'credit' => 3],
            // Chemistry: 65/100 (65%) => B (3.0) | Credit: 3 | Weighted: 9.0
            ['subject' => 'Chemistry', 'marks_obtained' => 65, 'total_marks' => 100, 'credit' => 3],
            // Math: 45/50 (90%) => A+ (4.0) | Credit: 4 | Weighted: 16.0
            ['subject' => 'Math', 'marks_obtained' => 45, 'total_marks' => 50, 'credit' => 4],
            // History: 20/100 (20%) => F (0.0) | Credit: 2 | Weighted: 0.0
            ['subject' => 'History', 'marks_obtained' => 20, 'total_marks' => 100, 'credit' => 2],
        ];

        // Total Credits = 3 + 3 + 4 + 2 = 12
        // Total Weighted Points = 12.0 + 9.0 + 16.0 + 0.0 = 37.0
        // Expected GPA = 37.0 / 12 = 3.0833 => 3.08
        // Expected Letter = B (because 3.08 >= 3.00 and < 3.50)

        $result = $gradingService->calculateGPA($marksPayload);

        $this->assertEquals('3.08', $result['cumulative_gpa']);
        $this->assertEquals('B', $result['grade_letter']);
    }
}
