<?php

use App\Services\Database;

class CreateWithholdingDetailsTable
{
    public function up(Database $db): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS withholding_details (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            formula_id INTEGER NOT NULL,
            formula_code VARCHAR(100) NOT NULL,
            formula_name VARCHAR(200) NOT NULL,
            formula TEXT NOT NULL,
            variables TEXT NOT NULL,
            result DECIMAL(15,2) NOT NULL,
            order_no VARCHAR(100),
            related_type VARCHAR(50),
            related_id INTEGER,
            operator VARCHAR(100),
            remark TEXT,
            status TINYINT DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );";
        $db->query($sql);

        $indexes = [
            "CREATE INDEX IF NOT EXISTS idx_withholding_formula_id ON withholding_details(formula_id)",
            "CREATE INDEX IF NOT EXISTS idx_withholding_formula_code ON withholding_details(formula_code)",
            "CREATE INDEX IF NOT EXISTS idx_withholding_order_no ON withholding_details(order_no)",
            "CREATE INDEX IF NOT EXISTS idx_withholding_related ON withholding_details(related_type, related_id)",
            "CREATE INDEX IF NOT EXISTS idx_withholding_created_at ON withholding_details(created_at)",
        ];

        foreach ($indexes as $indexSql) {
            $db->query($indexSql);
        }
    }

    public function down(Database $db): void
    {
        $dropIndexes = [
            "DROP INDEX IF EXISTS idx_withholding_created_at",
            "DROP INDEX IF EXISTS idx_withholding_related",
            "DROP INDEX IF EXISTS idx_withholding_order_no",
            "DROP INDEX IF EXISTS idx_withholding_formula_code",
            "DROP INDEX IF EXISTS idx_withholding_formula_id",
        ];
        foreach ($dropIndexes as $indexSql) {
            $db->query($indexSql);
        }

        $db->query("DROP TABLE IF EXISTS withholding_details");
    }

    public function batch(): int
    {
        return 4;
    }

    public function description(): string
    {
        return '创建预扣明细表 withholding_details 及索引';
    }
}
