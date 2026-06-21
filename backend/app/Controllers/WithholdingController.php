<?php

namespace App\Controllers;

use App\Services\Router;
use App\Services\WithholdingCalculator;
use App\Models\WithholdingDetail;
use App\Models\FundFlow;
use App\Models\OperationLog;

class WithholdingController
{
    private $calculator;
    private $router;
    private $detailModel;
    private $fundFlowModel;
    private $operationLog;

    public function __construct()
    {
        $this->calculator = new WithholdingCalculator();
        $this->router = new Router();
        $this->detailModel = $this->calculator->getDetailModel();
        $this->fundFlowModel = $this->calculator->getFundFlowModel();
        $this->operationLog = new OperationLog();
    }

    private function enrichDetail($detail)
    {
        if (!$detail) return $detail;
        $status = (int)($detail['status'] ?? 1);
        $detail['status'] = $status;
        $detail['status_label'] = $this->detailModel->getStatusLabel($status);
        $detail['status_tag_type'] = $this->detailModel->getStatusTagType($status);
        $detail['status_description'] = $this->detailModel->getStatusDescription($status);
        if (!empty($detail['variables']) && is_string($detail['variables'])) {
            $detail['variables'] = json_decode($detail['variables'], true);
        }
        return $detail;
    }

    private function enrichDetails(array $details): array
    {
        return array_map([$this, 'enrichDetail'], $details);
    }

    private function enrichFundFlowForDetail($flow)
    {
        if (!$flow) return $flow;
        $status = (int)($flow['status'] ?? 1);
        $flow['status'] = $status;
        $flow['status_label'] = $this->fundFlowModel->getStatusLabel($status);
        $flow['status_tag_type'] = $this->fundFlowModel->getStatusTagType($status);
        return $flow;
    }

    private function enrichFundFlowsForDetail(array $flows): array
    {
        return array_map([$this, 'enrichFundFlowForDetail'], $flows);
    }

