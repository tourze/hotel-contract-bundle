<?php

namespace Tourze\HotelContractBundle\Tests\Enum;

use PHPUnit\Framework\TestCase;
use Tourze\HotelContractBundle\Enum\InventorySummaryStatusEnum;

class InventorySummaryStatusEnumTest extends TestCase
{
    public function test_enumCases_haveCorrectValues(): void
    {
        $this->assertSame('normal', InventorySummaryStatusEnum::NORMAL->value);
        $this->assertSame('warning', InventorySummaryStatusEnum::WARNING->value);
        $this->assertSame('sold_out', InventorySummaryStatusEnum::SOLD_OUT->value);
    }

    public function test_getLabel_returnsCorrectLabels(): void
    {
        $this->assertSame('正常', InventorySummaryStatusEnum::NORMAL->getLabel());
        $this->assertSame('预警', InventorySummaryStatusEnum::WARNING->getLabel());
        $this->assertSame('售罄', InventorySummaryStatusEnum::SOLD_OUT->getLabel());
    }

    public function test_implementsInterfaces(): void
    {
        $this->assertInstanceOf(\Tourze\EnumExtra\Labelable::class, InventorySummaryStatusEnum::NORMAL);
        $this->assertInstanceOf(\Tourze\EnumExtra\Itemable::class, InventorySummaryStatusEnum::NORMAL);
        $this->assertInstanceOf(\Tourze\EnumExtra\Selectable::class, InventorySummaryStatusEnum::NORMAL);
    }

    public function test_allCasesExist(): void
    {
        $cases = InventorySummaryStatusEnum::cases();
        
        $this->assertCount(3, $cases);
        $this->assertContains(InventorySummaryStatusEnum::NORMAL, $cases);
        $this->assertContains(InventorySummaryStatusEnum::WARNING, $cases);
        $this->assertContains(InventorySummaryStatusEnum::SOLD_OUT, $cases);
    }

    public function test_canCreateFromValue(): void
    {
        $this->assertSame(InventorySummaryStatusEnum::NORMAL, InventorySummaryStatusEnum::from('normal'));
        $this->assertSame(InventorySummaryStatusEnum::WARNING, InventorySummaryStatusEnum::from('warning'));
        $this->assertSame(InventorySummaryStatusEnum::SOLD_OUT, InventorySummaryStatusEnum::from('sold_out'));
    }

    public function test_from_throwsException_withInvalidValue(): void
    {
        $this->expectException(\ValueError::class);
        InventorySummaryStatusEnum::from('invalid');
    }

    public function test_tryFrom_returnsNull_withInvalidValue(): void
    {
        $this->assertNull(InventorySummaryStatusEnum::tryFrom('invalid'));
    }

    public function test_tryFrom_returnsEnum_withValidValue(): void
    {
        $this->assertSame(InventorySummaryStatusEnum::NORMAL, InventorySummaryStatusEnum::tryFrom('normal'));
        $this->assertSame(InventorySummaryStatusEnum::WARNING, InventorySummaryStatusEnum::tryFrom('warning'));
        $this->assertSame(InventorySummaryStatusEnum::SOLD_OUT, InventorySummaryStatusEnum::tryFrom('sold_out'));
    }
} 