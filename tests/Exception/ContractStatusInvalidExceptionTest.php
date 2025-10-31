<?php

namespace Tourze\HotelContractBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\HotelContractBundle\Exception\ContractStatusInvalidException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(ContractStatusInvalidException::class)]
final class ContractStatusInvalidExceptionTest extends AbstractExceptionTestCase
{
    public function testCanBeCreated(): void
    {
        $exception = new ContractStatusInvalidException();

        // 验证异常实例的类型和基本属性
        $this->assertInstanceOf(\InvalidArgumentException::class, $exception);
        $this->assertInstanceOf(\Throwable::class, $exception);
    }

    public function testCanBeCreatedWithMessage(): void
    {
        $message = 'Contract status is invalid';
        $exception = new ContractStatusInvalidException($message);
        $this->assertEquals($message, $exception->getMessage());
    }

    public function testCanBeCreatedWithMessageAndCode(): void
    {
        $message = 'Contract status is invalid';
        $code = 400;
        $exception = new ContractStatusInvalidException($message, $code);
        $this->assertEquals($message, $exception->getMessage());
        $this->assertEquals($code, $exception->getCode());
    }

    public function testCanBeCreatedWithPrevious(): void
    {
        $previous = new \Exception('Previous exception');
        $exception = new ContractStatusInvalidException('Contract status is invalid', 0, $previous);
        $this->assertSame($previous, $exception->getPrevious());
    }
}
