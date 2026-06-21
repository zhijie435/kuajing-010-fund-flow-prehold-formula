<?php

namespace App\Controllers;

use App\Services\Router;
use App\Models\FundFlow;
use App\Models\OperationLog;
use App\Services\WithholdingCalculator;

class FundFlowController
{
    private $model;
    private $calculator;
    private $router;
    private $operationLog;

    public function __construct()
    {
        $this->model = new FundFlow();
        $this->calculator = new WithholdingCalculator();
        $this->router = new Router();
        $this->operationLog = new OperationLog();
    }

    private function enrichFlow($flow)
    {
        if (!$flow) return $flow;
        $status = (int)($flow['status'] ?? 1);
        $flow['status'] = $status;
        $flow['status_label'] = $this->model->getStatusLabel($status);
        $flow['status_tag_type'] = $this->model->getStatusTagType($status);
        $flow['status_description'] = $this->model->getStatusDescription($status);
        return $flow;
    }

    private function enrichFlows(array $flows): array
    {
        return array_map([$this, 'enrichFlow'], $flows);
    }

    private function getAvailableStatuses(int $currentStatus): array
    {
        $statuses = [];
        $allStatuses = $this->model->getStatusTypes();
        foreach ($allStatuses as $value => $label) {
            if ($this->model->canTransitionTo($currentStatus, $value)) {
                $statuses[] = [
                    'value' => $value,
                    'label' => $label,
                    'description' => $this->model->getStatusDescription($value)
                ];
            }
        }
        return $statuses;
    }

    private function enrichLogs(array $logs): array
    {
        foreach ($logs as &$log) {
            $log['action_label'] = $this->operationLog->getActionLabel($log['action']);
            if (!empty($log['old_value'])) {
                $log['old_value'] = json_decode($log['old_value'], true);
            }
            if (!empty($log['new_value'])) {
                $log['new_value'] = json_decode($log['new_value'], true);
            }
            if (!empty($log['extra'])) {
                $log['extra'] = json_decode($log['extra'], true);
            }
        }
        return $logs;
    }

