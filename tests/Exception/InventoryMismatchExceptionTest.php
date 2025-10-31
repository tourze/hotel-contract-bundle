<?php

namespace Tourze\HotelContractBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\HotelContractBundle\Exception\InventoryMismatchException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(InventoryMismatchException::class)]
final class InventoryMismatchExceptionTest extends AbstractExceptionTestCase
{
    public function testExceptionCanBeThrown(): void
    {
        $exception = new InventoryMismatchException('Test message');

        // 验证异常实例的类型和消息内容
        $this->assertInstanceOf(\InvalidArgumentException::class, $exception);
        $this->assertSame('Test message', $exception->getMessage());
    }
}
