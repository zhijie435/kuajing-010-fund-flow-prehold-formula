<?php

use App\Services\Database;

class CreateExtensionsTable
{
    public function up(Database $db): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS extensions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(100) NOT NULL UNIQUE,
            description TEXT,
            version VARCHAR(50) DEFAULT '1.0.0',
            vendor VARCHAR(100),
            creator VARCHAR(100),
            status TINYINT DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );";
        $db->query($sql);

        $indexes = [
            "CREATE INDEX IF NOT EXISTS idx_extensions_vendor ON extensions(vendor)",
            "CREATE INDEX IF NOT EXISTS idx_extensions_status ON extensions(status)",
            "CREATE INDEX IF NOT EXISTS idx_extensions_creator ON extensions(creator)",
        ];

        foreach ($indexes as $indexSql) {
            $db->query($indexSql);
        }
    }

    public function down(Database $db): void
    {
        $dropIndexes = [
            "DROP INDEX IF EXISTS idx_extensions_creator",
            "DROP INDEX IF EXISTS idx_extensions_status",
            "DROP INDEX IF EXISTS idx_extensions_vendor",
        ];
        foreach ($dropIndexes as $indexSql) {
            $db->query($indexSql);
        }

        $db->query("DROP TABLE IF EXISTS extensions");
    }

    public function batch(): int
    {
        return 1;
    }

    public function description(): string
    {
        return '创建扩展包表 extensions 及索引';
    }
}
