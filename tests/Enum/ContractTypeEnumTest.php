<?php

namespace Tourze\HotelContractBundle\Tests\Enum;

use PHPUnit\Framework\TestCase;
use Tourze\HotelContractBundle\Enum\ContractTypeEnum;

class ContractTypeEnumTest extends TestCase
{
    public function test_enumCases_haveCorrectValues(): void
    {
        $this->assertSame('fixed_price', ContractTypeEnum::FIXED_PRICE->value);
        $this->assertSame('dynamic_price', ContractTypeEnum::DYNAMIC_PRICE->value);
    }

    public function test_getLabel_returnsCorrectLabels(): void
    {
        $this->assertSame('固定总价', ContractTypeEnum::FIXED_PRICE->getLabel());
        $this->assertSame('动态打包价', ContractTypeEnum::DYNAMIC_PRICE->getLabel());
    }

    public function test_implementsInterfaces(): void
    {
        $this->assertInstanceOf(\Tourze\EnumExtra\Labelable::class, ContractTypeEnum::FIXED_PRICE);
        $this->assertInstanceOf(\Tourze\EnumExtra\Itemable::class, ContractTypeEnum::FIXED_PRICE);
        $this->assertInstanceOf(\Tourze\EnumExtra\Selectable::class, ContractTypeEnum::FIXED_PRICE);
    }

    public function test_allCasesExist(): void
    {
        $cases = ContractTypeEnum::cases();
        
        $this->assertCount(2, $cases);
        $this->assertContains(ContractTypeEnum::FIXED_PRICE, $cases);
        $this->assertContains(ContractTypeEnum::DYNAMIC_PRICE, $cases);
    }

    public function test_canCreateFromValue(): void
    {
        $this->assertSame(ContractTypeEnum::FIXED_PRICE, ContractTypeEnum::from('fixed_price'));
        $this->assertSame(ContractTypeEnum::DYNAMIC_PRICE, ContractTypeEnum::from('dynamic_price'));
    }

    public function test_from_throwsException_withInvalidValue(): void
    {
        $this->expectException(\ValueError::class);
        ContractTypeEnum::from('invalid');
    }

    public function test_tryFrom_returnsNull_withInvalidValue(): void
    {
        $this->assertNull(ContractTypeEnum::tryFrom('invalid'));
    }

    public function test_tryFrom_returnsEnum_withValidValue(): void
    {
        $this->assertSame(ContractTypeEnum::FIXED_PRICE, ContractTypeEnum::tryFrom('fixed_price'));
        $this->assertSame(ContractTypeEnum::DYNAMIC_PRICE, ContractTypeEnum::tryFrom('dynamic_price'));
    }
} 