<?php

namespace App\Domains\Production\Services;

use App\Domains\Inventory\Models\Product;
use InvalidArgumentException;

class BomFormulaEvaluatorService
{
    private const ALLOWED_FUNCTIONS = ['ceil', 'floor', 'round', 'min', 'max', 'abs'];

    /**
     * Evaluate a mathematical formula against provided parameter values and product attributes.
     */
    public function evaluate(string $formula, array $parameters = [], ?Product $product = null): float
    {
        $formula = trim($formula);

        if (empty($formula)) {
            throw new InvalidArgumentException("Formula expression cannot be empty.");
        }

        // Check for forbidden / unsafe substrings
        $this->guardAgainstUnsafeTokens($formula);

        // Merge product attributes into parameter context if product provided
        $mergedParams = $this->buildParameterContext($parameters, $product);

        // Tokenize formula
        $tokens = $this->tokenize($formula);

        // Convert to Reverse Polish Notation (RPN) using Shunting-Yard algorithm
        $rpn = $this->shuntingYard($tokens);

        // Evaluate RPN expression
        return $this->evaluateRpn($rpn, $mergedParams);
    }

    /**
     * Validate formula expression syntax and parameter completeness without throwing execution exceptions.
     */
    public function validateFormula(string $formula, array $availableParams = []): array
    {
        $errors = [];
        $formula = trim($formula);

        if (empty($formula)) {
            return ["Formula expression cannot be empty."];
        }

        try {
            $this->guardAgainstUnsafeTokens($formula);
            $tokens = $this->tokenize($formula);
            $rpn = $this->shuntingYard($tokens);

            $dummyParams = [];
            if (!empty($availableParams)) {
                $normalParams = array_change_key_case($availableParams, CASE_LOWER);
            }

            foreach ($rpn as $token) {
                if ($token['type'] === 'VARIABLE') {
                    $varName = strtolower($token['value']);
                    $dummyParams[$varName] = 1.0;

                    if (!empty($availableParams) && !array_key_exists($varName, $normalParams)) {
                        $errors[] = "Unknown parameter: {$token['value']}";
                    }
                }
            }

            // Perform dummy RPN evaluation to verify operator/operand syntax
            $this->evaluateRpn($rpn, $dummyParams);
        } catch (\Throwable $e) {
            $errors[] = $e->getMessage();
        }

        return array_values(array_unique($errors));
    }

