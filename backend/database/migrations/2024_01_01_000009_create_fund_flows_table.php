<?php

use App\Services\Database;

class CreateFundFlowsTable
{
    public function up(Database $db): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS fund_flows (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            flow_no VARCHAR(50) NOT NULL UNIQUE,
            flow_type VARCHAR(50) NOT NULL,
            direction TINYINT NOT NULL,
            amount DECIMAL(15,2) NOT NULL,
            balance DECIMAL(15,2) NOT NULL DEFAULT 0,
            currency VARCHAR(10) DEFAULT 'CNY',
            related_type VARCHAR(50),
            related_id INTEGER,
            withholding_detail_id INTEGER,
            order_no VARCHAR(100),
            operator VARCHAR(100),
            remark TEXT,
            status TINYINT DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );";
        $db->query($sql);

        $indexes = [
            "CREATE INDEX IF NOT EXISTS idx_fund_flow_no ON fund_flows(flow_no)",
            "CREATE INDEX IF NOT EXISTS idx_fund_flow_type ON fund_flows(flow_type)",
            "CREATE INDEX IF NOT EXISTS idx_fund_direction ON fund_flows(direction)",
            "CREATE INDEX IF NOT EXISTS idx_fund_withholding ON fund_flows(withholding_detail_id)",
            "CREATE INDEX IF NOT EXISTS idx_fund_order_no ON fund_flows(order_no)",
            "CREATE INDEX IF NOT EXISTS idx_fund_related ON fund_flows(related_type, related_id)",
            "CREATE INDEX IF NOT EXISTS idx_fund_created_at ON fund_flows(created_at)",
        ];

        foreach ($indexes as $indexSql) {
            $db->query($indexSql);
        }
    }

    public function down(Database $db): void
    {
        $dropIndexes = [
            "DROP INDEX IF EXISTS idx_fund_created_at",
            "DROP INDEX IF EXISTS idx_fund_related",
            "DROP INDEX IF EXISTS idx_fund_order_no",
            "DROP INDEX IF EXISTS idx_fund_withholding",
            "DROP INDEX IF EXISTS idx_fund_direction",
            "DROP INDEX IF EXISTS idx_fund_flow_type",
            "DROP INDEX IF EXISTS idx_fund_flow_no",
        ];
        foreach ($dropIndexes as $indexSql) {
            $db->query($indexSql);
        }

        $db->query("DROP TABLE IF EXISTS fund_flows");
    }

    public function batch(): int
    {
        return 5;
    }

    public function description(): string
    {
        return '创建资金流水表 fund_flows 及索引';
    }
}
