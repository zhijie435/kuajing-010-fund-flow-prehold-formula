<?php

namespace App\Models;

class FundFlow extends Model
{
    protected $table = 'fund_flows';
    protected $primaryKey = 'id';
    protected $fillable = [
        'flow_no', 'flow_type', 'direction', 'amount', 'balance',
        'currency', 'related_type', 'related_id', 'withholding_detail_id',
        'order_no', 'operator', 'remark', 'status'
    ];
    protected $timestamps = false;

    const DIRECTION_IN = 1;
    const DIRECTION_OUT = 2;

    const TYPE_WITHHOLD = 'withholding';
    const TYPE_REFUND = 'refund';
    const TYPE_SETTLEMENT = 'settlement';
    const TYPE_ADJUST = 'adjust';

    const STATUS_PENDING = 0;
    const STATUS_COMPLETED = 1;
    const STATUS_FAILED = 2;
    const STATUS_CANCELLED = 3;
    const STATUS_REVERSED = 4;

    public function generateFlowNo(): string
    {
        $config = require __DIR__ . '/../../config/config.php';
        if (!is_array($config)) {
            $config = require __DIR__ . '/../../config/config.php';
        }
        $prefix = $config['fund_flow']['flow_no_prefix'] ?? 'FF';
        return $prefix . date('YmdHis') . rand(1000, 9999);
    }

    public function getLatestBalance(): float
    {
        $sql = "SELECT balance FROM {$this->table} ORDER BY id DESC LIMIT 1";
        $result = $this->db->fetch($sql);
        return $result ? round((float)$result['balance'], 2) : 0.0;
    }

    public function findByOrderNo(string $orderNo): array
    {
        return $this->where('order_no', '=', $orderNo);
    }

    public function findByWithholdingDetailId(int $detailId): array
    {
        return $this->where('withholding_detail_id', '=', $detailId);
    }

    public function getFlowTypes(): array
    {
        return [
            self::TYPE_WITHHOLD => '预扣',
            self::TYPE_REFUND => '退款',
            self::TYPE_SETTLEMENT => '结算',
            self::TYPE_ADJUST => '调整'
        ];
    }

    public function getDirectionTypes(): array
    {
        return [
            self::DIRECTION_IN => '流入',
            self::DIRECTION_OUT => '流出'
        ];
    }

    public function getByRelated(string $relatedType, int $relatedId): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE related_type = ? AND related_id = ? ORDER BY id DESC";
        return $this->db->fetchAll($sql, [$relatedType, $relatedId]);
    }

    public function getStatusTypes(): array
    {
        return [
            self::STATUS_PENDING => '待处理',
            self::STATUS_COMPLETED => '已完成',
            self::STATUS_FAILED => '失败',
            self::STATUS_CANCELLED => '已取消',
            self::STATUS_REVERSED => '已冲正'
        ];
    }

    public function getStatusLabel(int $status): string
    {
        $types = $this->getStatusTypes();
        return $types[$status] ?? '未知状态';
    }

    public function getStatusTagType(int $status): string
    {
        $map = [
            self::STATUS_PENDING => 'warning',
            self::STATUS_COMPLETED => 'success',
            self::STATUS_FAILED => 'danger',
            self::STATUS_CANCELLED => 'info',
            self::STATUS_REVERSED => 'primary'
        ];
        return $map[$status] ?? '';
    }

    public function getStatusDescription(int $status): string
    {
        $map = [
            self::STATUS_PENDING => '流水已创建，等待处理完成',
            self::STATUS_COMPLETED => '流水处理成功，余额已更新',
            self::STATUS_FAILED => '流水处理失败，余额未变更',
            self::STATUS_CANCELLED => '流水已被手动取消',
            self::STATUS_REVERSED => '原流水已被冲正撤销'
        ];
        return $map[$status] ?? '';
    }

    public function canTransitionTo(int $currentStatus, int $newStatus): bool
    {
        $transitions = [
            self::STATUS_PENDING => [self::STATUS_COMPLETED, self::STATUS_FAILED, self::STATUS_CANCELLED],
            self::STATUS_COMPLETED => [self::STATUS_REVERSED],
            self::STATUS_FAILED => [self::STATUS_PENDING, self::STATUS_CANCELLED],
            self::STATUS_CANCELLED => [],
            self::STATUS_REVERSED => []
        ];
        return in_array($newStatus, $transitions[$currentStatus] ?? []);
    }
}
