<?php

namespace Tourze\HotelContractBundle\Tests\Unit\Exception;

use PHPUnit\Framework\TestCase;
use Tourze\HotelContractBundle\Exception\InsufficientInventoryException;

class InsufficientInventoryExceptionTest extends TestCase
{
    public function testCanBeCreated(): void
    {
        $exception = new InsufficientInventoryException();
        $this->assertInstanceOf(InsufficientInventoryException::class, $exception);
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