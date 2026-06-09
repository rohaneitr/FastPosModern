<?php

namespace App\Modules\Education\Services;

class GradingCalculationService
{
    /**
     * Evaluates a dynamic JSON marks payload and calculates the cumulative GPA.
     * Expected Payload Structure:
     * [
     *   {"subject": "Physics", "marks_obtained": 85, "total_marks": 100, "credit": 3},
     *   {"subject": "Chemistry", "marks_obtained": 72, "total_marks": 100, "credit": 3}
     * ]
     */
    public function calculateGPA(array $marksPayload): array
    {
        $totalCredits = '0.00';
        $totalWeightedPoints = '0.00';
        
        foreach ($marksPayload as $subject) {
            $obtained = (string)$subject['marks_obtained'];
            $total = (string)$subject['total_marks'];
            $credit = (string)($subject['credit'] ?? 1); // Default to 1 credit if not provided

            // Prevent division by zero
            if (bccomp($total, '0.00', 2) <= 0) {
                continue;
            }

            // Percentage = (Obtained / Total) * 100
            $ratio = bcdiv($obtained, $total, 4);
            $percentage = bcmul($ratio, '100.00', 2);

            // Fetch GPA based on percentage
            $gpaPoint = $this->getRubricGpa($percentage);

            // Weighted Point = GPA Point * Credit
            $weighted = bcmul($gpaPoint, $credit, 4);

            $totalWeightedPoints = bcadd($totalWeightedPoints, $weighted, 4);
            $totalCredits = bcadd($totalCredits, $credit, 4);
        }

        if (bccomp($totalCredits, '0.00', 4) <= 0) {
            return [
                'cumulative_gpa' => '0.00',
                'grade_letter' => 'F'
            ];
        }

        // Final GPA = Total Weighted Points / Total Credits
        $cumulativeGpa = bcdiv($totalWeightedPoints, $totalCredits, 2);
        
        return [
            'cumulative_gpa' => $cumulativeGpa,
            'grade_letter' => $this->getRubricLetter($cumulativeGpa)
        ];
    }

    /**
     * Standard US/International 4.0 Rubric Converter
     */
    private function getRubricGpa(string $percentage): string
    {
        if (bccomp($percentage, '80.00', 2) >= 0) return '4.00'; // A+ / A
        if (bccomp($percentage, '70.00', 2) >= 0) return '3.50'; // A- / B+
        if (bccomp($percentage, '60.00', 2) >= 0) return '3.00'; // B
        if (bccomp($percentage, '50.00', 2) >= 0) return '2.00'; // C
        if (bccomp($percentage, '40.00', 2) >= 0) return '1.00'; // D
        return '0.00'; // F
    }

    private function getRubricLetter(string $gpa): string
    {
        if (bccomp($gpa, '4.00', 2) >= 0) return 'A+';
        if (bccomp($gpa, '3.50', 2) >= 0) return 'A';
        if (bccomp($gpa, '3.00', 2) >= 0) return 'B';
        if (bccomp($gpa, '2.00', 2) >= 0) return 'C';
        if (bccomp($gpa, '1.00', 2) >= 0) return 'D';
        return 'F';
    }
}
