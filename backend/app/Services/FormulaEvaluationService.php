<?php

namespace App\Services;

use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

class FormulaEvaluationService
{
    protected $expressionLanguage;

    public function __construct()
    {
        $this->expressionLanguage = new ExpressionLanguage();
        $this->registerFunctions();
    }

    protected function registerFunctions()
    {
        // SQRT
        $this->expressionLanguage->register('SQRT', function ($str) {
            return sprintf('(is_numeric(%1$s) ? sqrt(%1$s) : 0)', $str);
        }, function ($arguments, $str) {
            return is_numeric($str) ? sqrt($str) : 0;
        });

        // POWER (^)
        $this->expressionLanguage->register('POWER', function ($a, $b) {
            return sprintf('pow(%1$s, %2$s)', $a, $b);
        }, function ($arguments, $a, $b) {
            return pow($a, $b);
        });
        
        // AVERAGE
        // Expects an array or varying arguments. Excel AVERAGE(range). 
        // In our case, inputs might be passed as an array 'values'.
        // But if formula is `AVERAGE(v1, v2)`, we support varargs.
        $this->expressionLanguage->register('AVERAGE', function (...$args) {
            return sprintf('(count([%1$s]) > 0 ? array_sum([%1$s]) / count([%1$s]) : 0)', implode(', ', $args));
        }, function ($arguments, ...$args) {
             // If first arg is array, use it
            if (count($args) === 1 && is_array($args[0])) {
                $data = $args[0];
            } else {
                $data = $args;
            }
            return count($data) > 0 ? array_sum($data) / count($data) : 0;
        });

         // STDEV
         // Standard Deviation (Sample)
        $this->expressionLanguage->register('STDEV', function (...$args) {
            // Compiler not easily implemented for complex logic inline, usually rely on evaluator
             return sprintf('stdev_custom([%1$s])', implode(', ', $args)); # simplified, might need custom provider
        }, function ($arguments, ...$args) {
            if (count($args) === 1 && is_array($args[0])) {
                $data = $args[0];
            } else {
                $data = $args;
            }
            
            $n = count($data);
            if ($n <= 1) return 0;
            
            $mean = array_sum($data) / $n;
            $carry = 0.0;
            foreach ($data as $val) {
                $d = ((double) $val) - $mean;
                $carry += $d * $d;
            }
            return sqrt($carry / ($n - 1));
        });
        
        // VLOOKUP - Simplified for now, or maybe the formula shouldn't use VLOOKUP directly but pre-fetched values.
        // The plan says "logic is agnostic of factors".
        // The Excel formulas use VLOOKUP to get factor values. 
        // In our DB, we should have fetched the Factor values ALREADY and passed them as variables.
        // So `VLOOKUP($C16, ...)` becomes `factor_co2` or similar variable.
        // We will assume the formula expression stored in DB is CLEANED of Excel logic and uses variables.
        // E.g. (activity_data * factor_co2) / 1000
    }

    public function evaluate(string $expression, array $values): float
    {
        try {
            // Replace Excel-style operators if present and easy to fix, though we expect clean syntax
            // ExpressionLanguage uses similar syntax but `^` is bitwise xor in PHP, `**` is power.
            // But we registered POWER. If input uses `^`, we might need to preprocess or expect valid syntax.
            // Let's assume we store valid ExpressionLanguage syntax in DB.
            // OR we replace `^` with `**` textually here.
            $expression = str_replace('^', '**', $expression); 
            
            return $this->expressionLanguage->evaluate($expression, $values);
        } catch (\Exception $e) {
            // Log error or throw
            throw new \Exception("Formula evaluation error: " . $e->getMessage());
        }
    }
}
