<?php

namespace Tourze\HotelContractBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\HotelContractBundle\Exception\InsufficientInventoryException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(InsufficientInventoryException::class)]
final class InsufficientInventoryExceptionTest extends AbstractExceptionTestCase
{
    public function testCanBeCreated(): void
    {
        $exception = new InsufficientInventoryException();

        // 验证异常实例的类型和基本属性
        $this->assertInstanceOf(\RuntimeException::class, $exception);
        $this->assertInstanceOf(\Throwable::class, $exception);
    }

    public function testCanBeCreatedWithMessage(): void
    {
        $message = 'Insufficient inventory available';
        $exception = new InsufficientInventoryException($message);
        $this->assertEquals($message, $exception->getMessage());
    }

    public function testCanBeCreatedWithMessageAndCode(): void
    {
        $message = 'Insufficient inventory available';
        $code = 409;
        $exception = new InsufficientInventoryException($message, $code);
        $this->assertEquals($message, $exception->getMessage());
        $this->assertEquals($code, $exception->getCode());
    }

    public function testCanBeCreatedWithPrevious(): void
    {
        $previous = new \Exception('Previous exception');
        $exception = new InsufficientInventoryException('Insufficient inventory', 0, $previous);
        $this->assertSame($previous, $exception->getPrevious());
    }
}