    private function getAvailableDetailStatuses(int $currentStatus): array
    {
        $statuses = [];
        $allStatuses = $this->detailModel->getStatusTypes();
        foreach ($allStatuses as $value => $label) {
            if ($this->detailModel->canTransitionTo($currentStatus, $value)) {
                $statuses[] = [
                    'value' => $value,
                    'label' => $label,
                    'description' => $this->detailModel->getStatusDescription($value)
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

    public function calculate()
    {
        $input = $this->router->getInput();

        $formulaCode = $input['formula_code'] ?? '';
        $variables = $input['variables'] ?? [];
        $initialStatus = (int)($input['initial_status'] ?? WithholdingDetail::STATUS_COMPLETED);
        $options = [
            'order_no' => $input['order_no'] ?? null,
            'related_type' => $input['related_type'] ?? null,
            'related_id' => $input['related_id'] ?? null,
            'operator' => $input['operator'] ?? 'admin',
            'remark' => $input['remark'] ?? null,
            'record' => true,
            'initial_status' => $initialStatus
        ];

        if (empty($formulaCode)) {
            return $this->router->error('公式编码不能为空');
        }

        if (!is_array($variables)) {
            return $this->router->error('变量参数格式错误');
        }

        try {
            $result = $this->calculator->calculate($formulaCode, $variables, $options);

            if (!empty($result['detail_id'])) {
                $this->operationLog->log(
                    OperationLog::TARGET_WITHHOLDING_DETAIL,
                    (int)$result['detail_id'],
                    OperationLog::ACTION_CREATE,
                    [
                        'operator' => $options['operator'],
                        'remark' => '创建预扣明细',
                        'new_value' => [
                            'formula_code' => $formulaCode,
                            'result' => round($result['result'], 2),
                            'initial_status' => $initialStatus
                        ]
                    ]
                );
            }

            return $this->router->success($result, '计算成功');
        } catch (\Exception $e) {
            return $this->router->error('计算失败: ' . $e->getMessage(), null, null, [
                'rolled_back' => true,
                'error_detail' => $e->getMessage()
            ]);
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
                $initialStatus = (int)($item['initial_status'] ?? WithholdingDetail::STATUS_COMPLETED);
                $options = [
                    'order_no' => $item['order_no'] ?? null,
                    'related_type' => $item['related_type'] ?? null,
                    'related_id' => $item['related_id'] ?? null,
                    'operator' => $item['operator'] ?? 'admin',
                    'remark' => $item['remark'] ?? null,
                    'record' => true,
                    'initial_status' => $initialStatus
                ];

                try {
                    $result = $this->calculator->calculate($formulaCode, $variables, $options);

                    if (!empty($result['detail_id'])) {
                        $this->operationLog->log(
                            OperationLog::TARGET_WITHHOLDING_DETAIL,
                            (int)$result['detail_id'],
                            OperationLog::ACTION_CREATE,
                            [
                                'operator' => $options['operator'],
                                'remark' => '批量创建预扣明细',
                                'new_value' => [
                                    'formula_code' => $formulaCode,
                                    'result' => round($result['result'], 2),
                                    'batch_index' => $index,
                                    'initial_status' => $initialStatus
                                ]
                            ]
                        );
                    }

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
        $status = $input['status'] ?? null;
        $relatedType = $input['related_type'] ?? null;
        $relatedId = $input['related_id'] ?? null;

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
        if ($status !== null && $status !== '') {
            $conditions['status'] = (int)$status;
        }
        if ($relatedType && $relatedId) {
            $result = $this->detailModel->getByRelated($relatedType, (int)$relatedId);
            $enriched = $this->enrichDetails($result);
            return $this->router->success([
                'data' => $enriched,
                'total' => count($enriched),
                'page' => 1,
                'per_page' => count($enriched),
                'total_pages' => 1,
                'status_types' => $this->detailModel->getStatusTypes()
            ]);
        }

        $result = $this->detailModel->paginate($page, $perPage, $conditions);
        $result['data'] = $this->enrichDetails($result['data']);
        $result['status_types'] = $this->detailModel->getStatusTypes();

        return $this->router->success($result);
    }

    public function detail($params)
    {
        $id = (int)$params['id'];
        $detail = $this->detailModel->find($id);

        if (!$detail) {
            return $this->router->error('预扣明细不存在', 404, 404);
        }

        $enrichedDetail = $this->enrichDetail($detail);
        $currentStatus = (int)$enrichedDetail['status'];

        $fundFlows = $this->fundFlowModel->findByWithholdingDetailId($id);
        $enrichedDetail['fund_flows'] = $this->enrichFundFlowsForDetail($fundFlows);

        $rawLogs = $this->operationLog->getByTarget(OperationLog::TARGET_WITHHOLDING_DETAIL, $id);
        $enrichedDetail['operation_logs'] = $this->enrichLogs($rawLogs);

        $enrichedDetail['available_statuses'] = $this->getAvailableDetailStatuses($currentStatus);

        return $this->router->success($enrichedDetail);
    }

    public function changeStatus($params)
    {
        $id = (int)$params['id'];
        $input = $this->router->getInput();
        $newStatus = (int)($input['status'] ?? -1);
        $operator = $input['operator'] ?? 'admin';
        $remark = $input['remark'] ?? '';

        $detail = $this->detailModel->find($id);
        if (!$detail) {
            return $this->router->error('预扣明细不存在', 404, 404);
        }

        $oldStatus = (int)$detail['status'];
        if ($oldStatus === $newStatus) {
            return $this->router->error('状态未发生变化', null, 400);
        }

        if (!$this->detailModel->canTransitionTo($oldStatus, $newStatus)) {
            $oldLabel = $this->detailModel->getStatusLabel($oldStatus);
            $newLabel = $this->detailModel->getStatusLabel($newStatus);
            return $this->router->error("不允许从[{$oldLabel}]变更为[{$newLabel}]", null, 400);
        }

        $db = $this->detailModel->getDb();
        $db->beginTransaction();

        try {
            $relatedFlows = $this->fundFlowModel->findByWithholdingDetailId($id);

            $totalBalanceAdjust = 0;
            $updatedFlowIds = [];

            $originalLatestBalance = $this->fundFlowModel->getLatestBalance();

            $flowTargetStatus = $newStatus;
            if ($newStatus === WithholdingDetail::STATUS_SETTLED) {
                $flowTargetStatus = FundFlow::STATUS_COMPLETED;
            }

            foreach ($relatedFlows as $flow) {
                $flowId = (int)$flow['id'];
                $flowOldStatus = (int)$flow['status'];

                if ($flowOldStatus === $flowTargetStatus) {
                    continue;
                }

                if (!$this->fundFlowModel->canTransitionTo($flowOldStatus, $flowTargetStatus)) {
                    continue;
                }

                $flowAmount = (float)$flow['amount'];
                $flowDirection = (int)$flow['direction'];
                $flowBalanceAdjust = 0;

                if ($flowOldStatus === FundFlow::STATUS_COMPLETED && $flowTargetStatus !== FundFlow::STATUS_COMPLETED) {
                    $flowBalanceAdjust = $flowDirection === FundFlow::DIRECTION_IN ? -$flowAmount : $flowAmount;
                } elseif ($flowOldStatus !== FundFlow::STATUS_COMPLETED && $flowTargetStatus === FundFlow::STATUS_COMPLETED) {
                    $flowBalanceAdjust = $flowDirection === FundFlow::DIRECTION_IN ? $flowAmount : -$flowAmount;
                }

                $this->fundFlowModel->update($flowId, ['status' => $flowTargetStatus]);

                $this->operationLog->logStatusChange(
                    OperationLog::TARGET_FUND_FLOW,
                    $flowId,
                    $flowOldStatus,
                    $flowTargetStatus,
                    $this->fundFlowModel->getStatusLabel($flowTargetStatus),
                    [
                        'operator' => $operator,
                        'remark' => '关联预扣明细状态变更同步',
                        'extra' => [
                            'withholding_detail_id' => $id,
                            'withholding_detail_new_status' => $newStatus,
                            'withholding_detail_new_status_label' => $this->detailModel->getStatusLabel($newStatus),
                            'balance_adjust' => round($flowBalanceAdjust, 2)
                        ]
                    ]
                );

                $totalBalanceAdjust += $flowBalanceAdjust;
                $updatedFlowIds[] = $flowId;
            }

            if ($totalBalanceAdjust !== 0.0 && !empty($updatedFlowIds)) {
                $minUpdatedId = min($updatedFlowIds);
                $adjustSql = "UPDATE fund_flows SET balance = balance + ? WHERE id > ?";
                $db->query($adjustSql, [round($totalBalanceAdjust, 2), $minUpdatedId]);
            }

            $this->detailModel->update($id, ['status' => $newStatus]);

            $newStatusLabel = $this->detailModel->getStatusLabel($newStatus);
            $this->operationLog->logStatusChange(
                OperationLog::TARGET_WITHHOLDING_DETAIL,
                $id,
                $oldStatus,
                $newStatus,
                $newStatusLabel,
                [
                    'operator' => $operator,
                    'remark' => $remark ?: ("状态变更: {$newStatusLabel}"),
                    'extra' => [
                        'related_flows_count' => count($relatedFlows),
                        'total_balance_adjust' => round($totalBalanceAdjust, 2),
                        'updated_flow_ids' => $updatedFlowIds
                    ]
                ]
            );

            $db->commit();

            $updatedDetail = $this->enrichDetail($this->detailModel->find($id));
            $updatedFlows = $this->enrichFundFlowsForDetail($this->fundFlowModel->findByWithholdingDetailId($id));

            return $this->router->success([
                'id' => $id,
                'status' => $newStatus,
                'status_label' => $newStatusLabel,
                'flow_target_status' => $flowTargetStatus,
                'flow_target_status_label' => $this->fundFlowModel->getStatusLabel($flowTargetStatus),
                'total_balance_adjusted' => round($totalBalanceAdjust, 2),
                'related_flows_count' => count($relatedFlows),
                'related_flows_updated' => count($updatedFlowIds),
                'detail' => $updatedDetail,
                'fund_flows' => $updatedFlows
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

        $detail = $this->detailModel->find($id);
        if (!$detail) {
            return $this->router->error('预扣明细不存在', 404, 404);
        }

        $oldRemark = $detail['remark'] ?? '';
        $newRemark = empty($oldRemark) ? $remark : $oldRemark . "\n" . $remark;

        $db = $this->detailModel->getDb();
        $db->beginTransaction();

        try {
            $this->detailModel->update($id, ['remark' => $newRemark]);

            $this->operationLog->log(
                OperationLog::TARGET_WITHHOLDING_DETAIL,
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

        $detail = $this->detailModel->find($id);
        if (!$detail) {
            return $this->router->error('预扣明细不存在', 404, 404);
        }

        $offset = ($page - 1) * $perPage;
        $db = $this->operationLog->getDb();

        $sql = "SELECT * FROM operation_logs WHERE target_type = ? AND target_id = ? ORDER BY id DESC LIMIT ? OFFSET ?";
        $countSql = "SELECT COUNT(*) as total FROM operation_logs WHERE target_type = ? AND target_id = ?";

        $targetType = OperationLog::TARGET_WITHHOLDING_DETAIL;
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

    public function statusTypes()
    {
        return $this->router->success([
            'status_types' => $this->detailModel->getStatusTypes()
        ]);
    }
}
