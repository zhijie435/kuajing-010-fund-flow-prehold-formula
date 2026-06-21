<?php

namespace Tests;

require_once __DIR__ . '/../autoload.php';

use App\Services\Database;
use App\Models\WithholdingFormula;
use App\Models\WithholdingDetail;
use App\Models\FundFlow;

abstract class TestCase
{
    protected $db;
    protected $testDbPath;
    protected $formulaModel;
    protected $detailModel;
    protected $fundFlowModel;
    protected $passed = 0;
    protected $failed = 0;
    protected $errors = [];

    public function __construct()
    {
        $this->testDbPath = __DIR__ . '/../database/test_database.sqlite';
        $this->setupTestDatabase();
        $this->formulaModel = new WithholdingFormula();
        $this->detailModel = new WithholdingDetail();
        $this->fundFlowModel = new FundFlow();
    }

    protected function setupTestDatabase(): void
    {
        if (file_exists($this->testDbPath)) {
            unlink($this->testDbPath);
        }

        $dir = dirname($this->testDbPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $pdo = new \PDO('sqlite:' . $this->testDbPath);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);

        $pdo->query("PRAGMA foreign_keys = ON");
        $pdo->query("PRAGMA journal_mode = WAL");

        $pdo->query("CREATE TABLE IF NOT EXISTS withholding_formulas (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(200) NOT NULL,
            code VARCHAR(100) NOT NULL UNIQUE,
            formula TEXT NOT NULL,
            description TEXT,
            variables TEXT,
            status TINYINT DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $pdo->query("CREATE TABLE IF NOT EXISTS withholding_details (
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
        )");

        $pdo->query("CREATE TABLE IF NOT EXISTS fund_flows (
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
        )");

        $pdo->query("CREATE TABLE IF NOT EXISTS operation_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            target_type VARCHAR(50) NOT NULL,
            target_id INTEGER NOT NULL,
            action VARCHAR(50) NOT NULL,
            operator VARCHAR(100),
            remark TEXT,
            old_value TEXT,
            new_value TEXT,
            extra TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $defaultFormulas = [
            [
                'name' => '订单金额比例预扣',
                'code' => 'ORDER_AMOUNT_RATE',
                'formula' => 'order_amount * rate',
                'description' => '按照订单金额的一定比例进行预扣',
                'variables' => json_encode([
                    ['name' => 'order_amount', 'label' => '订单金额', 'type' => 'number', 'default' => 0],
                    ['name' => 'rate', 'label' => '预扣比例', 'type' => 'number', 'default' => 0.05]
                ]),
                'status' => 1
            ],
            [
                'name' => '阶梯式预扣',
                'code' => 'STEP_WITHHOLDING',
                'formula' => 'order_amount <= 1000 ? order_amount * 0.03 : order_amount <= 5000 ? order_amount * 0.05 : order_amount * 0.08',
                'description' => '根据订单金额区间采用不同比例预扣',
                'variables' => json_encode([
                    ['name' => 'order_amount', 'label' => '订单金额', 'type' => 'number', 'default' => 0]
                ]),
                'status' => 1
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
                ]),
                'status' => 1
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
                ]),
                'status' => 1
            ],
            [
                'name' => '已禁用公式',
                'code' => 'DISABLED_FORMULA',
                'formula' => 'order_amount * 0.1',
                'description' => '测试禁用公式',
                'variables' => json_encode([
                    ['name' => 'order_amount', 'label' => '订单金额', 'type' => 'number', 'default' => 0]
                ]),
                'status' => 0
            ]
        ];

        foreach ($defaultFormulas as $formula) {
            $stmt = $pdo->prepare(
                "INSERT INTO withholding_formulas (name, code, formula, description, variables, status) VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $formula['name'],
                $formula['code'],
                $formula['formula'],
                $formula['description'],
                $formula['variables'],
                $formula['status']
            ]);
        }

        $reflection = new \ReflectionProperty(Database::class, 'instance');
        $reflection->setAccessible(true);
        $reflection->setValue(null, null);

        $GLOBALS['test_db_path'] = $this->testDbPath;
    }

    protected function getDb()
    {
        return Database::getInstance();
    }

    protected function assertEqual($expected, $actual, string $message = ''): void
    {
        $backtrace = debug_backtrace();
        $caller = $backtrace[1];
        $testName = $caller['function'] ?? 'unknown';

        if ($expected === $actual) {
            $this->passed++;
            echo "  ✅ {$testName}: {$message}\n";
        } else {
            $this->failed++;
            $expectedStr = var_export($expected, true);
            $actualStr = var_export($actual, true);
            $this->errors[] = "{$testName}: {$message} - Expected: {$expectedStr}, Actual: {$actualStr}";
            echo "  ❌ {$testName}: {$message}\n";
            echo "     Expected: {$expectedStr}\n";
            echo "     Actual:   {$actualStr}\n";
        }
    }

    protected function assertNotEqual($expected, $actual, string $message = ''): void
    {
        $backtrace = debug_backtrace();
        $caller = $backtrace[1];
        $testName = $caller['function'] ?? 'unknown';

        if ($expected !== $actual) {
            $this->passed++;
            echo "  ✅ {$testName}: {$message}\n";
        } else {
            $this->failed++;
            $this->errors[] = "{$testName}: {$message} - Values should not be equal";
            echo "  ❌ {$testName}: {$message}\n";
        }
    }

    protected function assertTrue($condition, string $message = ''): void
    {
        $this->assertEqual(true, $condition, $message);
    }

    protected function assertFalse($condition, string $message = ''): void
    {
        $this->assertEqual(false, $condition, $message);
    }

    protected function assertNull($value, string $message = ''): void
    {
        $this->assertEqual(null, $value, $message);
    }

    protected function assertNotNull($value, string $message = ''): void
    {
        $this->assertNotEqual(null, $value, $message);
    }

    protected function assertException(callable $fn, string $expectedExceptionClass = \Exception::class, string $message = ''): void
    {
        $backtrace = debug_backtrace();
        $caller = $backtrace[1];
        $testName = $caller['function'] ?? 'unknown';

        $caught = null;
        try {
            $fn();
        } catch (\Exception $e) {
            $caught = $e;
        } catch (\Throwable $e) {
            $caught = $e;
        }

        if ($caught !== null && $caught instanceof $expectedExceptionClass) {
            $this->passed++;
            echo "  ✅ {$testName}: {$message}\n";
        } else {
            $this->failed++;
            if ($caught === null) {
                $this->errors[] = "{$testName}: {$message} - No exception was thrown";
                echo "  ❌ {$testName}: {$message} - No exception was thrown\n";
            } else {
                $this->errors[] = "{$testName}: {$message} - Expected {$expectedExceptionClass}, got " . get_class($caught);
                echo "  ❌ {$testName}: {$message}\n";
                echo "     Expected exception: {$expectedExceptionClass}\n";
                echo "     Got exception: " . get_class($caught) . "\n";
            }
        }
    }

    protected function assertArrayHasKey($key, $array, string $message = ''): void
    {
        $this->assertTrue(is_array($array) && isset($array[$key]), $message);
    }

    protected function assertCount(int $expectedCount, $array, string $message = ''): void
    {
        $this->assertEqual($expectedCount, is_array($array) || $array instanceof \Countable ? count($array) : null, $message);
    }

    public function run(): void
    {
        $methods = get_class_methods($this);
        $testMethods = array_filter($methods, function ($method) {
            return strpos($method, 'test') === 0;
        });

        $className = get_class($this);
        echo "\n=== {$className} ===\n";

        $this->setUp();

        foreach ($testMethods as $method) {
            $this->$method();
        }

        $this->tearDown();
    }

    protected function setUp(): void
    {
    }

    protected function tearDown(): void
    {
    }

    public function getResults(): array
    {
        return [
            'passed' => $this->passed,
            'failed' => $this->failed,
            'errors' => $this->errors
        ];
    }
}
