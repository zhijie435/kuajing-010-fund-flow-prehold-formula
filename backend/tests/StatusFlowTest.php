<?php

namespace Tests;

use Tests\TestCase;
use App\Services\WithholdingCalculator;
use App\Models\WithholdingDetail;
use App\Models\FundFlow;
use App\Models\OperationLog;

class StatusFlowTest extends TestCase
{
    private $calculator;
    private $operationLog;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new WithholdingCalculator();
        $this->operationLog = new OperationLog();
    }

    public function testFundFlowStatusTransitions()
    {
        $this->assertTrue($this->fundFlowModel->canTransitionTo(FundFlow::STATUS_PENDING, FundFlow::STATUS_COMPLETED), '待处理可转为已完成');
        $this->assertTrue($this->fundFlowModel->canTransitionTo(FundFlow::STATUS_PENDING, FundFlow::STATUS_FAILED), '待处理可转为失败');
        $this->assertTrue($this->fundFlowModel->canTransitionTo(FundFlow::STATUS_PENDING, FundFlow::STATUS_CANCELLED), '待处理可转为已取消');
        $this->assertFalse($this->fundFlowModel->canTransitionTo(FundFlow::STATUS_PENDING, FundFlow::STATUS_REVERSED), '待处理不可直接转为已冲正');

        $this->assertTrue($this->fundFlowModel->canTransitionTo(FundFlow::STATUS_COMPLETED, FundFlow::STATUS_REVERSED), '已完成可转为已冲正');
        $this->assertFalse($this->fundFlowModel->canTransitionTo(FundFlow::STATUS_COMPLETED, FundFlow::STATUS_PENDING), '已完成不可转回待处理');
        $this->assertFalse($this->fundFlowModel->canTransitionTo(FundFlow::STATUS_COMPLETED, FundFlow::STATUS_FAILED), '已完成不可转为失败');

        $this->assertTrue($this->fundFlowModel->canTransitionTo(FundFlow::STATUS_FAILED, FundFlow::STATUS_PENDING), '失败可转为待处理（重试）');
        $this->assertTrue($this->fundFlowModel->canTransitionTo(FundFlow::STATUS_FAILED, FundFlow::STATUS_CANCELLED), '失败可转为已取消');
        $this->assertFalse($this->fundFlowModel->canTransitionTo(FundFlow::STATUS_FAILED, FundFlow::STATUS_COMPLETED), '失败不可直接转为已完成');

        $this->assertFalse($this->fundFlowModel->canTransitionTo(FundFlow::STATUS_CANCELLED, FundFlow::STATUS_COMPLETED), '已取消不可转为已完成');
        $this->assertFalse($this->fundFlowModel->canTransitionTo(FundFlow::STATUS_CANCELLED, FundFlow::STATUS_PENDING), '已取消不可转为待处理');

        $this->assertFalse($this->fundFlowModel->canTransitionTo(FundFlow::STATUS_REVERSED, FundFlow::STATUS_COMPLETED), '已冲正不可转回已完成');
        $this->assertFalse($this->fundFlowModel->canTransitionTo(FundFlow::STATUS_REVERSED, FundFlow::STATUS_PENDING), '已冲正不可转为待处理');
    }

    public function testWithholdingDetailStatusTransitions()
    {
        $this->assertTrue($this->detailModel->canTransitionTo(WithholdingDetail::STATUS_PENDING, WithholdingDetail::STATUS_COMPLETED), '待处理可转为已完成');
        $this->assertTrue($this->detailModel->canTransitionTo(WithholdingDetail::STATUS_PENDING, WithholdingDetail::STATUS_FAILED), '待处理可转为失败');
        $this->assertTrue($this->detailModel->canTransitionTo(WithholdingDetail::STATUS_PENDING, WithholdingDetail::STATUS_CANCELLED), '待处理可转为已取消');

        $this->assertTrue($this->detailModel->canTransitionTo(WithholdingDetail::STATUS_COMPLETED, WithholdingDetail::STATUS_SETTLED), '已完成可转为已结算');
        $this->assertTrue($this->detailModel->canTransitionTo(WithholdingDetail::STATUS_COMPLETED, WithholdingDetail::STATUS_REVERSED), '已完成可转为已冲正');
        $this->assertFalse($this->detailModel->canTransitionTo(WithholdingDetail::STATUS_COMPLETED, WithholdingDetail::STATUS_PENDING), '已完成不可转回待处理');

        $this->assertTrue($this->detailModel->canTransitionTo(WithholdingDetail::STATUS_FAILED, WithholdingDetail::STATUS_PENDING), '失败可转为待处理（重试）');
        $this->assertTrue($this->detailModel->canTransitionTo(WithholdingDetail::STATUS_FAILED, WithholdingDetail::STATUS_CANCELLED), '失败可转为已取消');

        $this->assertFalse($this->detailModel->canTransitionTo(WithholdingDetail::STATUS_CANCELLED, WithholdingDetail::STATUS_COMPLETED), '已取消不可转为已完成');
        $this->assertFalse($this->detailModel->canTransitionTo(WithholdingDetail::STATUS_CANCELLED, WithholdingDetail::STATUS_SETTLED), '已取消不可转为已结算');

        $this->assertFalse($this->detailModel->canTransitionTo(WithholdingDetail::STATUS_REVERSED, WithholdingDetail::STATUS_COMPLETED), '已冲正不可转回已完成');
        $this->assertFalse($this->detailModel->canTransitionTo(WithholdingDetail::STATUS_REVERSED, WithholdingDetail::STATUS_SETTLED), '已冲正不可转为已结算');

        $this->assertTrue($this->detailModel->canTransitionTo(WithholdingDetail::STATUS_SETTLED, WithholdingDetail::STATUS_REVERSED), '已结算可转为已冲正');
    }

    public function testFundFlowStatusLabels()
    {
        $this->assertEqual('待处理', $this->fundFlowModel->getStatusLabel(FundFlow::STATUS_PENDING), '待处理标签');
        $this->assertEqual('已完成', $this->fundFlowModel->getStatusLabel(FundFlow::STATUS_COMPLETED), '已完成标签');
        $this->assertEqual('失败', $this->fundFlowModel->getStatusLabel(FundFlow::STATUS_FAILED), '失败标签');
        $this->assertEqual('已取消', $this->fundFlowModel->getStatusLabel(FundFlow::STATUS_CANCELLED), '已取消标签');
        $this->assertEqual('已冲正', $this->fundFlowModel->getStatusLabel(FundFlow::STATUS_REVERSED), '已冲正标签');
    }

    public function testWithholdingDetailStatusLabels()
    {
        $this->assertEqual('待处理', $this->detailModel->getStatusLabel(WithholdingDetail::STATUS_PENDING), '待处理标签');
        $this->assertEqual('已完成', $this->detailModel->getStatusLabel(WithholdingDetail::STATUS_COMPLETED), '已完成标签');
        $this->assertEqual('失败', $this->detailModel->getStatusLabel(WithholdingDetail::STATUS_FAILED), '失败标签');
        $this->assertEqual('已取消', $this->detailModel->getStatusLabel(WithholdingDetail::STATUS_CANCELLED), '已取消标签');
        $this->assertEqual('已冲正', $this->detailModel->getStatusLabel(WithholdingDetail::STATUS_REVERSED), '已冲正标签');
        $this->assertEqual('已结算', $this->detailModel->getStatusLabel(WithholdingDetail::STATUS_SETTLED), '已结算标签');
    }

    public function testFundFlowStatusTagTypes()
    {
        $this->assertEqual('warning', $this->fundFlowModel->getStatusTagType(FundFlow::STATUS_PENDING), '待处理标签类型');
        $this->assertEqual('success', $this->fundFlowModel->getStatusTagType(FundFlow::STATUS_COMPLETED), '已完成标签类型');
        $this->assertEqual('danger', $this->fundFlowModel->getStatusTagType(FundFlow::STATUS_FAILED), '失败标签类型');
        $this->assertEqual('info', $this->fundFlowModel->getStatusTagType(FundFlow::STATUS_CANCELLED), '已取消标签类型');
        $this->assertEqual('primary', $this->fundFlowModel->getStatusTagType(FundFlow::STATUS_REVERSED), '已冲正标签类型');
    }

    public function testWithholdingDetailStatusTagTypes()
    {
        $this->assertEqual('warning', $this->detailModel->getStatusTagType(WithholdingDetail::STATUS_PENDING), '待处理标签类型');
        $this->assertEqual('success', $this->detailModel->getStatusTagType(WithholdingDetail::STATUS_COMPLETED), '已完成标签类型');
        $this->assertEqual('danger', $this->detailModel->getStatusTagType(WithholdingDetail::STATUS_FAILED), '失败标签类型');
        $this->assertEqual('info', $this->detailModel->getStatusTagType(WithholdingDetail::STATUS_CANCELLED), '已取消标签类型');
        $this->assertEqual('primary', $this->detailModel->getStatusTagType(WithholdingDetail::STATUS_REVERSED), '已冲正标签类型');
        $this->assertEqual('success', $this->detailModel->getStatusTagType(WithholdingDetail::STATUS_SETTLED), '已结算标签类型');
    }

    public function testFundFlowStatusDescriptions()
    {
        $this->assertEqual('流水已创建，等待处理完成', $this->fundFlowModel->getStatusDescription(FundFlow::STATUS_PENDING), '待处理描述');
        $this->assertEqual('流水处理成功，余额已更新', $this->fundFlowModel->getStatusDescription(FundFlow::STATUS_COMPLETED), '已完成描述');
        $this->assertEqual('流水处理失败，余额未变更', $this->fundFlowModel->getStatusDescription(FundFlow::STATUS_FAILED), '失败描述');
        $this->assertEqual('流水已被手动取消', $this->fundFlowModel->getStatusDescription(FundFlow::STATUS_CANCELLED), '已取消描述');
        $this->assertEqual('原流水已被冲正撤销', $this->fundFlowModel->getStatusDescription(FundFlow::STATUS_REVERSED), '已冲正描述');
    }

    public function testWithholdingDetailStatusDescriptions()
    {
        $this->assertEqual('预扣计算完成，等待关联资金流水确认', $this->detailModel->getStatusDescription(WithholdingDetail::STATUS_PENDING), '待处理描述');
        $this->assertEqual('预扣已完成，关联资金流水已到账', $this->detailModel->getStatusDescription(WithholdingDetail::STATUS_COMPLETED), '已完成描述');
        $this->assertEqual('预扣计算或关联流水处理失败', $this->detailModel->getStatusDescription(WithholdingDetail::STATUS_FAILED), '失败描述');
        $this->assertEqual('预扣已被手动取消', $this->detailModel->getStatusDescription(WithholdingDetail::STATUS_CANCELLED), '已取消描述');
        $this->assertEqual('原预扣已被冲正撤销', $this->detailModel->getStatusDescription(WithholdingDetail::STATUS_REVERSED), '已冲正描述');
        $this->assertEqual('预扣金额已完成最终结算', $this->detailModel->getStatusDescription(WithholdingDetail::STATUS_SETTLED), '已结算描述');
    }

    public function testCompletedFundFlowUpdatesBalance()
    {
        $initialBalance = $this->fundFlowModel->getLatestBalance();
        $this->assertEqual(0.0, (float)$initialBalance, '初始余额为0');

        $flowId = $this->fundFlowModel->create([
            'flow_no' => $this->fundFlowModel->generateFlowNo(),
            'flow_type' => FundFlow::TYPE_WITHHOLD,
            'direction' => FundFlow::DIRECTION_OUT,
            'amount' => 100.0,
            'balance' => -100.0,
            'currency' => 'CNY',
            'status' => FundFlow::STATUS_COMPLETED
        ]);

        $newBalance = $this->fundFlowModel->getLatestBalance();
        $this->assertEqual(-100.0, (float)$newBalance, '已完成预扣流水减少余额100元');
    }

    public function testPendingFundFlowDoesNotUpdateBalance()
    {
        $flowId = $this->fundFlowModel->create([
            'flow_no' => $this->fundFlowModel->generateFlowNo(),
            'flow_type' => FundFlow::TYPE_WITHHOLD,
            'direction' => FundFlow::DIRECTION_OUT,
            'amount' => 100.0,
            'balance' => 0.0,
            'currency' => 'CNY',
            'status' => FundFlow::STATUS_PENDING
        ]);

        $balance = $this->fundFlowModel->getLatestBalance();
        $this->assertEqual(0.0, (float)$balance, '待处理流水不影响余额');
    }

    public function testCreateWithholdingDetailAndFundFlowLinkage()
    {
        $calcResult = $this->calculator->calculate('ORDER_AMOUNT_RATE', [
            'order_amount' => 2000,
            'rate' => 0.05
        ], [
            'record' => true,
            'order_no' => 'ORDER-STATUS-001'
        ]);

        $this->assertNotNull($calcResult['detail_id'], '计算返回预扣明细ID');

        $detail = $this->detailModel->find($calcResult['detail_id']);
        $this->assertNotNull($detail, '预扣明细存在');
        $this->assertEqual(100.0, (float)$detail['result'], '预扣金额为100元');
        $this->assertEqual(WithholdingDetail::STATUS_COMPLETED, (int)$detail['status'], '预扣明细状态为已完成');

        $flows = $this->fundFlowModel->findByWithholdingDetailId($calcResult['detail_id']);
        $this->assertEqual(1, count($flows), '预扣明细关联1条资金流水');
        $flow = $flows[0];
        $this->assertEqual(FundFlow::STATUS_COMPLETED, (int)$flow['status'], '关联流水状态为已完成');
        $this->assertEqual(100.0, (float)$flow['amount'], '流水金额与预扣金额一致');
        $this->assertEqual(FundFlow::DIRECTION_OUT, (int)$flow['direction'], '资金方向为流出');
    }

    public function testPendingDetailSyncsPendingFlow()
    {
        $calcResult = $this->calculator->calculate('ORDER_AMOUNT_RATE', [
            'order_amount' => 500,
            'rate' => 0.1
        ], [
            'record' => true,
            'initial_status' => WithholdingDetail::STATUS_PENDING,
            'order_no' => 'ORDER-STATUS-002'
        ]);

        $detail = $this->detailModel->find($calcResult['detail_id']);
        $this->assertEqual(WithholdingDetail::STATUS_PENDING, (int)$detail['status'], '预扣明细初始为待处理');

        $flows = $this->fundFlowModel->findByWithholdingDetailId($calcResult['detail_id']);
        $this->assertEqual(1, count($flows), '关联1条流水');
        $this->assertEqual(FundFlow::STATUS_PENDING, (int)$flows[0]['status'], '关联流水也是待处理');
    }

    public function testDetailStatusTransitionPendingToCompleted()
    {
        $calcResult = $this->calculator->calculate('ORDER_AMOUNT_RATE', [
            'order_amount' => 1000,
            'rate' => 0.1
        ], [
            'record' => true,
            'initial_status' => WithholdingDetail::STATUS_PENDING
        ]);

        $detailId = $calcResult['detail_id'];
        $beforeBalance = $this->fundFlowModel->getLatestBalance();

        $db = $this->detailModel->getDb();
        $db->beginTransaction();

        try {
            $relatedFlows = $this->fundFlowModel->findByWithholdingDetailId($detailId);
            $totalBalanceAdjust = 0;
            $updatedFlowIds = [];
            $flowTargetStatus = FundFlow::STATUS_COMPLETED;

            foreach ($relatedFlows as $flow) {
                $flowId = (int)$flow['id'];
                $flowOldStatus = (int)$flow['status'];

                if (!$this->fundFlowModel->canTransitionTo($flowOldStatus, $flowTargetStatus)) {
                    continue;
                }

                $flowAmount = (float)$flow['amount'];
                $flowDirection = (int)$flow['direction'];
                $flowBalanceAdjust = 0;

                if ($flowOldStatus !== FundFlow::STATUS_COMPLETED && $flowTargetStatus === FundFlow::STATUS_COMPLETED) {
                    $flowBalanceAdjust = $flowDirection === FundFlow::DIRECTION_IN ? $flowAmount : -$flowAmount;
                }

                $this->fundFlowModel->update($flowId, ['status' => $flowTargetStatus]);
                $totalBalanceAdjust += $flowBalanceAdjust;
                $updatedFlowIds[] = $flowId;
            }

            if ($totalBalanceAdjust !== 0.0 && !empty($updatedFlowIds)) {
                $minUpdatedId = min($updatedFlowIds);
                $adjustSql = "UPDATE fund_flows SET balance = balance + ? WHERE id > ?";
                $db->query($adjustSql, [round($totalBalanceAdjust, 2), $minUpdatedId]);
            }

            $this->detailModel->update($detailId, ['status' => WithholdingDetail::STATUS_COMPLETED]);
            $db->commit();
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }

        $updatedDetail = $this->detailModel->find($detailId);
        $this->assertEqual(WithholdingDetail::STATUS_COMPLETED, (int)$updatedDetail['status'], '预扣明细转为已完成');

        $updatedFlows = $this->fundFlowModel->findByWithholdingDetailId($detailId);
        $this->assertEqual(1, count($updatedFlows), '关联流水存在');
        $this->assertEqual(FundFlow::STATUS_COMPLETED, (int)$updatedFlows[0]['status'], '关联流水同步转为已完成');

        $afterBalance = $this->fundFlowModel->getLatestBalance();
        $this->assertEqual(-100.0, (float)$afterBalance, '余额扣除100元（状态从待处理变为已完成时更新）');
    }

    public function testDetailStatusTransitionCompletedToReversed()
    {
        $calcResult = $this->calculator->calculate('ORDER_AMOUNT_RATE', [
            'order_amount' => 500,
            'rate' => 0.1
        ], ['record' => true]);

        $detailId = $calcResult['detail_id'];

        $flows = $this->fundFlowModel->findByWithholdingDetailId($detailId);
        $this->assertEqual(FundFlow::STATUS_COMPLETED, (int)$flows[0]['status'], '初始流水状态为已完成');

        $beforeBalance = (float)$flows[0]['balance'];

        $db = $this->detailModel->getDb();
        $db->beginTransaction();

        try {
            $relatedFlows = $this->fundFlowModel->findByWithholdingDetailId($detailId);
            $totalBalanceAdjust = 0;
            $updatedFlowIds = [];
            $flowTargetStatus = FundFlow::STATUS_REVERSED;

            foreach ($relatedFlows as $flow) {
                $flowId = (int)$flow['id'];
                $flowOldStatus = (int)$flow['status'];

                if (!$this->fundFlowModel->canTransitionTo($flowOldStatus, $flowTargetStatus)) {
                    continue;
                }

                $flowAmount = (float)$flow['amount'];
                $flowDirection = (int)$flow['direction'];
                $flowBalanceAdjust = 0;

                if ($flowOldStatus === FundFlow::STATUS_COMPLETED && $flowTargetStatus !== FundFlow::STATUS_COMPLETED) {
                    $flowBalanceAdjust = $flowDirection === FundFlow::DIRECTION_IN ? -$flowAmount : $flowAmount;
                }

                $this->fundFlowModel->update($flowId, ['status' => $flowTargetStatus]);
                $totalBalanceAdjust += $flowBalanceAdjust;
                $updatedFlowIds[] = $flowId;
            }

            if ($totalBalanceAdjust !== 0.0 && !empty($updatedFlowIds)) {
                $minUpdatedId = min($updatedFlowIds);
                $adjustSql = "UPDATE fund_flows SET balance = balance + ? WHERE id > ?";
                $db->query($adjustSql, [round($totalBalanceAdjust, 2), $minUpdatedId]);
            }

            $this->detailModel->update($detailId, ['status' => WithholdingDetail::STATUS_REVERSED]);
            $db->commit();
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }

        $updatedDetail = $this->detailModel->find($detailId);
        $this->assertEqual(WithholdingDetail::STATUS_REVERSED, (int)$updatedDetail['status'], '预扣明细转为已冲正');

        $updatedFlows = $this->fundFlowModel->findByWithholdingDetailId($detailId);
        $this->assertEqual(FundFlow::STATUS_REVERSED, (int)$updatedFlows[0]['status'], '关联流水同步转为已冲正');
    }

    public function testDetailStatusTransitionCompletedToSettled()
    {
        $calcResult = $this->calculator->calculate('ORDER_AMOUNT_RATE', [
            'order_amount' => 1000,
            'rate' => 0.05
        ], ['record' => true]);

        $detailId = $calcResult['detail_id'];

        $flowsBefore = $this->fundFlowModel->findByWithholdingDetailId($detailId);
        $beforeFlowStatus = (int)$flowsBefore[0]['status'];

        $db = $this->detailModel->getDb();
        $db->beginTransaction();

        try {
            $relatedFlows = $this->fundFlowModel->findByWithholdingDetailId($detailId);
            $flowTargetStatus = FundFlow::STATUS_COMPLETED;

            foreach ($relatedFlows as $flow) {
                $flowId = (int)$flow['id'];
                $flowOldStatus = (int)$flow['status'];

                if ($flowOldStatus === $flowTargetStatus) {
                    continue;
                }

                if (!$this->fundFlowModel->canTransitionTo($flowOldStatus, $flowTargetStatus)) {
                    continue;
                }

                $this->fundFlowModel->update($flowId, ['status' => $flowTargetStatus]);
            }

            $this->detailModel->update($detailId, ['status' => WithholdingDetail::STATUS_SETTLED]);
            $db->commit();
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }

        $updatedDetail = $this->detailModel->find($detailId);
        $this->assertEqual(WithholdingDetail::STATUS_SETTLED, (int)$updatedDetail['status'], '预扣明细转为已结算');

        $updatedFlows = $this->fundFlowModel->findByWithholdingDetailId($detailId);
        $this->assertEqual(FundFlow::STATUS_COMPLETED, (int)$updatedFlows[0]['status'], '已完成状态的流水在明细结算时保持已完成');
    }

    public function testDetailStatusTransitionPendingToCancelled()
    {
        $calcResult = $this->calculator->calculate('ORDER_AMOUNT_RATE', [
            'order_amount' => 1000,
            'rate' => 0.1
        ], [
            'record' => true,
            'initial_status' => WithholdingDetail::STATUS_PENDING
        ]);

        $detailId = $calcResult['detail_id'];
        $beforeBalance = $this->fundFlowModel->getLatestBalance();

        $db = $this->detailModel->getDb();
        $db->beginTransaction();

        try {
            $relatedFlows = $this->fundFlowModel->findByWithholdingDetailId($detailId);
            $flowTargetStatus = FundFlow::STATUS_CANCELLED;

            foreach ($relatedFlows as $flow) {
                $flowId = (int)$flow['id'];
                $flowOldStatus = (int)$flow['status'];

                if (!$this->fundFlowModel->canTransitionTo($flowOldStatus, $flowTargetStatus)) {
                    continue;
                }

                $this->fundFlowModel->update($flowId, ['status' => $flowTargetStatus]);
            }

            $this->detailModel->update($detailId, ['status' => WithholdingDetail::STATUS_CANCELLED]);
            $db->commit();
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }

        $updatedDetail = $this->detailModel->find($detailId);
        $this->assertEqual(WithholdingDetail::STATUS_CANCELLED, (int)$updatedDetail['status'], '预扣明细转为已取消');

        $updatedFlows = $this->fundFlowModel->findByWithholdingDetailId($detailId);
        $this->assertEqual(FundFlow::STATUS_CANCELLED, (int)$updatedFlows[0]['status'], '关联流水同步转为已取消');

        $afterBalance = $this->fundFlowModel->getLatestBalance();
        $this->assertEqual((float)$beforeBalance, (float)$afterBalance, '取消待处理预扣不影响余额');
    }

    public function testFundFlowBalanceOnMultipleCompletedFlows()
    {
        $this->fundFlowModel->create([
            'flow_no' => $this->fundFlowModel->generateFlowNo(),
            'flow_type' => FundFlow::TYPE_SETTLEMENT,
            'direction' => FundFlow::DIRECTION_IN,
            'amount' => 1000.0,
            'balance' => 1000.0,
            'currency' => 'CNY',
            'status' => FundFlow::STATUS_COMPLETED
        ]);

        $balanceAfterInflow = $this->fundFlowModel->getLatestBalance();
        $this->assertEqual(1000.0, (float)$balanceAfterInflow, '流入1000后余额为1000');

        $this->fundFlowModel->create([
            'flow_no' => $this->fundFlowModel->generateFlowNo(),
            'flow_type' => FundFlow::TYPE_WITHHOLD,
            'direction' => FundFlow::DIRECTION_OUT,
            'amount' => 200.0,
            'balance' => 800.0,
            'currency' => 'CNY',
            'status' => FundFlow::STATUS_COMPLETED
        ]);

        $balanceAfterOutflow = $this->fundFlowModel->getLatestBalance();
        $this->assertEqual(800.0, (float)$balanceAfterOutflow, '流出200后余额为800');
    }

    public function testOperationLogCreatedOnStatusChange()
    {
        $flowId = $this->fundFlowModel->create([
            'flow_no' => $this->fundFlowModel->generateFlowNo(),
            'flow_type' => FundFlow::TYPE_WITHHOLD,
            'direction' => FundFlow::DIRECTION_OUT,
            'amount' => 50.0,
            'balance' => -50.0,
            'currency' => 'CNY',
            'status' => FundFlow::STATUS_COMPLETED
        ]);

        $logsBefore = $this->operationLog->getByTarget(OperationLog::TARGET_FUND_FLOW, $flowId);
        $beforeCount = count($logsBefore);

        $this->operationLog->logStatusChange(
            OperationLog::TARGET_FUND_FLOW,
            $flowId,
            FundFlow::STATUS_COMPLETED,
            FundFlow::STATUS_REVERSED,
            '已冲正',
            ['operator' => 'tester', 'remark' => '测试冲正']
        );

        $logsAfter = $this->operationLog->getByTarget(OperationLog::TARGET_FUND_FLOW, $flowId);
        $afterCount = count($logsAfter);

        $this->assertEqual($beforeCount + 1, $afterCount, '状态变更后操作日志增加1条');

        $latestLog = $logsAfter[0];
        $this->assertEqual(OperationLog::ACTION_STATUS_CHANGE, $latestLog['action'], '日志动作为状态变更');
        $this->assertEqual('tester', $latestLog['operator'], '操作人正确');
    }

    public function testCannotTransitionToSameStatus()
    {
        $this->assertFalse($this->fundFlowModel->canTransitionTo(FundFlow::STATUS_COMPLETED, FundFlow::STATUS_COMPLETED), '不可转为相同状态');
        $this->assertFalse($this->detailModel->canTransitionTo(WithholdingDetail::STATUS_PENDING, WithholdingDetail::STATUS_PENDING), '不可转为相同状态');
    }

    public function testTerminalStatusCannotTransition()
    {
        $this->assertEqual(0, count(array_filter(
            [FundFlow::STATUS_COMPLETED, FundFlow::STATUS_PENDING, FundFlow::STATUS_FAILED, FundFlow::STATUS_CANCELLED, FundFlow::STATUS_REVERSED],
            function ($status) {
                return $this->fundFlowModel->canTransitionTo(FundFlow::STATUS_CANCELLED, $status);
            }
        )), '已取消为资金流水终态，不能再转换');

        $this->assertEqual(0, count(array_filter(
            [FundFlow::STATUS_COMPLETED, FundFlow::STATUS_PENDING, FundFlow::STATUS_FAILED, FundFlow::STATUS_CANCELLED, FundFlow::STATUS_REVERSED],
            function ($status) {
                return $this->fundFlowModel->canTransitionTo(FundFlow::STATUS_REVERSED, $status);
            }
        )), '已冲正为资金流水终态，不能再转换');

        $this->assertEqual(0, count(array_filter(
            [WithholdingDetail::STATUS_COMPLETED, WithholdingDetail::STATUS_PENDING, WithholdingDetail::STATUS_FAILED,
                WithholdingDetail::STATUS_CANCELLED, WithholdingDetail::STATUS_REVERSED, WithholdingDetail::STATUS_SETTLED],
            function ($status) {
                return $this->detailModel->canTransitionTo(WithholdingDetail::STATUS_CANCELLED, $status);
            }
        )), '已取消为预扣明细终态，不能再转换');

        $this->assertEqual(0, count(array_filter(
            [WithholdingDetail::STATUS_COMPLETED, WithholdingDetail::STATUS_PENDING, WithholdingDetail::STATUS_FAILED,
                WithholdingDetail::STATUS_CANCELLED, WithholdingDetail::STATUS_REVERSED, WithholdingDetail::STATUS_SETTLED],
            function ($status) {
                return $this->detailModel->canTransitionTo(WithholdingDetail::STATUS_REVERSED, $status);
            }
        )), '已冲正为预扣明细终态，不能再转换');
    }

    public function testFullLifecyclePendingCompletedSettledReversed()
    {
        $calcResult = $this->calculator->calculate('FIXED_PLUS_RATE', [
            'fixed_fee' => 10,
            'order_amount' => 500,
            'rate' => 0.02
        ], [
            'record' => true,
            'initial_status' => WithholdingDetail::STATUS_PENDING,
            'order_no' => 'LIFECYCLE-001'
        ]);

        $detailId = $calcResult['detail_id'];
        $detail = $this->detailModel->find($detailId);
        $this->assertEqual(WithholdingDetail::STATUS_PENDING, (int)$detail['status'], '生命周期1: 初始待处理');

        $flows = $this->fundFlowModel->findByWithholdingDetailId($detailId);
        $this->assertEqual(FundFlow::STATUS_PENDING, (int)$flows[0]['status'], '流水也是待处理');

        $this->detailModel->update($detailId, ['status' => WithholdingDetail::STATUS_COMPLETED]);
        $this->fundFlowModel->update($flows[0]['id'], ['status' => FundFlow::STATUS_COMPLETED]);

        $detail = $this->detailModel->find($detailId);
        $this->assertEqual(WithholdingDetail::STATUS_COMPLETED, (int)$detail['status'], '生命周期2: 已完成');

        $this->detailModel->update($detailId, ['status' => WithholdingDetail::STATUS_SETTLED]);
        $detail = $this->detailModel->find($detailId);
        $this->assertEqual(WithholdingDetail::STATUS_SETTLED, (int)$detail['status'], '生命周期3: 已结算');

        $this->detailModel->update($detailId, ['status' => WithholdingDetail::STATUS_REVERSED]);
        $this->fundFlowModel->update($flows[0]['id'], ['status' => FundFlow::STATUS_REVERSED]);

        $detail = $this->detailModel->find($detailId);
        $flows = $this->fundFlowModel->findByWithholdingDetailId($detailId);
        $this->assertEqual(WithholdingDetail::STATUS_REVERSED, (int)$detail['status'], '生命周期4: 已冲正（最终状态）');
        $this->assertEqual(FundFlow::STATUS_REVERSED, (int)$flows[0]['status'], '流水同步已冲正');
    }
}
