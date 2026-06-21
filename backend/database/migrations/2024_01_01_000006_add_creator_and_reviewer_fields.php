<?php

use App\Services\Database;

class AddCreatorAndReviewerFields
{
    public function up(Database $db): void
    {
        $extColumns = $db->query("PRAGMA table_info(extensions)")->fetchAll();
        $extColumnNames = array_column($extColumns, 'name');
        if (!in_array('creator', $extColumnNames)) {
            $db->query("ALTER TABLE extensions ADD COLUMN creator VARCHAR(100)");
        }

        $svcColumns = $db->query("PRAGMA table_info(services)")->fetchAll();
        $svcColumnNames = array_column($svcColumns, 'name');
        if (!in_array('creator', $svcColumnNames)) {
            $db->query("ALTER TABLE services ADD COLUMN creator VARCHAR(100)");
        }

        $bindColumns = $db->query("PRAGMA table_info(migration_service_bind)")->fetchAll();
        $bindColumnNames = array_column($bindColumns, 'name');
        if (!in_array('reviewer', $bindColumnNames)) {
            $db->query("ALTER TABLE migration_service_bind ADD COLUMN reviewer VARCHAR(100)");
        }
        if (!in_array('reviewed_at', $bindColumnNames)) {
            $db->query("ALTER TABLE migration_service_bind ADD COLUMN reviewed_at DATETIME");
        }
        if (!in_array('updated_at', $bindColumnNames)) {
            $db->query("ALTER TABLE migration_service_bind ADD COLUMN updated_at DATETIME");
        }
    }

    public function down(Database $db): void
    {
    }

    public function batch(): int
    {
        return 2;
    }

    public function description(): string
    {
        return '为 extensions、services、migration_service_bind 表添加 creator/reviewer 字段';
    }
}
