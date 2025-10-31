<?php

namespace Tourze\HotelContractBundle\Tests\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\HotelContractBundle\Entity\InventorySummary;
use Tourze\HotelContractBundle\Enum\InventorySummaryStatusEnum;
use Tourze\HotelProfileBundle\Entity\Hotel;
use Tourze\HotelProfileBundle\Entity\RoomType;
use Tourze\PHPUnitDoctrineEntity\AbstractEntityTestCase;

/**
 * @internal
 */
#[CoversClass(InventorySummary::class)]
final class InventorySummaryTest extends AbstractEntityTestCase
{
    protected function createEntity(): InventorySummary
    {
        return new InventorySummary();
    }

    public function testStatusUpdateSetsNormalWhenAvailableRoomsAbove10Percent(): void
    {
        $summary = $this->createEntity();
        $summary->setTotalRooms(100);
        $summary->setAvailableRooms(50);

        $this->assertSame(InventorySummaryStatusEnum::NORMAL, $summary->getStatus());
    }

    public function testStatusUpdateSetsWarningWhenAvailableRoomsAt10Percent(): void
    {
        $summary = $this->createEntity();
        $summary->setTotalRooms(100);
        $summary->setAvailableRooms(10);

        $this->assertSame(InventorySummaryStatusEnum::WARNING, $summary->getStatus());
    }

    public function testStatusUpdateSetsWarningWhenAvailableRoomsBelow10Percent(): void
    {
        $summary = $this->createEntity();
        $summary->setTotalRooms(100);
        $summary->setAvailableRooms(5);

        $this->assertSame(InventorySummaryStatusEnum::WARNING, $summary->getStatus());
    }

    public function testStatusUpdateSetsSoldOutWhenNoAvailableRooms(): void
    {
        $summary = $this->createEntity();
        $summary->setTotalRooms(100);
        $summary->setAvailableRooms(0);

        $this->assertSame(InventorySummaryStatusEnum::SOLD_OUT, $summary->getStatus());
    }

    public function testStatusUpdateSetsSoldOutWhenTotalRoomsIsZero(): void
    {
        $summary = $this->createEntity();
        $summary->setTotalRooms(0);
        $summary->setAvailableRooms(10);

        $this->assertSame(InventorySummaryStatusEnum::SOLD_OUT, $summary->getStatus());
    }

    public function testStatusUpdateSetsSoldOutWhenTotalRoomsIsNegative(): void
    {
        $summary = $this->createEntity();
        $summary->setTotalRooms(-5);
        $summary->setAvailableRooms(10);

        $this->assertSame(InventorySummaryStatusEnum::SOLD_OUT, $summary->getStatus());
    }

    public function testSetTotalRoomsUpdatesStatusWhenCalled(): void
    {
        $summary = $this->createEntity();
        $summary->setAvailableRooms(50);
        $summary->setTotalRooms(100);

        $this->assertSame(InventorySummaryStatusEnum::NORMAL, $summary->getStatus());
    }

    public function testSetAvailableRoomsUpdatesStatusWhenCalled(): void
    {
        $summary = $this->createEntity();
        $summary->setTotalRooms(100);
        $summary->setAvailableRooms(5);

        $this->assertSame(InventorySummaryStatusEnum::WARNING, $summary->getStatus());
    }

    public function testSetSoldRoomsUpdatesStatusWhenCalled(): void
    {
        $summary = $this->createEntity();
        $summary->setTotalRooms(100);
        $summary->setAvailableRooms(10);
        $summary->setSoldRooms(80);

        $this->assertSame(InventorySummaryStatusEnum::WARNING, $summary->getStatus());
    }

    public function testToStringReturnsCorrectFormatWithValidData(): void
    {
        $summary = $this->createEntity();

        $hotel = new Hotel();
        $hotel->setName('Grand Hotel');

        $roomType = new RoomType();
        $roomType->setName('Deluxe Room');

        $summary->setHotel($hotel);
        $summary->setRoomType($roomType);
        $summary->setDate(new \DateTimeImmutable('2024-01-01'));

        $expected = 'Grand Hotel - Deluxe Room - 2024-01-01';
        $this->assertSame($expected, $summary->__toString());
    }

    public function testToStringHandlesNullHotel(): void
    {
        $summary = $this->createEntity();

        $roomType = new RoomType();
        $roomType->setName('Deluxe Room');

        $summary->setRoomType($roomType);
        $summary->setDate(new \DateTimeImmutable('2024-01-01'));

        $expected = 'Unknown - Deluxe Room - 2024-01-01';
        $this->assertSame($expected, $summary->__toString());
    }

    public function testToStringHandlesNullRoomType(): void
    {
        $summary = $this->createEntity();

        $hotel = new Hotel();
        $hotel->setName('Grand Hotel');

        $summary->setHotel($hotel);
        $summary->setDate(new \DateTimeImmutable('2024-01-01'));

        $expected = 'Grand Hotel - Unknown - 2024-01-01';
        $this->assertSame($expected, $summary->__toString());
    }

    public function testToStringHandlesNullDate(): void
    {
        $summary = $this->createEntity();

        $hotel = new Hotel();
        $hotel->setName('Grand Hotel');

        $roomType = new RoomType();
        $roomType->setName('Deluxe Room');

        $summary->setHotel($hotel);
        $summary->setRoomType($roomType);

        $expected = 'Grand Hotel - Deluxe Room - Unknown Date';
        $this->assertSame($expected, $summary->__toString());
    }

    public function testToStringHandlesAllNullValues(): void
    {
        $summary = $this->createEntity();
        $expected = 'Unknown - Unknown - Unknown Date';
        $this->assertSame($expected, $summary->__toString());
    }

    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function propertiesProvider(): iterable
    {
        yield 'hotel' => ['hotel', null];
        yield 'roomType' => ['roomType', null];
        yield 'date' => ['date', new \DateTimeImmutable('2024-01-01')];
        yield 'totalRooms' => ['totalRooms', 100];
        yield 'availableRooms' => ['availableRooms', 50];
        yield 'reservedRooms' => ['reservedRooms', 20];
        yield 'soldRooms' => ['soldRooms', 30];
        yield 'pendingRooms' => ['pendingRooms', 10];
        yield 'status' => ['status', InventorySummaryStatusEnum::WARNING];
        yield 'lowestPrice' => ['lowestPrice', '99.99'];
        yield 'lowestContract' => ['lowestContract', null];
        yield 'createTime' => ['createTime', new \DateTimeImmutable('2024-01-01 10:00:00')];
        yield 'updateTime' => ['updateTime', new \DateTimeImmutable('2024-01-01 10:00:00')];
    }
}
