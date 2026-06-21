<?php

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/WithholdingCalculatorTest.php';
require_once __DIR__ . '/StatusFlowTest.php';

use Tests\WithholdingCalculatorTest;
use Tests\StatusFlowTest;

echo "========================================\n";
echo "  电商订单库存后台 - 单元测试\n";
echo "========================================\n";

$tests = [
    new WithholdingCalculatorTest(),
    new StatusFlowTest(),
];

$totalPassed = 0;
$totalFailed = 0;
$allErrors = [];

foreach ($tests as $test) {
    $test->run();
    $results = $test->getResults();
    $totalPassed += $results['passed'];
    $totalFailed += $results['failed'];
    $allErrors = array_merge($allErrors, $results['errors']);
}

echo "\n========================================\n";
echo "  测试结果汇总\n";
echo "========================================\n";
echo "  通过: {$totalPassed}\n";
echo "  失败: {$totalFailed}\n";
echo "  总计: " . ($totalPassed + $totalFailed) . "\n";
echo "========================================\n";

if (!empty($allErrors)) {
    echo "\n失败详情:\n";
    foreach ($allErrors as $i => $error) {
        echo "  " . ($i + 1) . ". {$error}\n";
    }
    echo "\n";
    exit(1);
}

echo "\n🎉 所有测试通过！\n\n";
exit(0);
