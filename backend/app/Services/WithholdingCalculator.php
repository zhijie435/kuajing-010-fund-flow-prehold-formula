<?php

namespace App\Services;

use App\Models\WithholdingFormula;
use App\Models\WithholdingDetail;
use App\Models\FundFlow;

class WithholdingCalculator
{
    private $formulaModel;
    private $detailModel;
    private $fundFlowModel;

    public function __construct()
    {
        $this->formulaModel = new WithholdingFormula();
        $this->detailModel = new WithholdingDetail();
        $this->fundFlowModel = new FundFlow();
    }

    public function calculate(string $formulaCode, array $variables, array $options = []): array
    {
        $formula = $this->formulaModel->findByCode($formulaCode);
        
        if (!$formula) {
            throw new \Exception("Formula with code '$formulaCode' not found");
        }
        
        if ($formula['status'] != 1) {
            throw new \Exception("Formula '$formulaCode' is not active");
        }
        
        $formulaVariables = json_decode($formula['variables'], true) ?: [];
        $processedVariables = $this->processVariables($formulaVariables, $variables);
        
        $result = $this->evaluateFormula($formula['formula'], $processedVariables);
        
        $shouldRecord = $options['record'] ?? true;
        $detailId = null;
        
        if ($shouldRecord) {
            $db = $this->formulaModel->getDb();
            $db->beginTransaction();
            
            try {
                $detailId = $this->recordDetail($formula, $processedVariables, $result, $options);
                $this->recordFundFlow($formula, $result, $detailId, $options);
                $db->commit();
            } catch (\Exception $e) {
                $db->rollBack();
                throw $e;
            }
        }
        
        return [
            'formula_id' => $formula['id'],
            'formula_code' => $formula['code'],
            'formula_name' => $formula['name'],
            'formula' => $formula['formula'],
            'variables' => $processedVariables,
            'result' => round($result, 2),
            'detail_id' => $detailId,
            'calculated_at' => date('Y-m-d H:i:s')
        ];
    }

    public function preview(string $formulaCode, array $variables): array
    {
        return $this->calculate($formulaCode, $variables, ['record' => false]);
    }

    public function validateFormula(string $formula, array $variables): array
    {
        $errors = [];
        
        if (empty($formula)) {
            $errors[] = '公式不能为空';
        }
        
        if (!$this->checkFormulaSecurity($formula)) {
            $errors[] = '公式包含非法字符或函数';
        }
        
        $extractedVars = $this->extractVariables($formula);
        $varNames = array_column($variables, 'name');
        
        $missingVars = array_diff($extractedVars, $varNames);
        if (!empty($missingVars)) {
            $errors[] = '公式中存在未定义的变量: ' . implode(', ', $missingVars);
        }
        
        $testVars = [];
        foreach ($variables as $var) {
            $testVars[$var['name']] = $var['default'] ?? 100;
        }
        
        try {
            $this->evaluateFormula($formula, $testVars);
        } catch (\Exception $e) {
            $errors[] = '公式计算错误: ' . $e->getMessage();
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'extracted_variables' => $extractedVars
        ];
    }

    private function processVariables(array $definedVars, array $inputVars): array
    {
        $processed = [];
        
        foreach ($definedVars as $defVar) {
            $name = $defVar['name'];
            $default = $defVar['default'] ?? 0;
            $value = $inputVars[$name] ?? $default;
            
            if (!is_numeric($value)) {
                throw new \Exception("Variable '$name' must be numeric");
            }
            
            $processed[$name] = (float)$value;
        }
        
        return $processed;
    }

    private function evaluateFormula(string $formula, array $variables): float
    {
        if (!$this->checkFormulaSecurity($formula)) {
            throw new \Exception('Formula contains unsafe code');
        }
        
        $replacements = [];
        foreach ($variables as $name => $value) {
            $replacements['#' . $name . '#'] = (float)$value;
        }
        
        $expression = strtr($formula, $this->buildVariablePlaceholders($variables));
        
        $expression = preg_replace_callback('/\b([a-zA-Z_][a-zA-Z0-9_]*)\b/', function($matches) use ($variables) {
            $varName = $matches[1];
            if (array_key_exists($varName, $variables)) {
                return (float)$variables[$varName];
            }
            throw new \Exception("Undefined variable: $varName");
        }, $expression);
        
        $result = $this->safeEval($expression);
        
        if (!is_numeric($result)) {
            throw new \Exception('Formula evaluation did not return a numeric result');
        }
        
        return (float)$result;
    }

