<?php

namespace App\Controllers;

use App\Services\Router;
use App\Models\FundFlow;
use App\Services\WithholdingCalculator;

class FundFlowController
{
    private $model;
    private $calculator;
    private $router;

    public function __construct()
    {
        $this->model = new FundFlow();
        $this->calculator = new WithholdingCalculator();
        $this->router = new Router();
    }

    public function index()
    {
        $input = $this->router->getInput();
        $page = (int)($input['page'] ?? 1);
        $perPage = (int)($input['per_page'] ?? 20);
        $flowType = $input['flow_type'] ?? null;
        $direction = $input['direction'] ?? null;
        $orderNo = $input['order_no'] ?? null;
        $relatedType = $input['related_type'] ?? null;
        $relatedId = $input['related_id'] ?? null;
        $withholdingDetailId = $input['withholding_detail_id'] ?? null;
        $startDate = $input['start_date'] ?? null;
        $endDate = $input['end_date'] ?? null;
        
        $conditions = [];
        
        if ($flowType) {
            $conditions['flow_type'] = $flowType;
        }
        if ($direction) {
            $conditions['direction'] = (int)$direction;
        }
        if ($orderNo) {
            $conditions['order_no'] = $orderNo;
        }
        if ($withholdingDetailId) {
            $conditions['withholding_detail_id'] = (int)$withholdingDetailId;
        }
        if ($relatedType && $relatedId) {
            $result = $this->model->getByRelated($relatedType, (int)$relatedId);
            return $this->router->success([
                'data' => $result,
                'total' => count($result),
                'page' => 1,
                'per_page' => count($result),
                'total_pages' => 1
            ]);
        }
        
        $db = $this->model->getDb();
        $whereParts = [];
        $params = [];
        
        if (!empty($conditions)) {
            foreach ($conditions as $column => $value) {
                $whereParts[] = "$column = ?";
                $params[] = $value;
            }
        }
        
        if ($startDate) {
            $whereParts[] = "created_at >= ?";
            $params[] = $startDate . ' 00:00:00';
        }
        if ($endDate) {
            $whereParts[] = "created_at <= ?";
            $params[] = $endDate . ' 23:59:59';
        }
        
        $whereClause = !empty($whereParts) ? 'WHERE ' . implode(' AND ', $whereParts) : '';
        $offset = ($page - 1) * $perPage;
        
        $sql = "SELECT * FROM fund_flows $whereClause ORDER BY id DESC LIMIT ? OFFSET ?";
        $countSql = "SELECT COUNT(*) as total FROM fund_flows $whereClause";
        
        $queryParams = array_merge($params, [$perPage, $offset]);
        $data = $db->fetchAll($sql, $queryParams);
        $total = $db->fetch($countSql, $params);
        
        $result = [
            'data' => $data,
            'total' => (int)$total['total'],
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => (int)ceil($total['total'] / $perPage)
        ];
        
        return $this->router->success($result);
    }

    public function show($params)
    {
        $id = (int)$params['id'];
        $flow = $this->model->find($id);
        
        if (!$flow) {
            return $this->router->error('资金流水不存在', 404, 404);
        }
        
        return $this->router->success($flow);
    }

    public function stats()
    {
        $input = $this->router->getInput();
        $startDate = $input['start_date'] ?? null;
        $endDate = $input['end_date'] ?? null;
        
        $db = $this->model->getDb();
        
        $whereParts = [];
        $params = [];
        
        if ($startDate) {
            $whereParts[] = "created_at >= ?";
            $params[] = $startDate . ' 00:00:00';
        }
        if ($endDate) {
            $whereParts[] = "created_at <= ?";
            $params[] = $endDate . ' 23:59:59';
        }
        
        $whereClause = !empty($whereParts) ? 'WHERE ' . implode(' AND ', $whereParts) : '';
        
        $statsSql = "SELECT
            SUM(CASE WHEN direction = 1 THEN amount ELSE 0 END) as total_in,
            SUM(CASE WHEN direction = 2 THEN amount ELSE 0 END) as total_out,
            SUM(CASE WHEN direction = 1 THEN amount ELSE -amount END) as net_amount,
            COUNT(*) as total_count
            FROM fund_flows $whereClause";
        
        $stats = $db->fetch($statsSql, $params);
        
        $typeStatsSql = "SELECT flow_type, direction,
            SUM(amount) as total_amount,
            COUNT(*) as count
            FROM fund_flows $whereClause
            GROUP BY flow_type, direction
            ORDER BY flow_type, direction";
        
        $typeStats = $db->fetchAll($typeStatsSql, $params);
        
        $latestBalance = $this->model->getLatestBalance();
        
        return $this->router->success([
            'summary' => [
                'total_in' => round((float)($stats['total_in'] ?? 0), 2),
                'total_out' => round((float)($stats['total_out'] ?? 0), 2),
                'net_amount' => round((float)($stats['net_amount'] ?? 0), 2),
                'total_count' => (int)($stats['total_count'] ?? 0),
                'current_balance' => round($latestBalance, 2)
            ],
            'by_type' => $typeStats,
            'flow_types' => $this->model->getFlowTypes(),
            'direction_types' => $this->model->getDirectionTypes()
        ]);
    }

    public function store()
    {
        $input = $this->router->getInput();
        
        $flowType = $input['flow_type'] ?? '';
        $direction = (int)($input['direction'] ?? 0);
        $amount = (float)($input['amount'] ?? 0);
        
        if (empty($flowType)) {
            return $this->router->error('流水类型不能为空');
        }
        if (!in_array($direction, [FundFlow::DIRECTION_IN, FundFlow::DIRECTION_OUT])) {
            return $this->router->error('资金方向错误');
        }
        if ($amount <= 0) {
            return $this->router->error('金额必须大于0');
        }
        
        $db = $this->model->getDb();
        $db->beginTransaction();
        
        try {
            $latestBalance = $this->model->getLatestBalance();
            $newBalance = $direction === FundFlow::DIRECTION_IN 
                ? $latestBalance + $amount 
                : $latestBalance - $amount;
            
            $data = [
                'flow_no' => $this->model->generateFlowNo(),
                'flow_type' => $flowType,
                'direction' => $direction,
                'amount' => round($amount, 2),
                'balance' => round($newBalance, 2),
                'currency' => $input['currency'] ?? 'CNY',
                'related_type' => $input['related_type'] ?? null,
                'related_id' => $input['related_id'] ?? null,
                'withholding_detail_id' => $input['withholding_detail_id'] ?? null,
                'order_no' => $input['order_no'] ?? null,
                'operator' => $input['operator'] ?? 'admin',
                'remark' => $input['remark'] ?? null,
                'status' => 1
            ];
            
            $id = $this->model->create($data);
            $db->commit();
            
            return $this->router->success(['id' => $id], '创建成功');
            
        } catch (\Exception $e) {
            $db->rollBack();
            return $this->router->error('创建失败: ' . $e->getMessage());
        }
    }

    public function types()
    {
        return $this->router->success([
            'flow_types' => $this->model->getFlowTypes(),
            'direction_types' => $this->model->getDirectionTypes()
        ]);
    }
}
