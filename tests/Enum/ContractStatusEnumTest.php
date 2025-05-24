<?php

namespace Tourze\HotelContractBundle\Tests\Enum;

use PHPUnit\Framework\TestCase;
use Tourze\HotelContractBundle\Enum\ContractStatusEnum;

class ContractStatusEnumTest extends TestCase
{
    public function test_enumCases_haveCorrectValues(): void
    {
        $this->assertSame('pending', ContractStatusEnum::PENDING->value);
        $this->assertSame('active', ContractStatusEnum::ACTIVE->value);
        $this->assertSame('terminated', ContractStatusEnum::TERMINATED->value);
    }

    public function test_getLabel_returnsCorrectLabels(): void
    {
        $this->assertSame('待确认', ContractStatusEnum::PENDING->getLabel());
        $this->assertSame('生效', ContractStatusEnum::ACTIVE->getLabel());
        $this->assertSame('终止', ContractStatusEnum::TERMINATED->getLabel());
    }

    public function test_implementsInterfaces(): void
    {
        $this->assertInstanceOf(\Tourze\EnumExtra\Labelable::class, ContractStatusEnum::PENDING);
        $this->assertInstanceOf(\Tourze\EnumExtra\Itemable::class, ContractStatusEnum::PENDING);
        $this->assertInstanceOf(\Tourze\EnumExtra\Selectable::class, ContractStatusEnum::PENDING);
    }

    public function test_allCasesExist(): void
    {
        $cases = ContractStatusEnum::cases();
        
        $this->assertCount(3, $cases);
        $this->assertContains(ContractStatusEnum::PENDING, $cases);
        $this->assertContains(ContractStatusEnum::ACTIVE, $cases);
        $this->assertContains(ContractStatusEnum::TERMINATED, $cases);
    }

    public function test_canCreateFromValue(): void
    {
        $this->assertSame(ContractStatusEnum::PENDING, ContractStatusEnum::from('pending'));
        $this->assertSame(ContractStatusEnum::ACTIVE, ContractStatusEnum::from('active'));
        $this->assertSame(ContractStatusEnum::TERMINATED, ContractStatusEnum::from('terminated'));
    }

    public function test_from_throwsException_withInvalidValue(): void
    {
        $this->expectException(\ValueError::class);
        ContractStatusEnum::from('invalid');
    }

    public function test_tryFrom_returnsNull_withInvalidValue(): void
    {
        $this->assertNull(ContractStatusEnum::tryFrom('invalid'));
    }

    public function test_tryFrom_returnsEnum_withValidValue(): void
    {
        $this->assertSame(ContractStatusEnum::PENDING, ContractStatusEnum::tryFrom('pending'));
        $this->assertSame(ContractStatusEnum::ACTIVE, ContractStatusEnum::tryFrom('active'));
        $this->assertSame(ContractStatusEnum::TERMINATED, ContractStatusEnum::tryFrom('terminated'));
    }
} 