    private function buildVariablePlaceholders(array $variables): array
    {
        $placeholders = [];
        foreach ($variables as $name => $value) {
            $placeholders['/' . '\b' . preg_quote($name, '/') . '\b' . '/'] = (float)$value;
        }
        return $placeholders;
    }

    private function safeEval(string $expression): float
    {
        $allowedPatterns = [
            'numbers' => '\d+\.?\d*',
            'operators' => '\+\-\*\/',
            'parentheses' => '\(\)',
            'comparisons' => '<>=!?&|',
            'whitespace' => '\s',
            'ternary' => '\?:'
        ];
        
        $pattern = '/[^' . implode('', $allowedPatterns) . ']/';
        if (preg_match($pattern, $expression)) {
            throw new \Exception('Expression contains invalid characters');
        }
        
        $result = null;
        $code = '$result = ' . $expression . ';';
        
        try {
            eval($code);
        } catch (\Throwable $e) {
            throw new \Exception('Formula evaluation error: ' . $e->getMessage());
        }
        
        return (float)$result;
    }

    private function checkFormulaSecurity(string $formula): bool
    {
        $dangerousPatterns = [
            '/\b(exec|system|shell_exec|passthru|popen|proc_open)\s*\(/i',
            '/\b(eval|assert|create_function)\s*\(/i',
            '/\b(include|require|include_once|require_once)\s*[\(\/]/i',
            '/\$\{/',
            '/\$\_/',
            '/new\s+/i',
            '/->/',
            '/::/',
            '/`/',
            '/\b(echo|print|var_dump|print_r)\s*\(/i',
            '/\b(fopen|fwrite|file_put_contents|file_get_contents)\s*\(/i',
            '/\b(curl|fsockopen|pfsockopen)\s*\(/i'
        ];
        
        foreach ($dangerousPatterns as $pattern) {
            if (preg_match($pattern, $formula)) {
                return false;
            }
        }
        
        return true;
    }

    private function extractVariables(string $formula): array
    {
        preg_match_all('/\b([a-zA-Z_][a-zA-Z0-9_]*)\b/', $formula, $matches);
        $keywords = ['true', 'false', 'null', 'and', 'or', 'xor', 'if', 'else', 'elseif', 'while', 'for', 'foreach', 'return'];
        $variables = array_diff($matches[1], $keywords);
        return array_values(array_unique($variables));
    }

    private function recordDetail(array $formula, array $variables, float $result, array $options): int
    {
        $detailData = [
            'formula_id' => $formula['id'],
            'formula_code' => $formula['code'],
            'formula_name' => $formula['name'],
            'formula' => $formula['formula'],
            'variables' => json_encode($variables, JSON_UNESCAPED_UNICODE),
            'result' => round($result, 2),
            'order_no' => $options['order_no'] ?? null,
            'related_type' => $options['related_type'] ?? null,
            'related_id' => $options['related_id'] ?? null,
            'operator' => $options['operator'] ?? 'system',
            'remark' => $options['remark'] ?? null,
            'status' => 1
        ];
        
        return $this->detailModel->create($detailData);
    }

    private function recordFundFlow(array $formula, float $result, int $detailId, array $options): int
    {
        $latestBalance = $this->fundFlowModel->getLatestBalance();
        $newBalance = $latestBalance - $result;
        
        $flowData = [
            'flow_no' => $this->fundFlowModel->generateFlowNo(),
            'flow_type' => FundFlow::TYPE_WITHHOLD,
            'direction' => FundFlow::DIRECTION_OUT,
            'amount' => round($result, 2),
            'balance' => round($newBalance, 2),
            'currency' => 'CNY',
            'related_type' => $options['related_type'] ?? null,
            'related_id' => $options['related_id'] ?? null,
            'withholding_detail_id' => $detailId,
            'order_no' => $options['order_no'] ?? null,
            'operator' => $options['operator'] ?? 'system',
            'remark' => $options['remark'] ?? ('预扣: ' . $formula['name']),
            'status' => 1
        ];
        
        return $this->fundFlowModel->create($flowData);
    }

    public function getFormulaModel(): WithholdingFormula
    {
        return $this->formulaModel;
    }

    public function getDetailModel(): WithholdingDetail
    {
        return $this->detailModel;
    }

    public function getFundFlowModel(): FundFlow
    {
        return $this->fundFlowModel;
    }
}
