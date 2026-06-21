<?php

namespace App\Exceptions;

class FormulaException extends \Exception
{
    const CODE_NOT_FOUND = 1001;
    const CODE_INACTIVE = 1002;
    const CODE_INVALID_VARIABLE = 1003;
    const CODE_EVALUATION_ERROR = 1004;
    const CODE_UNSAFE = 1005;
    const CODE_NEGATIVE_RESULT = 1006;
    const CODE_RECORD_FAILED = 1007;
    const CODE_MISSING_VARIABLES = 1008;

    private $errorCode;

    public function __construct(string $message, int $errorCode = 0, \Throwable $previous = null)
    {
        $this->errorCode = $errorCode;
        parent::__construct($message, $errorCode, $previous);
    }

    public static function notFound(string $code): self
    {
        return new self("公式编码 '{$code}' 不存在", self::CODE_NOT_FOUND);
    }

    public static function inactive(string $code): self
    {
        return new self("公式 '{$code}' 未启用", self::CODE_INACTIVE);
    }

    public static function invalidVariable(string $name): self
    {
        return new self("变量 '{$name}' 必须为数值", self::CODE_INVALID_VARIABLE);
    }

    public static function evaluationError(string $detail): self
    {
        return new self("公式计算错误: {$detail}", self::CODE_EVALUATION_ERROR);
    }

    public static function unsafe(): self
    {
        return new self("公式包含不安全代码", self::CODE_UNSAFE);
    }

    public static function negativeResult(float $result): self
    {
        return new self("预扣金额不能为负数，计算结果: {$result}", self::CODE_NEGATIVE_RESULT);
    }

    public static function recordFailed(string $detail): self
    {
        return new self("记录创建失败: {$detail}", self::CODE_RECORD_FAILED);
    }

    public static function missingVariables(array $names): self
    {
        return new self("缺少必填变量: " . implode(', ', $names), self::CODE_MISSING_VARIABLES);
    }

    public function getErrorCode(): int
    {
        return $this->errorCode;
    }
}
