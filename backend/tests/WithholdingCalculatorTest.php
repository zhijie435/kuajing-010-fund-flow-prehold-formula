<?php

namespace Tests;

use Tests\TestCase;
use App\Services\WithholdingCalculator;
use App\Exceptions\FormulaException;

class WithholdingCalculatorTest extends TestCase
{
    private $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new WithholdingCalculator();
    }

    public function testOrderAmountRateFormula()
    {
        $result = $this->calculator->calculate('ORDER_AMOUNT_RATE', [
            'order_amount' => 1000,
            'rate' => 0.05
        ], ['record' => false]);

        $this->assertEqual(50.0, $result['result'], '订单金额1000，比例0.05，预扣50元');
        $this->assertEqual('ORDER_AMOUNT_RATE', $result['formula_code'], '返回正确的公式编码');
        $this->assertArrayHasKey('variables', $result, '返回值包含变量');
    }

    public function testOrderAmountRateFormulaDefaultRate()
    {
        $result = $this->calculator->calculate('ORDER_AMOUNT_RATE', [
            'order_amount' => 2000
        ], ['record' => false]);

        $this->assertEqual(100.0, $result['result'], '默认比例0.05，2000 * 0.05 = 100');
    }

    public function testStepWithholdingLow()
    {
        $result = $this->calculator->calculate('STEP_WITHHOLDING', [
            'order_amount' => 500
        ], ['record' => false]);

        $this->assertEqual(15.0, $result['result'], '500元以下按3%计算: 500 * 0.03 = 15');
    }

    public function testStepWithholdingMedium()
    {
        $result = $this->calculator->calculate('STEP_WITHHOLDING', [
            'order_amount' => 2000
        ], ['record' => false]);

        $this->assertEqual(100.0, $result['result'], '1000-5000元按5%计算: 2000 * 0.05 = 100');
    }

    public function testStepWithholdingHigh()
    {
        $result = $this->calculator->calculate('STEP_WITHHOLDING', [
            'order_amount' => 10000
        ], ['record' => false]);

        $this->assertEqual(800.0, $result['result'], '5000元以上按8%计算: 10000 * 0.08 = 800');
    }

    public function testStepWithholdingBoundaryLow()
    {
        $result = $this->calculator->calculate('STEP_WITHHOLDING', [
            'order_amount' => 1000
        ], ['record' => false]);

        $this->assertEqual(30.0, $result['result'], '边界值1000按3%计算: 1000 * 0.03 = 30');
    }

    public function testStepWithholdingBoundaryHigh()
    {
        $result = $this->calculator->calculate('STEP_WITHHOLDING', [
            'order_amount' => 5000
        ], ['record' => false]);

        $this->assertEqual(250.0, $result['result'], '边界值5000按5%计算: 5000 * 0.05 = 250');
    }

    public function testFixedPlusRateFormula()
    {
        $result = $this->calculator->calculate('FIXED_PLUS_RATE', [
            'fixed_fee' => 10,
            'order_amount' => 500,
            'rate' => 0.02
        ], ['record' => false]);

        $this->assertEqual(20.0, $result['result'], '固定10 + 500*0.02 = 20');
    }

    public function testFixedPlusRateDefaultValues()
    {
        $result = $this->calculator->calculate('FIXED_PLUS_RATE', [
            'order_amount' => 1000
        ], ['record' => false]);

        $this->assertEqual(30.0, $result['result'], '默认固定10 + 1000*默认0.02 = 30');
    }

    public function testInventoryOccupyFormula()
    {
        $result = $this->calculator->calculate('INVENTORY_OCCUPY', [
            'quantity' => 100,
            'unit_price' => 50,
            'occupy_rate' => 0.1,
            'storage_fee' => 5
        ], ['record' => false]);

        $this->assertEqual(505.0, $result['result'], '100*50*0.1 + 5 = 505');
    }

    public function testInventoryOccupyDefaultValues()
    {
        $result = $this->calculator->calculate('INVENTORY_OCCUPY', [
            'quantity' => 10,
            'unit_price' => 100
        ], ['record' => false]);

        $this->assertEqual(105.0, $result['result'], '10*100*默认0.1 + 默认5 = 105');
    }

    public function testZeroAmountCalculation()
    {
        $result = $this->calculator->calculate('ORDER_AMOUNT_RATE', [
            'order_amount' => 0,
            'rate' => 0.1
        ], ['record' => false]);

        $this->assertEqual(0.0, $result['result'], '零金额计算结果为0');
    }

    public function testDecimalPrecisionRounding()
    {
        $result = $this->calculator->calculate('ORDER_AMOUNT_RATE', [
            'order_amount' => 100.333,
            'rate' => 0.03
        ], ['record' => false]);

        $this->assertEqual(3.01, $result['result'], '结果四舍五入保留两位小数');
    }

    public function testFormulaNotFoundThrowsException()
    {
        $this->assertException(function () {
            $this->calculator->calculate('NON_EXISTENT_FORMULA', [], ['record' => false]);
        }, FormulaException::class, '不存在的公式编码抛出异常');
    }

    public function testInactiveFormulaThrowsException()
    {
        $this->assertException(function () {
            $this->calculator->calculate('DISABLED_FORMULA', [
                'order_amount' => 100
            ], ['record' => false]);
        }, FormulaException::class, '禁用公式抛出异常');
    }

    public function testInvalidVariableThrowsException()
    {
        $this->assertException(function () {
            $this->calculator->calculate('ORDER_AMOUNT_RATE', [
                'order_amount' => 'not_a_number',
                'rate' => 0.05
            ], ['record' => false]);
        }, FormulaException::class, '非数字变量抛出异常');
    }

    public function testNegativeResultThrowsException()
    {
        $formulaId = $this->formulaModel->create([
            'name' => '负数结果公式',
            'code' => 'NEGATIVE_RESULT',
            'formula' => 'order_amount - 1000',
            'description' => '测试负数结果',
            'variables' => json_encode([
                ['name' => 'order_amount', 'label' => '订单金额', 'type' => 'number', 'default' => 0]
            ]),
            'status' => 1
        ]);

        $this->assertException(function () {
            $this->calculator->calculate('NEGATIVE_RESULT', [
                'order_amount' => 100
            ], ['record' => false]);
        }, FormulaException::class, '负数结果抛出异常');
    }

    public function testUnsafeFormulaThrowsException()
    {
        $formulaId = $this->formulaModel->create([
            'name' => '不安全公式',
            'code' => 'UNSAFE_FORMULA',
            'formula' => 'exec("ls")',
            'description' => '测试不安全公式',
            'variables' => json_encode([]),
            'status' => 1
        ]);

        $this->assertException(function () {
            $this->calculator->calculate('UNSAFE_FORMULA', [], ['record' => false]);
        }, FormulaException::class, '不安全公式抛出异常');
    }

    public function testPreviewDoesNotRecord()
    {
        $beforeCount = count($this->detailModel->all());
        $beforeFlowCount = count($this->fundFlowModel->all());

        $result = $this->calculator->preview('ORDER_AMOUNT_RATE', [
            'order_amount' => 1000,
            'rate' => 0.05
        ]);

        $afterCount = count($this->detailModel->all());
        $afterFlowCount = count($this->fundFlowModel->all());

        $this->assertEqual(50.0, $result['result'], '预览模式计算正确');
        $this->assertEqual($beforeCount, $afterCount, '预览模式不创建预扣明细记录');
        $this->assertEqual($beforeFlowCount, $afterFlowCount, '预览模式不创建资金流水记录');
    }

    public function testCalculateWithRecordCreatesDetailAndFlow()
    {
        $beforeCount = count($this->detailModel->all());
        $beforeFlowCount = count($this->fundFlowModel->all());

        $result = $this->calculator->calculate('ORDER_AMOUNT_RATE', [
            'order_amount' => 1000,
            'rate' => 0.05
        ], [
            'record' => true,
            'order_no' => 'TEST20240101001',
            'operator' => 'tester'
        ]);

        $afterCount = count($this->detailModel->all());
        $afterFlowCount = count($this->fundFlowModel->all());

        $this->assertNotNull($result['detail_id'], '返回预扣明细ID');
        $this->assertEqual($beforeCount + 1, $afterCount, '创建了1条预扣明细');
        $this->assertEqual($beforeFlowCount + 1, $afterFlowCount, '创建了1条资金流水');

        $detail = $this->detailModel->find($result['detail_id']);
        $this->assertNotNull($detail, '预扣明细存在');
        $this->assertEqual(50.0, (float)$detail['result'], '预扣明细金额正确');
        $this->assertEqual('TEST20240101001', $detail['order_no'], '预扣明细订单号正确');
        $this->assertEqual('tester', $detail['operator'], '预扣明细操作人正确');
        $this->assertEqual(\App\Models\WithholdingDetail::STATUS_COMPLETED, (int)$detail['status'], '预扣明细默认状态为已完成');

        $flows = $this->fundFlowModel->findByWithholdingDetailId($result['detail_id']);
        $this->assertEqual(1, count($flows), '关联了1条资金流水');
        $flow = $flows[0];
        $this->assertEqual(\App\Models\FundFlow::TYPE_WITHHOLDING, $flow['flow_type'], '流水类型为预扣');
        $this->assertEqual(\App\Models\FundFlow::DIRECTION_OUT, (int)$flow['direction'], '资金方向为流出');
        $this->assertEqual(50.0, (float)$flow['amount'], '流水金额正确');
    }

    public function testValidateFormulaValid()
    {
        $result = $this->calculator->validateFormula('order_amount * rate', [
            ['name' => 'order_amount', 'label' => '订单金额', 'default' => 100],
            ['name' => 'rate', 'label' => '比例', 'default' => 0.05]
        ]);

        $this->assertTrue($result['valid'], '有效公式验证通过');
        $this->assertCount(0, $result['errors'], '无错误信息');
    }

    public function testValidateFormulaEmpty()
    {
        $result = $this->calculator->validateFormula('', []);
        $this->assertFalse($result['valid'], '空公式验证不通过');
    }

    public function testValidateFormulaUnsafe()
    {
        $result = $this->calculator->validateFormula('exec("ls")', []);
        $this->assertFalse($result['valid'], '不安全公式验证不通过');
    }

    public function testValidateFormulaMissingVariables()
    {
        $result = $this->calculator->validateFormula('order_amount * rate', [
            ['name' => 'order_amount', 'label' => '订单金额', 'default' => 100]
        ]);

        $this->assertFalse($result['valid'], '缺失变量的公式验证不通过');
    }

    public function testValidateFormulaNegativeResult()
    {
        $result = $this->calculator->validateFormula('order_amount - 1000', [
            ['name' => 'order_amount', 'label' => '订单金额', 'default' => 100]
        ]);

        $this->assertFalse($result['valid'], '产生负数结果的公式验证不通过');
    }

    public function testCalculateWithPendingInitialStatus()
    {
        $result = $this->calculator->calculate('ORDER_AMOUNT_RATE', [
            'order_amount' => 1000,
            'rate' => 0.05
        ], [
            'record' => true,
            'initial_status' => \App\Models\WithholdingDetail::STATUS_PENDING
        ]);

        $detail = $this->detailModel->find($result['detail_id']);
        $this->assertEqual(\App\Models\WithholdingDetail::STATUS_PENDING, (int)$detail['status'], '初始状态为待处理');

        $flows = $this->fundFlowModel->findByWithholdingDetailId($result['detail_id']);
        $this->assertEqual(1, count($flows), '关联了1条资金流水');
        $this->assertEqual(\App\Models\FundFlow::STATUS_PENDING, (int)$flows[0]['status'], '流水初始状态为待处理');
    }

    public function testResultRoundedToTwoDecimals()
    {
        $result = $this->calculator->calculate('ORDER_AMOUNT_RATE', [
            'order_amount' => 99.999,
            'rate' => 0.1
        ], ['record' => false]);

        $this->assertEqual(10.0, $result['result'], '结果四舍五入到2位小数: 99.999 * 0.1 = 10.00');
    }

    public function testComplexArithmeticExpression()
    {
        $formulaId = $this->formulaModel->create([
            'name' => '复杂计算',
            'code' => 'COMPLEX_FORMULA',
            'formula' => '(base_amount + extra_fee) * rate - discount',
            'description' => '复杂表达式测试',
            'variables' => json_encode([
                ['name' => 'base_amount', 'label' => '基础金额', 'type' => 'number', 'default' => 0],
                ['name' => 'extra_fee', 'label' => '额外费用', 'type' => 'number', 'default' => 0],
                ['name' => 'rate', 'label' => '比例', 'type' => 'number', 'default' => 1],
                ['name' => 'discount', 'label' => '折扣', 'type' => 'number', 'default' => 0]
            ]),
            'status' => 1
        ]);

        $result = $this->calculator->calculate('COMPLEX_FORMULA', [
            'base_amount' => 100,
            'extra_fee' => 20,
            'rate' => 0.5,
            'discount' => 10
        ], ['record' => false]);

        $this->assertEqual(50.0, $result['result'], '(100+20)*0.5-10 = 50');
    }
}
