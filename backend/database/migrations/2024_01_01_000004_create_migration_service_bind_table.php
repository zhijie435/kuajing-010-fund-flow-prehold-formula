<?php

use App\Services\Database;

class CreateMigrationServiceBindTable
{
    public function up(Database $db): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS migration_service_bind (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            migration_id INTEGER NOT NULL,
            service_id INTEGER NOT NULL,
            bind_type VARCHAR(50) DEFAULT 'direct',
            priority INTEGER DEFAULT 0,
            config TEXT,
            status TINYINT DEFAULT 2,
            reviewer VARCHAR(100),
            reviewed_at DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (migration_id) REFERENCES migrations(id) ON DELETE CASCADE,
            FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE,
            UNIQUE(migration_id, service_id)
        );";
        $db->query($sql);

        $indexes = [
            "CREATE INDEX IF NOT EXISTS idx_bind_migration_id ON migration_service_bind(migration_id)",
            "CREATE INDEX IF NOT EXISTS idx_bind_service_id ON migration_service_bind(service_id)",
            "CREATE INDEX IF NOT EXISTS idx_bind_type ON migration_service_bind(bind_type)",
            "CREATE INDEX IF NOT EXISTS idx_bind_status ON migration_service_bind(status)",
            "CREATE INDEX IF NOT EXISTS idx_bind_priority ON migration_service_bind(priority)",
            "CREATE INDEX IF NOT EXISTS idx_bind_reviewer ON migration_service_bind(reviewer)",
        ];

        foreach ($indexes as $indexSql) {
            $db->query($indexSql);
        }
    }

    public function down(Database $db): void
    {
        $dropIndexes = [
            "DROP INDEX IF EXISTS idx_bind_reviewer",
            "DROP INDEX IF EXISTS idx_bind_priority",
            "DROP INDEX IF EXISTS idx_bind_status",
            "DROP INDEX IF EXISTS idx_bind_type",
            "DROP INDEX IF EXISTS idx_bind_service_id",
            "DROP INDEX IF EXISTS idx_bind_migration_id",
        ];
        foreach ($dropIndexes as $indexSql) {
            $db->query($indexSql);
        }

        $db->query("DROP TABLE IF EXISTS migration_service_bind");
    }

    public function batch(): int
    {
        return 1;
    }

    public function description(): string
    {
        return '创建迁移服务绑定表 migration_service_bind 及索引';
    }
}
