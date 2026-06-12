<?php

/**
 * run_unit_tests.php — Task 3.6 Unit Test Runner
 * Bypasses phpunit wrapper by running tests directly via PHP.
 * Run: php run_unit_tests.php
 */

require_once __DIR__ . '/vendor/autoload.php';

$testFiles = [
    __DIR__ . '/tests/Unit/Procurement/Actions/PurchaseTotalsCalculatorTest.php',
    __DIR__ . '/tests/Unit/Sales/Actions/FinancialCalculatorTest.php',
    __DIR__ . '/tests/Unit/Tenant/Models/DeviceActivationTest.php',
    __DIR__ . '/tests/Unit/Tenant/Services/PlanManagementServiceTest.php',
];

$totalPassed = 0;
$totalFailed = 0;
$totalSkipped = 0;
$allErrors = [];

foreach ($testFiles as $file) {
    require_once $file;
}

// Collect all test classes
$testClasses = [
    \Tests\Unit\Procurement\Actions\PurchaseTotalsCalculatorTest::class,
    \Tests\Unit\Sales\Actions\FinancialCalculatorTest::class,
    \Tests\Unit\Tenant\Models\DeviceActivationTest::class,
    \Tests\Unit\Tenant\Services\PlanManagementServiceTest::class,
];

echo "\n" . str_repeat('=', 70) . "\n";
echo "  FastPOS Task 3.6 — Unit Test Suite Runner\n";
echo str_repeat('=', 70) . "\n\n";

foreach ($testClasses as $className) {
    $shortName = class_basename($className);
    echo "▶ {$shortName}\n";
    echo str_repeat('-', 50) . "\n";

    $reflection = new ReflectionClass($className);
    // PHPUnit 12: TestCase constructor requires a method name argument
    $instance   = $reflection->newInstanceArgs(['__phpunit_runner_placeholder__']);

    // Run setUp if exists
    $setupMethod = $reflection->hasMethod('setUp') ? $reflection->getMethod('setUp') : null;

    $passed  = 0;
    $failed  = 0;
    $skipped = 0;

    foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
        $methodName = $method->getName();

        // Skip inherited from PHPUnit\Framework\TestCase
        if ($method->getDeclaringClass()->getName() !== $className) {
            continue;
        }

        // Only run test methods (PHPUnit convention: starts with 'test' OR has @test docblock)
        $docComment = $method->getDocComment() ?: '';
        $isTestMethod = str_starts_with($methodName, 'test') || str_contains($docComment, '@test');

        if (!$isTestMethod) {
            continue;
        }

        try {
            // Run setUp before each test
            if ($setupMethod) {
                $setupMethod->invoke($instance);
            }

            $method->invoke($instance);
            echo "  ✅ {$methodName}\n";
            $passed++;
        } catch (\PHPUnit\Framework\SkippedTest $e) {
            echo "  ⏭  {$methodName} (skipped)\n";
            $skipped++;
        } catch (\PHPUnit\Framework\AssertionFailedError $e) {
            echo "  ❌ {$methodName}\n";
            echo "     → " . $e->getMessage() . "\n";
            $failed++;
            $allErrors[] = "[{$shortName}::{$methodName}] " . $e->getMessage();
        } catch (\Throwable $e) {
            echo "  💥 {$methodName} — " . get_class($e) . ": " . $e->getMessage() . "\n";
            $failed++;
            $allErrors[] = "[{$shortName}::{$methodName}] " . get_class($e) . ': ' . $e->getMessage();
        }
    }

    echo str_repeat('-', 50) . "\n";
    echo "  Passed: {$passed}  Failed: {$failed}  Skipped: {$skipped}\n\n";

    $totalPassed  += $passed;
    $totalFailed  += $failed;
    $totalSkipped += $skipped;
}

// Final Summary
echo str_repeat('=', 70) . "\n";
echo "  FINAL RESULTS\n";
echo str_repeat('=', 70) . "\n";
echo "  ✅ Passed:  {$totalPassed}\n";
echo "  ❌ Failed:  {$totalFailed}\n";
echo "  ⏭  Skipped: {$totalSkipped}\n";
echo "  📊 Total:   " . ($totalPassed + $totalFailed + $totalSkipped) . " tests\n";

if (!empty($allErrors)) {
    echo "\n  FAILURES:\n";
    foreach ($allErrors as $error) {
        echo "  • {$error}\n";
    }
}

echo str_repeat('=', 70) . "\n";
echo ($totalFailed === 0) ? "  🎉 ALL TESTS PASSED\n" : "  ⚠️  {$totalFailed} test(s) failed\n";
echo str_repeat('=', 70) . "\n\n";

exit($totalFailed > 0 ? 1 : 0);
