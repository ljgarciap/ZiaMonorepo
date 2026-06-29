<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\FormulaEvaluationService;

class FormulaEvaluationServiceTest extends TestCase
{
    protected FormulaEvaluationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new FormulaEvaluationService();
    }

    public function test_simple_multiplication_formula()
    {
        // expression: quantity * factor
        // 10 * 5 = 50
        $result = $this->service->evaluate('quantity * factor', [
            'quantity' => 10,
            'factor'   => 5,
        ]);

        $this->assertEqualsWithDelta(50.0, $result, 0.0001);
    }

    public function test_power_function()
    {
        // POWER(x, 2) where x=4 → 4^2 = 16
        $result = $this->service->evaluate('POWER(x, 2)', ['x' => 4]);

        $this->assertEqualsWithDelta(16.0, $result, 0.0001);
    }

    public function test_sqrt_function()
    {
        // SQRT(x) where x=9 → 3.0
        $result = $this->service->evaluate('SQRT(x)', ['x' => 9]);

        $this->assertEqualsWithDelta(3.0, $result, 0.0001);
    }

    public function test_invalid_formula_throws_exception()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/Formula evaluation error/');

        // Calling an unregistered function throws a Symfony parse/runtime error,
        // caught by the service and re-thrown as Exception("Formula evaluation error: ...")
        $this->service->evaluate('invalid_func(x)', ['x' => 1]);
    }

    public function test_division_by_zero_throws_exception()
    {
        // Integer division by zero in PHP 8 → DivisionByZeroError (an Error, not Exception)
        // It propagates through the service since the catch block only catches \Exception
        $this->expectException(\Throwable::class);

        $this->service->evaluate('quantity / zero_value', ['quantity' => 1, 'zero_value' => 0]);
    }

    public function test_formula_with_multiple_variables()
    {
        // a * b + c = 2 * 3 + 4 = 10
        $result = $this->service->evaluate('a * b + c', [
            'a' => 2,
            'b' => 3,
            'c' => 4,
        ]);

        $this->assertEqualsWithDelta(10.0, $result, 0.0001);
    }

    public function test_result_is_float()
    {
        // Return value must be numeric (float or coercible to float)
        $result = $this->service->evaluate('a * b', ['a' => 3.0, 'b' => 2.0]);

        $this->assertTrue(is_numeric($result), 'evaluate() must return a numeric value');
        $this->assertEqualsWithDelta(6.0, $result, 0.0001);
    }

    // ─── caret operator compatibility ─────────────────────────────────────────

    public function test_caret_operator_is_rewritten_to_exponent()
    {
        // Formulas imported from Excel may use ^ — service replaces it with **
        $result = $this->service->evaluate('2^10', []);
        $this->assertEqualsWithDelta(1024.0, $result, 0.0001);
    }

    public function test_caret_with_variable()
    {
        $result = $this->service->evaluate('base^2', ['base' => 5.0]);
        $this->assertEqualsWithDelta(25.0, $result, 0.0001);
    }

    // ─── GHG Protocol expression (integration with CarbonFootprintService vars) ──

    public function test_standard_combustion_expression_uses_service_variable_names()
    {
        // This is the expression stored in DB for standard combustion formulas.
        // Verifies that variable names match what CarbonFootprintService passes to evaluate().
        $result = $this->service->evaluate(
            '(activity_data * factor_co2) / 1000',
            [
                'activity_data'      => 1000.0,
                'factor_co2'         => 7.618,
                'factor_ch4'         => 0.0,
                'factor_n2o'         => 0.0,
                'factor_total_co2e'  => 0.0,
                'gwp_co2'            => 1.0,
                'gwp_ch4'            => 28.0,
                'gwp_n2o'            => 265.0,
            ]
        );
        // (1000 * 7.618) / 1000 = 7.618 tCO2
        $this->assertEqualsWithDelta(7.618, $result, 0.0001);
    }
}
