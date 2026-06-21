<?php

use App\Services\Database;

class CreateMigrationsTable
{
    public function up(Database $db): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS migrations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            extension_id INTEGER NOT NULL,
            filename VARCHAR(255) NOT NULL,
            version VARCHAR(50) NOT NULL,
            description TEXT,
            batch INTEGER DEFAULT 0,
            migrate_path VARCHAR(255),
            status TINYINT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (extension_id) REFERENCES extensions(id) ON DELETE CASCADE,
            UNIQUE(extension_id, filename)
        );";
        $db->query($sql);

        $indexes = [
            "CREATE INDEX IF NOT EXISTS idx_migrations_extension_id ON migrations(extension_id)",
            "CREATE INDEX IF NOT EXISTS idx_migrations_version ON migrations(version)",
            "CREATE INDEX IF NOT EXISTS idx_migrations_status ON migrations(status)",
            "CREATE INDEX IF NOT EXISTS idx_migrations_batch ON migrations(batch)",
        ];

        foreach ($indexes as $indexSql) {
            $db->query($indexSql);
        }
    }

    public function down(Database $db): void
    {
        $dropIndexes = [
            "DROP INDEX IF EXISTS idx_migrations_batch",
            "DROP INDEX IF EXISTS idx_migrations_status",
            "DROP INDEX IF EXISTS idx_migrations_version",
            "DROP INDEX IF EXISTS idx_migrations_extension_id",
        ];
        foreach ($dropIndexes as $indexSql) {
            $db->query($indexSql);
        }

        $db->query("DROP TABLE IF EXISTS migrations");
    }

    public function batch(): int
    {
        return 1;
    }

    public function description(): string
    {
        return '创建迁移文件表 migrations 及索引';
    }
}