    public function index()
    {
        $input = $this->router->getInput();
        $page = (int)($input['page'] ?? 1);
        $perPage = (int)($input['per_page'] ?? 20);
        $flowType = $input['flow_type'] ?? null;
        $direction = $input['direction'] ?? null;
        $status = $input['status'] ?? null;
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
        if ($direction !== null && $direction !== '') {
            $conditions['direction'] = (int)$direction;
        }
        if ($status !== null && $status !== '') {
            $conditions['status'] = (int)$status;
        }
        if ($orderNo) {
            $conditions['order_no'] = $orderNo;
        }
        if ($withholdingDetailId) {
            $conditions['withholding_detail_id'] = (int)$withholdingDetailId;
        }
        if ($relatedType && $relatedId) {
            $result = $this->model->getByRelated($relatedType, (int)$relatedId);
            $enriched = $this->enrichFlows($result);
            return $this->router->success([
                'data' => $enriched,
                'total' => count($enriched),
                'page' => 1,
                'per_page' => count($enriched),
                'total_pages' => 1,
                'status_types' => $this->model->getStatusTypes()
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

        $enrichedData = $this->enrichFlows($data);

        $result = [
            'data' => $enrichedData,
            'total' => (int)$total['total'],
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => (int)ceil($total['total'] / $perPage),
            'status_types' => $this->model->getStatusTypes()
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

        $enrichedFlow = $this->enrichFlow($flow);
        $currentStatus = (int)$enrichedFlow['status'];

        $rawLogs = $this->operationLog->getByTarget(OperationLog::TARGET_FUND_FLOW, $id);
        $logs = $this->enrichLogs($rawLogs);

        $enrichedFlow['operation_logs'] = $logs;
        $enrichedFlow['available_statuses'] = $this->getAvailableStatuses($currentStatus);

        return $this->router->success($enrichedFlow);
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

        $statusStatsSql = "SELECT status,
            SUM(CASE WHEN direction = 1 THEN amount ELSE -amount END) as net_amount,
            COUNT(*) as count
            FROM fund_flows $whereClause
            GROUP BY status
            ORDER BY status";

        $rawStatusStats = $db->fetchAll($statusStatsSql, $params);
        $byStatus = [];
        foreach ($rawStatusStats as $stat) {
            $statusVal = (int)$stat['status'];
            $byStatus[] = [
                'status' => $statusVal,
                'status_label' => $this->model->getStatusLabel($statusVal),
                'status_tag_type' => $this->model->getStatusTagType($statusVal),
                'count' => (int)$stat['count'],
                'net_amount' => round((float)$stat['net_amount'], 2)
            ];
        }

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
            'by_status' => $byStatus,
            'flow_types' => $this->model->getFlowTypes(),
            'direction_types' => $this->model->getDirectionTypes(),
            'status_types' => $this->model->getStatusTypes()
        ]);
    }

    public function store()
    {
        $input = $this->router->getInput();

        $flowType = $input['flow_type'] ?? '';
        $direction = (int)($input['direction'] ?? 0);
        $amount = (float)($input['amount'] ?? 0);
        $initialStatus = (int)($input['status'] ?? FundFlow::STATUS_COMPLETED);

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
            if ($initialStatus === FundFlow::STATUS_COMPLETED) {
                $newBalance = $direction === FundFlow::DIRECTION_IN
                    ? $latestBalance + $amount
                    : $latestBalance - $amount;
            } else {
                $newBalance = $latestBalance;
            }

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
                'status' => $initialStatus
            ];

            $id = $this->model->create($data);

            $this->operationLog->log(
                OperationLog::TARGET_FUND_FLOW,
                $id,
                OperationLog::ACTION_CREATE,
                [
                    'operator' => $data['operator'],
                    'remark' => '创建资金流水',
                    'new_value' => [
                        'flow_type' => $flowType,
                        'direction' => $direction,
                        'amount' => round($amount, 2),
                        'status' => $initialStatus
                    ]
                ]
            );

            $db->commit();

            return $this->router->success(['id' => $id], '创建成功');

        } catch (\Exception $e) {
            $db->rollBack();
            return $this->router->error('创建失败: ' . $e->getMessage());
        }
    }

    public function changeStatus($params)
    {
        $id = (int)$params['id'];
        $input = $this->router->getInput();
        $newStatus = (int)($input['status'] ?? -1);
        $operator = $input['operator'] ?? 'admin';
        $remark = $input['remark'] ?? '';

        $flow = $this->model->find($id);
        if (!$flow) {
            return $this->router->error('资金流水不存在', 404, 404);
        }

        $oldStatus = (int)$flow['status'];
        if ($oldStatus === $newStatus) {
            return $this->router->error('状态未发生变化', null, 400);
        }

        if (!$this->model->canTransitionTo($oldStatus, $newStatus)) {
            $oldLabel = $this->model->getStatusLabel($oldStatus);
            $newLabel = $this->model->getStatusLabel($newStatus);
            return $this->router->error("不允许从[{$oldLabel}]变更为[{$newLabel}]", null, 400);
        }

        $db = $this->model->getDb();
        $db->beginTransaction();

        try {
            $amount = (float)$flow['amount'];
            $direction = (int)$flow['direction'];
            $balanceDelta = 0;

            $originalLatestBalance = $this->model->getLatestBalance();

            if ($oldStatus === FundFlow::STATUS_COMPLETED && $newStatus !== FundFlow::STATUS_COMPLETED) {
                $balanceDelta = $direction === FundFlow::DIRECTION_IN ? -$amount : $amount;
            } elseif ($oldStatus !== FundFlow::STATUS_COMPLETED && $newStatus === FundFlow::STATUS_COMPLETED) {
                $balanceDelta = $direction === FundFlow::DIRECTION_IN ? $amount : -$amount;
            }

            $this->model->update($id, ['status' => $newStatus]);

            if (abs($balanceDelta) > 0.001) {
                $adjustSql = "UPDATE fund_flows SET balance = balance + ? WHERE id > ?";
                $db->query($adjustSql, [round($balanceDelta, 2), $id]);
            }

            $newStatusLabel = $this->model->getStatusLabel($newStatus);
            $this->operationLog->logStatusChange(
                OperationLog::TARGET_FUND_FLOW,
                $id,
                $oldStatus,
                $newStatus,
                $newStatusLabel,
                [
                    'operator' => $operator,
                    'remark' => $remark ?: ("状态变更: {$newStatusLabel}"),
                    'extra' => [
                        'balance_delta' => round($balanceDelta, 2),
                        'flow_id' => $id
                    ]
                ]
            );

            $db->commit();

            $updatedFlow = $this->enrichFlow($this->model->find($id));

            return $this->router->success([
                'id' => $id,
                'status' => $newStatus,
                'status_label' => $newStatusLabel,
                'balance_adjusted' => round($balanceDelta, 2),
                'flow' => $updatedFlow
            ], '状态变更成功');

        } catch (\Exception $e) {
            $db->rollBack();
            return $this->router->error('状态变更失败: ' . $e->getMessage());
        }
    }

