<?php

declare(strict_types=1);

namespace Tourze\HotelContractBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\HotelContractBundle\Exception\FileOperationException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(FileOperationException::class)]
final class FileOperationExceptionTest extends AbstractExceptionTestCase
{
    public function testExceptionCanBeThrown(): void
    {
        $this->expectException(FileOperationException::class);
        $this->expectExceptionMessage('File operation failed');

        throw new FileOperationException('File operation failed');
    }

    public function testExceptionExtendsRuntimeException(): void
    {
        $exception = new FileOperationException('Test');
        $this->assertInstanceOf(\RuntimeException::class, $exception);
    }
}