    /**
     * Preview formula evaluation for UI/API.
     */
    public function previewFormula(string $formula, array $sampleParams = []): array
    {
        try {
            $result = $this->evaluate($formula, $sampleParams);
            return [
                'success' => true,
                'result' => round($result, 4),
                'error' => null,
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'result' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Build merged parameter context incorporating product dimensions and variant attributes.
     */

    public function buildParameterContext(array $parameters, ?Product $product = null): array
    {
        $context = [];

        if ($product) {
            // 1. Parent Product dimensions if variant
            if ($product->parent_id && !$product->relationLoaded('parent')) {
                $product->load('parent');
            }
            $parent = $product->parent;
            if ($parent) {
                if ($parent->length !== null) $context['length'] = (float) $parent->length;
                if ($parent->width !== null) $context['width'] = (float) $parent->width;
                if ($parent->height !== null) $context['height'] = (float) $parent->height;
                if ($parent->weight !== null) $context['weight'] = (float) $parent->weight;
            }

            // 2. Product explicit dimensions
            if ($product->length !== null) $context['length'] = (float) $product->length;
            if ($product->width !== null) $context['width'] = (float) $product->width;
            if ($product->height !== null) $context['height'] = (float) $product->height;
            if ($product->weight !== null) $context['weight'] = (float) $product->weight;

            // 3. Product attributes_config
            if (is_array($product->attributes_config)) {
                foreach ($product->attributes_config as $k => $v) {
                    if (is_numeric($v)) {
                        $context[strtolower((string) $k)] = (float) $v;
                    }
                }
            }

            // 4. Product variant_values
            if (is_array($product->variant_values)) {
                foreach ($product->variant_values as $k => $v) {
                    if (is_numeric($v)) {
                        $context[strtolower((string) $k)] = (float) $v;
                    }
                }
            }
        }

        // 5. Explicit parameters from Production Order / caller (highest precedence)
        foreach ($parameters as $k => $v) {
            if (is_numeric($v)) {
                $context[strtolower((string) $k)] = (float) $v;
            }
        }

        return $context;
    }

    /**
     * Guard against unsafe tokens or arbitrary PHP execution constructs.
     */
    private function guardAgainstUnsafeTokens(string $formula): void
    {
        if (preg_match('/[;$\{\}\\\\\?\'"`:\[\]]|eval|exec|system|passthru|shell_exec|include|require|function|class|return/i', $formula)) {
            throw new InvalidArgumentException("Formula contains forbidden keywords or characters.");
        }
    }

    /**
     * Tokenizer: converts raw string into array of structured tokens.
     */
    private function tokenize(string $formula): array
    {
        $tokens = [];
        $len = strlen($formula);
        $i = 0;

        while ($i < $len) {
            $char = $formula[$i];

            if (ctype_space($char)) {
                $i++;
                continue;
            }

            $isUnaryMinus = false;
            if ($char === '-' && $i + 1 < $len && (ctype_digit($formula[$i + 1]) || $formula[$i + 1] === '.')) {
                $prevToken = !empty($tokens) ? end($tokens) : null;
                if ($prevToken === null || ($prevToken['type'] === 'OPERATOR' && strpos('+-*/^(),', (string)$prevToken['value']) !== false)) {
                    $isUnaryMinus = true;
                }
            }

            if (ctype_digit($char) || $char === '.' || $isUnaryMinus) {
                $numStr = '';
                if ($isUnaryMinus) {
                    $numStr = '-';
                    $i++;
                }
                while ($i < $len && (ctype_digit($formula[$i]) || $formula[$i] === '.')) {
                    $numStr .= $formula[$i];
                    $i++;
                }
                if (substr_count($numStr, '.') > 1) {
                    throw new InvalidArgumentException("Invalid number format '{$numStr}' in formula.");
                }
                $tokens[] = ['type' => 'NUMBER', 'value' => (float) $numStr];
                continue;
            }

            if (strpos('+-*/^(),', $char) !== false) {
                $tokens[] = ['type' => 'OPERATOR', 'value' => $char];
                $i++;
                continue;
            }

            if (ctype_alpha($char) || $char === '_') {
                $nameStr = '';
                while ($i < $len && (ctype_alnum($formula[$i]) || $formula[$i] === '_')) {
                    $nameStr .= $formula[$i];
                    $i++;
                }
                if (in_array(strtolower($nameStr), self::ALLOWED_FUNCTIONS, true)) {
                    $tokens[] = ['type' => 'FUNCTION', 'value' => strtolower($nameStr), 'arg_count' => 1];
                } else {
                    $tokens[] = ['type' => 'VARIABLE', 'value' => $nameStr];
                }
                continue;
            }

            throw new InvalidArgumentException("Unexpected character '{$char}' in formula.");
        }

        return $tokens;
    }

    /**
     * Shunting-Yard Algorithm: infix tokens -> RPN output queue.
     */
    private function shuntingYard(array $tokens): array
    {
        $outputQueue = [];
        $operatorStack = [];

        $precedence = [
            '+' => 2,
            '-' => 2,
            '*' => 3,
            '/' => 3,
            '^' => 4,
        ];

        $associativity = [
            '+' => 'L',
            '-' => 'L',
            '*' => 'L',
            '/' => 'L',
            '^' => 'R',
        ];

        foreach ($tokens as $token) {
            $type = $token['type'];
            $val = $token['value'];

            if ($type === 'NUMBER' || $type === 'VARIABLE') {
                $outputQueue[] = $token;
            } elseif ($type === 'FUNCTION') {
                $operatorStack[] = $token;
            } elseif ($val === ',') {
                while (!empty($operatorStack) && end($operatorStack)['value'] !== '(') {
                    $outputQueue[] = array_pop($operatorStack);
                }
                if (empty($operatorStack)) {
                    throw new InvalidArgumentException("Misplaced comma or mismatched parentheses in formula.");
                }
                $stackLen = count($operatorStack);
                if ($stackLen >= 2 && $operatorStack[$stackLen - 2]['type'] === 'FUNCTION') {
                    $operatorStack[$stackLen - 2]['arg_count']++;
                }
            } elseif ($type === 'OPERATOR' && strpos('+-*/^', (string) $val) !== false) {
                $p1 = $precedence[$val];
                while (!empty($operatorStack)) {
                    $top = end($operatorStack);
                    if ($top['type'] === 'OPERATOR' && strpos('+-*/^', (string) $top['value']) !== false) {
                        $p2 = $precedence[$top['value']];
                        $assoc = $associativity[$val];
                        if (($assoc === 'L' && $p1 <= $p2) || ($assoc === 'R' && $p1 < $p2)) {
                            $outputQueue[] = array_pop($operatorStack);
                            continue;
                        }
                    }
                    break;
                }
                $operatorStack[] = $token;
            } elseif ($val === '(') {
                $operatorStack[] = $token;
            } elseif ($val === ')') {
                while (!empty($operatorStack) && end($operatorStack)['value'] !== '(') {
                    $outputQueue[] = array_pop($operatorStack);
                }
                if (empty($operatorStack)) {
                    throw new InvalidArgumentException("Mismatched parentheses in formula.");
                }
                array_pop($operatorStack); // Pop '('

                if (!empty($operatorStack) && end($operatorStack)['type'] === 'FUNCTION') {
                    $outputQueue[] = array_pop($operatorStack);
                }
            }
        }

        while (!empty($operatorStack)) {
            $top = array_pop($operatorStack);
            if ($top['value'] === '(' || $top['value'] === ')') {
                throw new InvalidArgumentException("Mismatched parentheses in formula.");
            }
            $outputQueue[] = $top;
        }

        return $outputQueue;
    }

    /**
     * Evaluate RPN token queue using value stack.
     */
    private function evaluateRpn(array $rpn, array $params): float
    {
        $stack = [];

        foreach ($rpn as $token) {
            $type = $token['type'];
            $val = $token['value'];

            if ($type === 'NUMBER') {
                $stack[] = (float) $val;
            } elseif ($type === 'VARIABLE') {
                $varLower = strtolower($val);
                if (!array_key_exists($varLower, $params)) {
                    throw new InvalidArgumentException("Missing required parameter: {$val}");
                }
                $stack[] = (float) $params[$varLower];
            } elseif ($type === 'OPERATOR') {
                if (count($stack) < 2) {
                    throw new InvalidArgumentException("Invalid expression syntax near '{$val}'.");
                }
                $b = array_pop($stack);
                $a = array_pop($stack);

                switch ($val) {
                    case '+':
                        $stack[] = $a + $b;
                        break;
                    case '-':
                        $stack[] = $a - $b;
                        break;
                    case '*':
                        $stack[] = $a * $b;
                        break;
                    case '/':
                        if (abs($b) < 1e-12) {
                            throw new InvalidArgumentException("Division by zero in formula evaluation.");
                        }
                        $stack[] = $a / $b;
                        break;
                    case '^':
                        $stack[] = pow($a, $b);
                        break;
                }
            } elseif ($type === 'FUNCTION') {
                $argCount = $token['arg_count'] ?? 1;
                if (count($stack) < $argCount) {
                    throw new InvalidArgumentException("Insufficient arguments for function '{$val}'.");
                }
                switch ($val) {
                    case 'ceil':
                        $stack[] = ceil(array_pop($stack));
                        break;
                    case 'floor':
                        $stack[] = floor(array_pop($stack));
                        break;
                    case 'round':
                        if ($argCount === 2) {
                            $precision = (int) array_pop($stack);
                            $v = array_pop($stack);
                            $stack[] = round($v, $precision);
                        } else {
                            $stack[] = round(array_pop($stack));
                        }
                        break;
                    case 'abs':
                        $stack[] = abs(array_pop($stack));
                        break;
                    case 'min':
                        if ($argCount < 2) throw new InvalidArgumentException("min() requires 2 arguments.");
                        $b = array_pop($stack);
                        $a = array_pop($stack);
                        $stack[] = min($a, $b);
                        break;
                    case 'max':
                        if ($argCount < 2) throw new InvalidArgumentException("max() requires 2 arguments.");
                        $b = array_pop($stack);
                        $a = array_pop($stack);
                        $stack[] = max($a, $b);
                        break;
                }
            }
        }

        if (count($stack) !== 1) {
            throw new InvalidArgumentException("Invalid formula expression syntax.");
        }

        return (float) array_pop($stack);
    }
}