    public function addRemark($params)
    {
        $id = (int)$params['id'];
        $input = $this->router->getInput();
        $remark = trim($input['remark'] ?? '');
        $operator = $input['operator'] ?? 'admin';

        if (empty($remark)) {
            return $this->router->error('备注内容不能为空');
        }

        $flow = $this->model->find($id);
        if (!$flow) {
            return $this->router->error('资金流水不存在', 404, 404);
        }

        $oldRemark = $flow['remark'] ?? '';
        $newRemark = empty($oldRemark) ? $remark : $oldRemark . "\n" . $remark;

        $db = $this->model->getDb();
        $db->beginTransaction();

        try {
            $this->model->update($id, ['remark' => $newRemark]);

            $this->operationLog->log(
                OperationLog::TARGET_FUND_FLOW,
                $id,
                OperationLog::ACTION_REMARK,
                [
                    'operator' => $operator,
                    'remark' => '添加备注',
                    'old_value' => ['remark' => $oldRemark],
                    'new_value' => ['remark' => $remark],
                    'extra' => ['full_remark' => $newRemark]
                ]
            );

            $db->commit();

            return $this->router->success([
                'id' => $id,
                'remark' => $newRemark
            ], '备注添加成功');

        } catch (\Exception $e) {
            $db->rollBack();
            return $this->router->error('备注添加失败: ' . $e->getMessage());
        }
    }

    public function operationLogs($params)
    {
        $id = (int)$params['id'];
        $input = $this->router->getInput();
        $page = (int)($input['page'] ?? 1);
        $perPage = (int)($input['per_page'] ?? 20);

        $flow = $this->model->find($id);
        if (!$flow) {
            return $this->router->error('资金流水不存在', 404, 404);
        }

        $offset = ($page - 1) * $perPage;
        $db = $this->operationLog->getDb();

        $sql = "SELECT * FROM operation_logs WHERE target_type = ? AND target_id = ? ORDER BY id DESC LIMIT ? OFFSET ?";
        $countSql = "SELECT COUNT(*) as total FROM operation_logs WHERE target_type = ? AND target_id = ?";

        $targetType = OperationLog::TARGET_FUND_FLOW;
        $data = $db->fetchAll($sql, [$targetType, $id, $perPage, $offset]);
        $total = $db->fetch($countSql, [$targetType, $id]);

        $enrichedLogs = $this->enrichLogs($data);

        return $this->router->success([
            'data' => $enrichedLogs,
            'total' => (int)$total['total'],
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => (int)ceil($total['total'] / $perPage)
        ]);
    }

    public function types()
    {
        return $this->router->success([
            'flow_types' => $this->model->getFlowTypes(),
            'direction_types' => $this->model->getDirectionTypes(),
            'status_types' => $this->model->getStatusTypes()
        ]);
    }
}
