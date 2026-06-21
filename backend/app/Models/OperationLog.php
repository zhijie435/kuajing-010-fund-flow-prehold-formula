<?php

namespace App\Models;

class OperationLog extends Model
{
    protected $table = 'operation_logs';
    protected $primaryKey = 'id';
    protected $fillable = [
        'target_type', 'target_id', 'action', 'old_value',
        'new_value', 'operator', 'remark', 'extra'
    ];
    protected $timestamps = false;

    const TARGET_FUND_FLOW = 'fund_flow';
    const TARGET_WITHHOLDING_DETAIL = 'withholding_detail';

    const ACTION_CREATE = 'create';
    const ACTION_UPDATE = 'update';
    const ACTION_STATUS_CHANGE = 'status_change';
    const ACTION_DELETE = 'delete';
    const ACTION_CANCEL = 'cancel';
    const ACTION_REVERSE = 'reverse';
    const ACTION_SETTLE = 'settle';
    const ACTION_REMARK = 'remark';

    public function getTargetTypes(): array
    {
        return [
            self::TARGET_FUND_FLOW => '资金流水',
            self::TARGET_WITHHOLDING_DETAIL => '预扣明细'
        ];
    }

    public function getActionTypes(): array
    {
        return [
            self::ACTION_CREATE => '创建',
            self::ACTION_UPDATE => '更新',
            self::ACTION_STATUS_CHANGE => '状态变更',
            self::ACTION_DELETE => '删除',
            self::ACTION_CANCEL => '取消',
            self::ACTION_REVERSE => '冲正',
            self::ACTION_SETTLE => '结算',
            self::ACTION_REMARK => '备注'
        ];
    }

    public function getActionLabel(string $action): string
    {
        $types = $this->getActionTypes();
        return $types[$action] ?? $action;
    }

    public function getByTarget(string $targetType, int $targetId): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE target_type = ? AND target_id = ? ORDER BY id DESC";
        return $this->db->fetchAll($sql, [$targetType, $targetId]);
    }

    public function log(string $targetType, int $targetId, string $action, array $context = []): int
    {
        $data = [
            'target_type' => $targetType,
            'target_id' => $targetId,
            'action' => $action,
            'old_value' => isset($context['old_value']) ? json_encode($context['old_value'], JSON_UNESCAPED_UNICODE) : null,
            'new_value' => isset($context['new_value']) ? json_encode($context['new_value'], JSON_UNESCAPED_UNICODE) : null,
            'operator' => $context['operator'] ?? 'system',
            'remark' => $context['remark'] ?? null,
            'extra' => isset($context['extra']) ? json_encode($context['extra'], JSON_UNESCAPED_UNICODE) : null
        ];
        return $this->create($data);
    }

    public function logStatusChange(string $targetType, int $targetId, int $oldStatus, int $newStatus, string $statusLabel, array $context = []): int
    {
        $remark = $context['remark'] ?? "状态变更: {$statusLabel}";
        $data = [
            'target_type' => $targetType,
            'target_id' => $targetId,
            'action' => self::ACTION_STATUS_CHANGE,
            'old_value' => json_encode(['status' => $oldStatus], JSON_UNESCAPED_UNICODE),
            'new_value' => json_encode(['status' => $newStatus], JSON_UNESCAPED_UNICODE),
            'operator' => $context['operator'] ?? 'system',
            'remark' => $remark,
            'extra' => isset($context['extra']) ? json_encode($context['extra'], JSON_UNESCAPED_UNICODE) : null
        ];
        return $this->create($data);
    }
}
