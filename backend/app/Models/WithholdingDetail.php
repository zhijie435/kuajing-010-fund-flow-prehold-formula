<?php

namespace App\Models;

class WithholdingDetail extends Model
{
    protected $table = 'withholding_details';
    protected $primaryKey = 'id';
    protected $fillable = [
        'formula_id', 'formula_code', 'formula_name', 'formula',
        'variables', 'result', 'order_no', 'related_type', 'related_id',
        'operator', 'remark', 'status'
    ];
    protected $timestamps = false;

    const STATUS_PENDING = 0;
    const STATUS_COMPLETED = 1;
    const STATUS_FAILED = 2;
    const STATUS_CANCELLED = 3;
    const STATUS_REVERSED = 4;
    const STATUS_SETTLED = 5;

    public function findByOrderNo(string $orderNo): array
    {
        return $this->where('order_no', '=', $orderNo);
    }

    public function findByFormulaId(int $formulaId): array
    {
        return $this->where('formula_id', '=', $formulaId);
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
            self::STATUS_REVERSED => '已冲正',
            self::STATUS_SETTLED => '已结算'
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
            self::STATUS_REVERSED => 'primary',
            self::STATUS_SETTLED => 'success'
        ];
        return $map[$status] ?? '';
    }

    public function getStatusDescription(int $status): string
    {
        $map = [
            self::STATUS_PENDING => '预扣计算完成，等待关联资金流水确认',
            self::STATUS_COMPLETED => '预扣已完成，关联资金流水已到账',
            self::STATUS_FAILED => '预扣计算或关联流水处理失败',
            self::STATUS_CANCELLED => '预扣已被手动取消',
            self::STATUS_REVERSED => '原预扣已被冲正撤销',
            self::STATUS_SETTLED => '预扣金额已完成最终结算'
        ];
        return $map[$status] ?? '';
    }

    public function canTransitionTo(int $currentStatus, int $newStatus): bool
    {
        $transitions = [
            self::STATUS_PENDING => [self::STATUS_COMPLETED, self::STATUS_FAILED, self::STATUS_CANCELLED],
            self::STATUS_COMPLETED => [self::STATUS_SETTLED, self::STATUS_REVERSED],
            self::STATUS_FAILED => [self::STATUS_PENDING, self::STATUS_CANCELLED],
            self::STATUS_CANCELLED => [],
            self::STATUS_REVERSED => [],
            self::STATUS_SETTLED => [self::STATUS_REVERSED]
        ];
        return in_array($newStatus, $transitions[$currentStatus] ?? []);
    }
}
