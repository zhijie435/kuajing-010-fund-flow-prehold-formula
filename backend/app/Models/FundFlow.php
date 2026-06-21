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

    public function generateFlowNo(): string
    {
        return 'FF' . date('YmdHis') . rand(1000, 9999);
    }

    public function getLatestBalance()
    {
        $sql = "SELECT balance FROM {$this->table} ORDER BY id DESC LIMIT 1";
        $result = $this->db->fetch($sql);
        return $result ? (float)$result['balance'] : 0;
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
}
