<?php

namespace App\Services;

use App\Models\OperationLog;

trait ControllerHelper
{
    protected function enrichLogs(array $logs): array
    {
        $operationLog = new OperationLog();
        foreach ($logs as &$log) {
            $log['action_label'] = $operationLog->getActionLabel($log['action']);
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

    protected function getAvailableStatuses($model, int $currentStatus): array
    {
        $statuses = [];
        $allStatuses = $model->getStatusTypes();
        foreach ($allStatuses as $value => $label) {
            if ($model->canTransitionTo($currentStatus, $value)) {
                $statuses[] = [
                    'value' => $value,
                    'label' => $label,
                    'description' => $model->getStatusDescription($value)
                ];
            }
        }
        return $statuses;
    }
}
