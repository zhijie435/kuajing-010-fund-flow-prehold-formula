<?php

namespace App\Services;

use App\Models\WithholdingFormula;
use App\Models\WithholdingDetail;
use App\Models\FundFlow;
use App\Exceptions\FormulaException;

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
            throw FormulaException::notFound($formulaCode);
        }

        if ((int)$formula['status'] !== 1) {
            throw FormulaException::inactive($formulaCode);
        }

        $formulaVariables = json_decode($formula['variables'], true) ?: [];
        $processedVariables = $this->processVariables($formulaVariables, $variables);

        $result = $this->evaluateFormula($formula['formula'], $processedVariables);

        if ($result < 0) {
            throw FormulaException::negativeResult($result);
        }

        $shouldRecord = $options['record'] ?? true;
        $detailId = null;

        if ($shouldRecord) {
            $db = $this->formulaModel->getDb();
            $db->beginTransaction();

            try {
                $detailId = $this->recordDetail($formula, $processedVariables, $result, $options);
                if ($detailId <= 0) {
                    throw FormulaException::recordFailed('预扣明细创建返回无效ID');
                }

                $flowId = $this->recordFundFlow($formula, $result, $detailId, $options);
                if ($flowId <= 0) {
                    throw FormulaException::recordFailed('资金流水创建返回无效ID');
                }

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
            $result = $this->evaluateFormula($formula, $testVars);
            if ($result < 0) {
                $errors[] = '公式计算结果为负数 (' . round($result, 2) . ')，预扣金额不允许为负';
            }
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
        $missing = [];

        foreach ($definedVars as $defVar) {
            $name = $defVar['name'];
            $isRequired = !array_key_exists('default', $defVar) || $defVar['default'] === null || $defVar['default'] === '';
            $default = $defVar['default'] ?? 0;

            if (!array_key_exists($name, $inputVars) || $inputVars[$name] === null || $inputVars[$name] === '') {
                if ($isRequired) {
                    $missing[] = $name;
                    continue;
                }
                $value = $default;
            } else {
                $value = $inputVars[$name];
            }

            if (!is_numeric($value)) {
                throw FormulaException::invalidVariable($name);
            }

            $processed[$name] = (float)$value;
        }

        if (!empty($missing)) {
            throw FormulaException::missingVariables($missing);
        }

        return $processed;
    }

    private function evaluateFormula(string $formula, array $variables): float
    {
        if (!$this->checkFormulaSecurity($formula)) {
            throw FormulaException::unsafe();
        }

        $expression = preg_replace_callback('/\b([a-zA-Z_][a-zA-Z0-9_]*)\b/', function($matches) use ($variables) {
            $varName = $matches[1];
            if (array_key_exists($varName, $variables)) {
                return (float)$variables[$varName];
            }
            throw FormulaException::evaluationError("未定义变量: {$varName}");
        }, $formula);

        $result = $this->safeEval($expression);

        if (!is_numeric($result)) {
            throw FormulaException::evaluationError('计算结果不是有效数值');
        }

        return (float)$result;
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
            throw FormulaException::evaluationError('表达式包含非法字符');
        }

        $result = null;
        $code = '$result = ' . $expression . ';';

        try {
            eval($code);
        } catch (\Throwable $e) {
            throw FormulaException::evaluationError($e->getMessage());
        }

        if ($result === null) {
            throw FormulaException::evaluationError('表达式求值返回空值');
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
            '/\$_/',
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
        $initialStatus = $options['initial_status'] ?? WithholdingDetail::STATUS_COMPLETED;

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
            'status' => $initialStatus
        ];

        return $this->detailModel->create($detailData);
    }

    private function recordFundFlow(array $formula, float $result, int $detailId, array $options): int
    {
        $initialStatus = $options['initial_status'] ?? FundFlow::STATUS_COMPLETED;

        $latestBalance = $this->fundFlowModel->getLatestBalance();

        if ($initialStatus === FundFlow::STATUS_COMPLETED) {
            $newBalance = round($latestBalance - $result, 2);
        } else {
            $newBalance = round($latestBalance, 2);
        }

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
            'status' => $initialStatus
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
