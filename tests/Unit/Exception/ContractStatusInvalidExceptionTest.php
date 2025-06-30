<?php

namespace Tourze\HotelContractBundle\Tests\Unit\Exception;

use PHPUnit\Framework\TestCase;
use Tourze\HotelContractBundle\Exception\ContractStatusInvalidException;

class ContractStatusInvalidExceptionTest extends TestCase
{
    public function testCanBeCreated(): void
    {
        $exception = new ContractStatusInvalidException();
        $this->assertInstanceOf(ContractStatusInvalidException::class, $exception);
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