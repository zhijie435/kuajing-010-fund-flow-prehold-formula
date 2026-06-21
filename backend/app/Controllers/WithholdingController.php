<?php

namespace App\Controllers;

use App\Services\Router;
use App\Services\WithholdingCalculator;

class WithholdingController
{
    private $calculator;
    private $router;

    public function __construct()
    {
        $this->calculator = new WithholdingCalculator();
        $this->router = new Router();
    }

    public function calculate()
    {
        $input = $this->router->getInput();
        
        $formulaCode = $input['formula_code'] ?? '';
        $variables = $input['variables'] ?? [];
        $options = [
            'order_no' => $input['order_no'] ?? null,
            'related_type' => $input['related_type'] ?? null,
            'related_id' => $input['related_id'] ?? null,
            'operator' => $input['operator'] ?? 'admin',
            'remark' => $input['remark'] ?? null,
            'record' => true
        ];
        
        if (empty($formulaCode)) {
            return $this->router->error('公式编码不能为空');
        }
        
        if (!is_array($variables)) {
            return $this->router->error('变量参数格式错误');
        }
        
        try {
            $result = $this->calculator->calculate($formulaCode, $variables, $options);
            return $this->router->success($result, '计算成功');
        } catch (\Exception $e) {
            return $this->router->error('计算失败: ' . $e->getMessage());
        }
    }

    public function preview()
    {
        $input = $this->router->getInput();
        
        $formulaCode = $input['formula_code'] ?? '';
        $variables = $input['variables'] ?? [];
        
        if (empty($formulaCode)) {
            return $this->router->error('公式编码不能为空');
        }
        
        if (!is_array($variables)) {
            return $this->router->error('变量参数格式错误');
        }
        
        try {
            $result = $this->calculator->preview($formulaCode, $variables);
            return $this->router->success($result, '预览成功');
        } catch (\Exception $e) {
            return $this->router->error('预览失败: ' . $e->getMessage());
        }
    }

    public function batchCalculate()
    {
        $input = $this->router->getInput();
        
        $items = $input['items'] ?? [];
        
        if (!is_array($items) || empty($items)) {
            return $this->router->error('批量计算数据不能为空');
        }
        
        $results = [];
        $db = $this->calculator->getFormulaModel()->getDb();
        
        try {
            $db->beginTransaction();
            
            foreach ($items as $index => $item) {
                $formulaCode = $item['formula_code'] ?? '';
                $variables = $item['variables'] ?? [];
                $options = [
                    'order_no' => $item['order_no'] ?? null,
                    'related_type' => $item['related_type'] ?? null,
                    'related_id' => $item['related_id'] ?? null,
                    'operator' => $item['operator'] ?? 'admin',
                    'remark' => $item['remark'] ?? null,
                    'record' => true
                ];
                
                try {
                    $result = $this->calculator->calculate($formulaCode, $variables, $options);
                    $results[] = [
                        'index' => $index,
                        'success' => true,
                        'data' => $result
                    ];
                } catch (\Exception $e) {
                    $results[] = [
                        'index' => $index,
                        'success' => false,
                        'error' => $e->getMessage()
                    ];
                }
            }
            
            $db->commit();
            
            $successCount = count(array_filter($results, fn($r) => $r['success']));
            $failCount = count($results) - $successCount;
            
            return $this->router->success([
                'results' => $results,
                'summary' => [
                    'total' => count($results),
                    'success' => $successCount,
                    'failed' => $failCount
                ]
            ], $failCount > 0 ? "部分计算失败: 成功{$successCount}条, 失败{$failCount}条" : '批量计算成功');
            
        } catch (\Exception $e) {
            $db->rollBack();
            return $this->router->error('批量计算失败: ' . $e->getMessage());
        }
    }

    public function details()
    {
        $input = $this->router->getInput();
        $page = (int)($input['page'] ?? 1);
        $perPage = (int)($input['per_page'] ?? 20);
        $formulaId = $input['formula_id'] ?? null;
        $formulaCode = $input['formula_code'] ?? null;
        $orderNo = $input['order_no'] ?? null;
        $relatedType = $input['related_type'] ?? null;
        $relatedId = $input['related_id'] ?? null;
        
        $detailModel = $this->calculator->getDetailModel();
        $conditions = [];
        
        if ($formulaId) {
            $conditions['formula_id'] = (int)$formulaId;
        }
        if ($formulaCode) {
            $conditions['formula_code'] = $formulaCode;
        }
        if ($orderNo) {
            $conditions['order_no'] = $orderNo;
        }
        if ($relatedType && $relatedId) {
            $result = $detailModel->getByRelated($relatedType, (int)$relatedId);
            foreach ($result as &$item) {
                if (!empty($item['variables'])) {
                    $item['variables'] = json_decode($item['variables'], true);
                }
            }
            return $this->router->success([
                'data' => $result,
                'total' => count($result),
                'page' => 1,
                'per_page' => count($result),
                'total_pages' => 1
            ]);
        }
        
        $result = $detailModel->paginate($page, $perPage, $conditions);
        
        foreach ($result['data'] as &$item) {
            if (!empty($item['variables'])) {
                $item['variables'] = json_decode($item['variables'], true);
            }
        }
        
        return $this->router->success($result);
    }

    public function detail($params)
    {
        $id = (int)$params['id'];
        $detailModel = $this->calculator->getDetailModel();
        $detail = $detailModel->find($id);
        
        if (!$detail) {
            return $this->router->error('预扣明细不存在', 404, 404);
        }
        
        if (!empty($detail['variables'])) {
            $detail['variables'] = json_decode($detail['variables'], true);
        }
        
        $fundFlows = $this->calculator->getFundFlowModel()->findByWithholdingDetailId($id);
        $detail['fund_flows'] = $fundFlows;
        
        return $this->router->success($detail);
    }
}
