<?php

declare(strict_types=1);

namespace Tourze\HotelContractBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\HotelContractBundle\Exception\InvalidEntityException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(InvalidEntityException::class)]
final class InvalidEntityExceptionTest extends AbstractExceptionTestCase
{
    public function testExceptionCanBeThrown(): void
    {
        $this->expectException(InvalidEntityException::class);
        $this->expectExceptionMessage('Test message');

        throw new InvalidEntityException('Test message');
    }

    public function testExceptionExtendsLogicException(): void
    {
        $exception = new InvalidEntityException('Test');
        $this->assertInstanceOf(\LogicException::class, $exception);
    }
}
