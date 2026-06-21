<?php

use App\Services\Database;

class CreateOperationLogsTable
{
    public function up(Database $db): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS operation_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            target_type VARCHAR(50) NOT NULL,
            target_id INTEGER NOT NULL,
            action VARCHAR(50) NOT NULL,
            old_value TEXT,
            new_value TEXT,
            operator VARCHAR(100),
            remark TEXT,
            extra TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );";
        $db->query($sql);

        $indexes = [
            "CREATE INDEX IF NOT EXISTS idx_operation_target ON operation_logs(target_type, target_id)",
            "CREATE INDEX IF NOT EXISTS idx_operation_action ON operation_logs(action)",
            "CREATE INDEX IF NOT EXISTS idx_operation_created_at ON operation_logs(created_at)",
        ];

        foreach ($indexes as $indexSql) {
            $db->query($indexSql);
        }
    }

    public function down(Database $db): void
    {
        $dropIndexes = [
            "DROP INDEX IF EXISTS idx_operation_created_at",
            "DROP INDEX IF EXISTS idx_operation_action",
            "DROP INDEX IF EXISTS idx_operation_target",
        ];
        foreach ($dropIndexes as $indexSql) {
            $db->query($indexSql);
        }

        $db->query("DROP TABLE IF EXISTS operation_logs");
    }

    public function batch(): int
    {
        return 6;
    }

    public function description(): string
    {
        return '创建操作记录表 operation_logs 及索引';
    }
}
