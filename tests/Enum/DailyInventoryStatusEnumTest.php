<?php

namespace Tourze\HotelContractBundle\Tests\Enum;

use PHPUnit\Framework\TestCase;
use Tourze\HotelContractBundle\Enum\DailyInventoryStatusEnum;

class DailyInventoryStatusEnumTest extends TestCase
{
    public function test_enumCases_haveCorrectValues(): void
    {
        $this->assertSame('available', DailyInventoryStatusEnum::AVAILABLE->value);
        $this->assertSame('sold', DailyInventoryStatusEnum::SOLD->value);
        $this->assertSame('pending', DailyInventoryStatusEnum::PENDING->value);
        $this->assertSame('reserved', DailyInventoryStatusEnum::RESERVED->value);
        $this->assertSame('disabled', DailyInventoryStatusEnum::DISABLED->value);
        $this->assertSame('cancelled', DailyInventoryStatusEnum::CANCELLED->value);
        $this->assertSame('refunded', DailyInventoryStatusEnum::REFUNDED->value);
    }

    public function test_getLabel_returnsCorrectLabels(): void
    {
        $this->assertSame('可售', DailyInventoryStatusEnum::AVAILABLE->getLabel());
        $this->assertSame('已售', DailyInventoryStatusEnum::SOLD->getLabel());
        $this->assertSame('待确认', DailyInventoryStatusEnum::PENDING->getLabel());
        $this->assertSame('预留', DailyInventoryStatusEnum::RESERVED->getLabel());
        $this->assertSame('禁用', DailyInventoryStatusEnum::DISABLED->getLabel());
        $this->assertSame('已取消', DailyInventoryStatusEnum::CANCELLED->getLabel());
        $this->assertSame('已退款', DailyInventoryStatusEnum::REFUNDED->getLabel());
    }

    public function test_implementsInterfaces(): void
    {
        $this->assertInstanceOf(\Tourze\EnumExtra\Labelable::class, DailyInventoryStatusEnum::AVAILABLE);
        $this->assertInstanceOf(\Tourze\EnumExtra\Itemable::class, DailyInventoryStatusEnum::AVAILABLE);
        $this->assertInstanceOf(\Tourze\EnumExtra\Selectable::class, DailyInventoryStatusEnum::AVAILABLE);
    }

    public function test_allCasesExist(): void
    {
        $cases = DailyInventoryStatusEnum::cases();
        
        $this->assertCount(7, $cases);
        $this->assertContains(DailyInventoryStatusEnum::AVAILABLE, $cases);
        $this->assertContains(DailyInventoryStatusEnum::SOLD, $cases);
        $this->assertContains(DailyInventoryStatusEnum::PENDING, $cases);
        $this->assertContains(DailyInventoryStatusEnum::RESERVED, $cases);
        $this->assertContains(DailyInventoryStatusEnum::DISABLED, $cases);
        $this->assertContains(DailyInventoryStatusEnum::CANCELLED, $cases);
        $this->assertContains(DailyInventoryStatusEnum::REFUNDED, $cases);
    }

    public function test_canCreateFromValue(): void
    {
        $this->assertSame(DailyInventoryStatusEnum::AVAILABLE, DailyInventoryStatusEnum::from('available'));
        $this->assertSame(DailyInventoryStatusEnum::SOLD, DailyInventoryStatusEnum::from('sold'));
        $this->assertSame(DailyInventoryStatusEnum::PENDING, DailyInventoryStatusEnum::from('pending'));
        $this->assertSame(DailyInventoryStatusEnum::RESERVED, DailyInventoryStatusEnum::from('reserved'));
        $this->assertSame(DailyInventoryStatusEnum::DISABLED, DailyInventoryStatusEnum::from('disabled'));
        $this->assertSame(DailyInventoryStatusEnum::CANCELLED, DailyInventoryStatusEnum::from('cancelled'));
        $this->assertSame(DailyInventoryStatusEnum::REFUNDED, DailyInventoryStatusEnum::from('refunded'));
    }

    public function test_from_throwsException_withInvalidValue(): void
    {
        $this->expectException(\ValueError::class);
        DailyInventoryStatusEnum::from('invalid');
    }

    public function test_tryFrom_returnsNull_withInvalidValue(): void
    {
        $this->assertNull(DailyInventoryStatusEnum::tryFrom('invalid'));
    }

    public function test_tryFrom_returnsEnum_withValidValue(): void
    {
        $this->assertSame(DailyInventoryStatusEnum::AVAILABLE, DailyInventoryStatusEnum::tryFrom('available'));
        $this->assertSame(DailyInventoryStatusEnum::SOLD, DailyInventoryStatusEnum::tryFrom('sold'));
    }
} 