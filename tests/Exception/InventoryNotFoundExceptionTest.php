<?php

namespace Tourze\HotelContractBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\HotelContractBundle\Exception\InventoryNotFoundException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(InventoryNotFoundException::class)]
final class InventoryNotFoundExceptionTest extends AbstractExceptionTestCase
{
    public function testExceptionCanBeThrown(): void
    {
        $exception = new InventoryNotFoundException('Test message');

        // 验证异常实例的类型和消息内容
        $this->assertInstanceOf(\InvalidArgumentException::class, $exception);
        $this->assertSame('Test message', $exception->getMessage());
    }
}
