<?php

use App\Services\Database;

class CreateServicesTable
{
    public function up(Database $db): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS services (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(100) NOT NULL UNIQUE,
            description TEXT,
            service_type VARCHAR(50),
            endpoint VARCHAR(255),
            creator VARCHAR(100),
            status TINYINT DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );";
        $db->query($sql);

        $indexes = [
            "CREATE INDEX IF NOT EXISTS idx_services_service_type ON services(service_type)",
            "CREATE INDEX IF NOT EXISTS idx_services_status ON services(status)",
            "CREATE INDEX IF NOT EXISTS idx_services_creator ON services(creator)",
        ];

        foreach ($indexes as $indexSql) {
            $db->query($indexSql);
        }
    }

    public function down(Database $db): void
    {
        $dropIndexes = [
            "DROP INDEX IF EXISTS idx_services_creator",
            "DROP INDEX IF EXISTS idx_services_status",
            "DROP INDEX IF EXISTS idx_services_service_type",
        ];
        foreach ($dropIndexes as $indexSql) {
            $db->query($indexSql);
        }

        $db->query("DROP TABLE IF EXISTS services");
    }

    public function batch(): int
    {
        return 1;
    }

    public function description(): string
    {
        return '创建服务表 services 及索引';
    }
}
