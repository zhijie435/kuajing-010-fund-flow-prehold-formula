<?php

use App\Services\Database;

class CreateWithholdingFormulasTable
{
    public function up(Database $db): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS withholding_formulas (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(200) NOT NULL,
            code VARCHAR(100) NOT NULL UNIQUE,
            formula TEXT NOT NULL,
            description TEXT,
            variables TEXT,
            status TINYINT DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );";
        $db->query($sql);

        $indexes = [
            "CREATE INDEX IF NOT EXISTS idx_formula_code ON withholding_formulas(code)",
            "CREATE INDEX IF NOT EXISTS idx_formula_status ON withholding_formulas(status)",
        ];

        foreach ($indexes as $indexSql) {
            $db->query($indexSql);
        }

        $defaultFormulas = [
            [
                'name' => '订单金额比例预扣',
                'code' => 'ORDER_AMOUNT_RATE',
                'formula' => 'order_amount * rate',
                'description' => '按照订单金额的一定比例进行预扣',
                'variables' => json_encode([
                    ['name' => 'order_amount', 'label' => '订单金额', 'type' => 'number', 'default' => 0],
                    ['name' => 'rate', 'label' => '预扣比例', 'type' => 'number', 'default' => 0.05]
                ])
            ],
            [
                'name' => '阶梯式预扣',
                'code' => 'STEP_WITHHOLDING',
                'formula' => 'order_amount <= 1000 ? order_amount * 0.03 : (order_amount <= 5000 ? order_amount * 0.05 : order_amount * 0.08)',
                'description' => '根据订单金额区间采用不同比例预扣',
                'variables' => json_encode([
                    ['name' => 'order_amount', 'label' => '订单金额', 'type' => 'number', 'default' => 0]
                ])
            ],
            [
                'name' => '固定金额加比例',
                'code' => 'FIXED_PLUS_RATE',
                'formula' => 'fixed_fee + order_amount * rate',
                'description' => '固定手续费加订单金额比例',
                'variables' => json_encode([
                    ['name' => 'fixed_fee', 'label' => '固定手续费', 'type' => 'number', 'default' => 10],
                    ['name' => 'order_amount', 'label' => '订单金额', 'type' => 'number', 'default' => 0],
                    ['name' => 'rate', 'label' => '比例', 'type' => 'number', 'default' => 0.02]
                ])
            ],
            [
                'name' => '库存占用预扣',
                'code' => 'INVENTORY_OCCUPY',
                'formula' => 'quantity * unit_price * occupy_rate + storage_fee',
                'description' => '根据库存占用数量和单价计算预扣金额',
                'variables' => json_encode([
                    ['name' => 'quantity', 'label' => '库存数量', 'type' => 'number', 'default' => 0],
                    ['name' => 'unit_price', 'label' => '单价', 'type' => 'number', 'default' => 0],
                    ['name' => 'occupy_rate', 'label' => '占用费率', 'type' => 'number', 'default' => 0.1],
                    ['name' => 'storage_fee', 'label' => '仓储费', 'type' => 'number', 'default' => 5]
                ])
            ]
        ];

        foreach ($defaultFormulas as $formula) {
            $checkSql = "SELECT id FROM withholding_formulas WHERE code = ?";
            $exists = $db->query($checkSql, [$formula['code']])->fetch();
            if (!$exists) {
                $insertSql = "INSERT INTO withholding_formulas (name, code, formula, description, variables) VALUES (?, ?, ?, ?, ?)";
                $db->query($insertSql, [
                    $formula['name'],
                    $formula['code'],
                    $formula['formula'],
                    $formula['description'],
                    $formula['variables']
                ]);
            }
        }
    }

    public function down(Database $db): void
    {
        $dropIndexes = [
            "DROP INDEX IF EXISTS idx_formula_status",
            "DROP INDEX IF EXISTS idx_formula_code",
        ];
        foreach ($dropIndexes as $indexSql) {
            $db->query($indexSql);
        }

        $db->query("DROP TABLE IF EXISTS withholding_formulas");
    }

    public function batch(): int
    {
        return 3;
    }

    public function description(): string
    {
        return '创建预扣公式表 withholding_formulas 及索引，初始化默认公式';
    }
}
