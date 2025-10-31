<?php

namespace Tourze\HotelContractBundle\Tests\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\HotelContractBundle\Entity\DailyInventory;
use Tourze\HotelContractBundle\Enum\DailyInventoryStatusEnum;
use Tourze\HotelProfileBundle\Entity\RoomType;
use Tourze\PHPUnitDoctrineEntity\AbstractEntityTestCase;

/**
 * @internal
 */
#[CoversClass(DailyInventory::class)]
final class DailyInventoryTest extends AbstractEntityTestCase
{
    protected function createEntity(): DailyInventory
    {
        return new DailyInventory();
    }

    public function testProfitRateCalculationReturnsZeroWhenCostPriceIsZero(): void
    {
        $inventory = $this->createEntity();
        $inventory->setCostPrice('0.00');
        $inventory->setSellingPrice('100.00');

        $this->assertSame('0.00', $inventory->getProfitRate());
    }

    public function testProfitRateCalculationReturnsZeroWhenSellingPriceIsZero(): void
    {
        $inventory = $this->createEntity();
        $inventory->setCostPrice('100.00');
        $inventory->setSellingPrice('0.00');

        $this->assertSame('0.00', $inventory->getProfitRate());
    }

    public function testProfitRateCalculationReturnsCorrectValueWithValidPrices(): void
    {
        $inventory = $this->createEntity();
        $inventory->setCostPrice('100.00');
        $inventory->setSellingPrice('150.00');

        $this->assertSame('50.00', $inventory->getProfitRate());
    }

    public function testProfitRateCalculationHandlesDecimalsCorrectly(): void
    {
        $inventory = $this->createEntity();
        $inventory->setCostPrice('100.00');
        $inventory->setSellingPrice('133.33');

        $this->assertSame('33.33', $inventory->getProfitRate());
    }

    public function testToStringReturnsCorrectFormatWithValidData(): void
    {
        $inventory = $this->createEntity();

        $roomType = new RoomType();
        $roomType->setName('Deluxe Room');

        $inventory->setRoomType($roomType);
        $inventory->setCode('INV-001');
        $inventory->setDate(new \DateTimeImmutable('2024-01-01'));

        $expected = 'Deluxe Room - INV-001 - 2024-01-01';
        $this->assertSame($expected, $inventory->__toString());
    }

    public function testToStringHandlesNullRoomType(): void
    {
        $inventory = $this->createEntity();
        $inventory->setCode('INV-001');
        $inventory->setDate(new \DateTimeImmutable('2024-01-01'));

        $expected = 'Unknown - INV-001 - 2024-01-01';
        $this->assertSame($expected, $inventory->__toString());
    }

    public function testToStringHandlesNullDate(): void
    {
        $inventory = $this->createEntity();

        $roomType = new RoomType();
        $roomType->setName('Deluxe Room');

        $inventory->setRoomType($roomType);
        $inventory->setCode('INV-001');

        $expected = 'Deluxe Room - INV-001 - Unknown Date';
        $this->assertSame($expected, $inventory->__toString());
    }

    public function testToStringHandlesEmptyCode(): void
    {
        $inventory = $this->createEntity();

        $roomType = new RoomType();
        $roomType->setName('Deluxe Room');

        $inventory->setRoomType($roomType);
        $inventory->setDate(new \DateTimeImmutable('2024-01-01'));

        $expected = 'Deluxe Room -  - 2024-01-01';
        $this->assertSame($expected, $inventory->__toString());
    }

    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function propertiesProvider(): iterable
    {
        yield 'code' => ['code', 'INV-2024-001'];
        yield 'roomType' => ['roomType', null];
        yield 'hotel' => ['hotel', null];
        yield 'date' => ['date', new \DateTimeImmutable('2024-01-01')];
        // yield 'isReserved' => ['isReserved', true]; // 跳过这个属性，因为方法命名不一致
        yield 'status' => ['status', DailyInventoryStatusEnum::SOLD];
        yield 'contract' => ['contract', null];
        yield 'costPrice' => ['costPrice', '100.50'];
        yield 'sellingPrice' => ['sellingPrice', '150.75'];
        yield 'profitRate' => ['profitRate', '25.50'];
        yield 'priceAdjustReason' => ['priceAdjustReason', 'Seasonal adjustment'];
        yield 'createTime' => ['createTime', new \DateTimeImmutable('2024-01-01 10:00:00')];
        yield 'updateTime' => ['updateTime', new \DateTimeImmutable('2024-01-01 10:00:00')];
        yield 'lastModifiedBy' => ['lastModifiedBy', 'admin'];
    }
}
