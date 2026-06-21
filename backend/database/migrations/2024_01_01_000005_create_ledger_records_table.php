<?php

use App\Services\Database;

class CreateLedgerRecordsTable
{
    public function up(Database $db): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS ledger_records (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            record_type VARCHAR(50) NOT NULL,
            extension_id INTEGER,
            migration_id INTEGER,
            service_id INTEGER,
            bind_id INTEGER,
            action VARCHAR(100) NOT NULL,
            operator VARCHAR(100),
            remark TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );";
        $db->query($sql);

        $indexes = [
            "CREATE INDEX IF NOT EXISTS idx_ledger_type ON ledger_records(record_type)",
            "CREATE INDEX IF NOT EXISTS idx_ledger_extension ON ledger_records(extension_id)",
            "CREATE INDEX IF NOT EXISTS idx_ledger_migration ON ledger_records(migration_id)",
            "CREATE INDEX IF NOT EXISTS idx_ledger_service ON ledger_records(service_id)",
            "CREATE INDEX IF NOT EXISTS idx_ledger_operator ON ledger_records(operator)",
            "CREATE INDEX IF NOT EXISTS idx_ledger_created_at ON ledger_records(created_at)",
        ];

        foreach ($indexes as $indexSql) {
            $db->query($indexSql);
        }
    }

    public function down(Database $db): void
    {
        $dropIndexes = [
            "DROP INDEX IF EXISTS idx_ledger_created_at",
            "DROP INDEX IF EXISTS idx_ledger_operator",
            "DROP INDEX IF EXISTS idx_ledger_service",
            "DROP INDEX IF EXISTS idx_ledger_migration",
            "DROP INDEX IF EXISTS idx_ledger_extension",
            "DROP INDEX IF EXISTS idx_ledger_type",
        ];
        foreach ($dropIndexes as $indexSql) {
            $db->query($indexSql);
        }

        $db->query("DROP TABLE IF EXISTS ledger_records");
    }

    public function batch(): int
    {
        return 1;
    }

    public function description(): string
    {
        return '创建台账记录表 ledger_records 及索引';
    }
}
