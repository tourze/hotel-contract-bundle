<?php

namespace Tourze\HotelContractBundle\Tests\Entity;

use PHPUnit\Framework\TestCase;
use Tourze\HotelContractBundle\Entity\HotelContract;
use Tourze\HotelContractBundle\Entity\InventorySummary;
use Tourze\HotelContractBundle\Enum\InventorySummaryStatusEnum;
use Tourze\HotelProfileBundle\Entity\Hotel;
use Tourze\HotelProfileBundle\Entity\RoomType;

class InventorySummaryTest extends TestCase
{
    private InventorySummary $summary;

    protected function setUp(): void
    {
        $this->summary = new InventorySummary();
    }

    public function test_getId_returnsNull_whenNotPersisted(): void
    {
        $this->assertNull($this->summary->getId());
    }

    public function test_setHotel_returnsInstance_withValidHotel(): void
    {
        $hotel = $this->createMock(Hotel::class);
        $result = $this->summary->setHotel($hotel);
        
        $this->assertSame($this->summary, $result);
        $this->assertSame($hotel, $this->summary->getHotel());
    }

    public function test_setHotel_returnsInstance_withNullValue(): void
    {
        $result = $this->summary->setHotel(null);
        
        $this->assertSame($this->summary, $result);
        $this->assertNull($this->summary->getHotel());
    }

    public function test_getHotel_returnsNull_whenNotSet(): void
    {
        $this->assertNull($this->summary->getHotel());
    }

    public function test_setRoomType_returnsInstance_withValidRoomType(): void
    {
        $roomType = $this->createMock(RoomType::class);
        $result = $this->summary->setRoomType($roomType);
        
        $this->assertSame($this->summary, $result);
        $this->assertSame($roomType, $this->summary->getRoomType());
    }

    public function test_setRoomType_returnsInstance_withNullValue(): void
    {
        $result = $this->summary->setRoomType(null);
        
        $this->assertSame($this->summary, $result);
        $this->assertNull($this->summary->getRoomType());
    }

    public function test_getRoomType_returnsNull_whenNotSet(): void
    {
        $this->assertNull($this->summary->getRoomType());
    }

    public function test_setDate_returnsInstance_withValidDate(): void
    {
        $date = new \DateTime('2024-01-01');
        $result = $this->summary->setDate($date);
        
        $this->assertSame($this->summary, $result);
        $this->assertSame($date, $this->summary->getDate());
    }

    public function test_setDate_returnsInstance_withNullValue(): void
    {
        $result = $this->summary->setDate(null);
        
        $this->assertSame($this->summary, $result);
        $this->assertNull($this->summary->getDate());
    }

    public function test_getDate_returnsNull_whenNotSet(): void
    {
        $this->assertNull($this->summary->getDate());
    }

    public function test_setTotalRooms_returnsInstance_withValidValue(): void
    {
        $totalRooms = 100;
        $result = $this->summary->setTotalRooms($totalRooms);
        
        $this->assertSame($this->summary, $result);
        $this->assertSame($totalRooms, $this->summary->getTotalRooms());
    }

    public function test_setTotalRooms_updatesStatus_whenCalled(): void
    {
        $this->summary->setAvailableRooms(50);
        $this->summary->setTotalRooms(100);

        $this->assertSame(InventorySummaryStatusEnum::NORMAL, $this->summary->getStatus());
    }

    public function test_getTotalRooms_returnsZero_whenNotSet(): void
    {
        $this->assertSame(0, $this->summary->getTotalRooms());
    }

    public function test_setAvailableRooms_returnsInstance_withValidValue(): void
    {
        $availableRooms = 50;
        $result = $this->summary->setAvailableRooms($availableRooms);
        
        $this->assertSame($this->summary, $result);
        $this->assertSame($availableRooms, $this->summary->getAvailableRooms());
    }

    public function test_setAvailableRooms_updatesStatus_whenCalled(): void
    {
        $this->summary->setTotalRooms(100);
        $this->summary->setAvailableRooms(5);
        
        $this->assertSame(InventorySummaryStatusEnum::WARNING, $this->summary->getStatus());
    }

    public function test_getAvailableRooms_returnsZero_whenNotSet(): void
    {
        $this->assertSame(0, $this->summary->getAvailableRooms());
    }

    public function test_setReservedRooms_returnsInstance_withValidValue(): void
    {
        $reservedRooms = 20;
        $result = $this->summary->setReservedRooms($reservedRooms);
        
        $this->assertSame($this->summary, $result);
        $this->assertSame($reservedRooms, $this->summary->getReservedRooms());
    }

    public function test_getReservedRooms_returnsZero_whenNotSet(): void
    {
        $this->assertSame(0, $this->summary->getReservedRooms());
    }

    public function test_setSoldRooms_returnsInstance_withValidValue(): void
    {
        $soldRooms = 30;
        $result = $this->summary->setSoldRooms($soldRooms);
        
        $this->assertSame($this->summary, $result);
        $this->assertSame($soldRooms, $this->summary->getSoldRooms());
    }

    public function test_setSoldRooms_updatesStatus_whenCalled(): void
    {
        $this->summary->setTotalRooms(100);
        $this->summary->setAvailableRooms(10);
        $this->summary->setSoldRooms(80);
        
        $this->assertSame(InventorySummaryStatusEnum::WARNING, $this->summary->getStatus());
    }

    public function test_getSoldRooms_returnsZero_whenNotSet(): void
    {
        $this->assertSame(0, $this->summary->getSoldRooms());
    }

    public function test_setPendingRooms_returnsInstance_withValidValue(): void
    {
        $pendingRooms = 10;
        $result = $this->summary->setPendingRooms($pendingRooms);
        
        $this->assertSame($this->summary, $result);
        $this->assertSame($pendingRooms, $this->summary->getPendingRooms());
    }

    public function test_getPendingRooms_returnsZero_whenNotSet(): void
    {
        $this->assertSame(0, $this->summary->getPendingRooms());
    }

    public function test_setStatus_returnsInstance_withValidStatus(): void
    {
        $status = InventorySummaryStatusEnum::WARNING;
        $result = $this->summary->setStatus($status);
        
        $this->assertSame($this->summary, $result);
        $this->assertSame($status, $this->summary->getStatus());
    }

    public function test_getStatus_returnsDefaultValue_whenNotSet(): void
    {
        $this->assertSame(InventorySummaryStatusEnum::NORMAL, $this->summary->getStatus());
    }

    public function test_setLowestPrice_returnsInstance_withValidPrice(): void
    {
        $price = '99.99';
        $result = $this->summary->setLowestPrice($price);
        
        $this->assertSame($this->summary, $result);
        $this->assertSame($price, $this->summary->getLowestPrice());
    }

    public function test_setLowestPrice_returnsInstance_withNullValue(): void
    {
        $result = $this->summary->setLowestPrice(null);
        
        $this->assertSame($this->summary, $result);
        $this->assertNull($this->summary->getLowestPrice());
    }

    public function test_getLowestPrice_returnsNull_whenNotSet(): void
    {
        $this->assertNull($this->summary->getLowestPrice());
    }

    public function test_setLowestContract_returnsInstance_withValidContract(): void
    {
        $contract = $this->createMock(HotelContract::class);
        $result = $this->summary->setLowestContract($contract);
        
        $this->assertSame($this->summary, $result);
        $this->assertSame($contract, $this->summary->getLowestContract());
    }

    public function test_setLowestContract_returnsInstance_withNullValue(): void
    {
        $result = $this->summary->setLowestContract(null);
        
        $this->assertSame($this->summary, $result);
        $this->assertNull($this->summary->getLowestContract());
    }

    public function test_getLowestContract_returnsNull_whenNotSet(): void
    {
        $this->assertNull($this->summary->getLowestContract());
    }

    public function test_getCreateTime_returnsNull_whenNotSet(): void
    {
        $this->assertNull($this->summary->getCreateTime());
    }

    public function test_getUpdateTime_returnsNull_whenNotSet(): void
    {
        $this->assertNull($this->summary->getUpdateTime());
    }

    public function test_setCreateTime_setsValue_withValidDateTime(): void
    {
        $dateTime = new \DateTime('2024-01-01 10:00:00');
        $this->summary->setCreateTime($dateTime);
        
        $this->assertSame($dateTime, $this->summary->getCreateTime());
    }

    public function test_setUpdateTime_setsValue_withValidDateTime(): void
    {
        $dateTime = new \DateTime('2024-01-01 10:00:00');
        $this->summary->setUpdateTime($dateTime);
        
        $this->assertSame($dateTime, $this->summary->getUpdateTime());
    }

    public function test_statusUpdate_setsNormal_whenAvailableRoomsAbove10Percent(): void
    {
        $this->summary->setTotalRooms(100);
        $this->summary->setAvailableRooms(50);
        
        $this->assertSame(InventorySummaryStatusEnum::NORMAL, $this->summary->getStatus());
    }

    public function test_statusUpdate_setsWarning_whenAvailableRoomsAt10Percent(): void
    {
        $this->summary->setTotalRooms(100);
        $this->summary->setAvailableRooms(10);
        
        $this->assertSame(InventorySummaryStatusEnum::WARNING, $this->summary->getStatus());
    }

    public function test_statusUpdate_setsWarning_whenAvailableRoomsBelow10Percent(): void
    {
        $this->summary->setTotalRooms(100);
        $this->summary->setAvailableRooms(5);
        
        $this->assertSame(InventorySummaryStatusEnum::WARNING, $this->summary->getStatus());
    }

    public function test_statusUpdate_setsSoldOut_whenNoAvailableRooms(): void
    {
        $this->summary->setTotalRooms(100);
        $this->summary->setAvailableRooms(0);
        
        $this->assertSame(InventorySummaryStatusEnum::SOLD_OUT, $this->summary->getStatus());
    }

    public function test_statusUpdate_setsSoldOut_whenTotalRoomsIsZero(): void
    {
        $this->summary->setTotalRooms(0);
        $this->summary->setAvailableRooms(10);
        
        $this->assertSame(InventorySummaryStatusEnum::SOLD_OUT, $this->summary->getStatus());
    }

    public function test_statusUpdate_setsSoldOut_whenTotalRoomsIsNegative(): void
    {
        $this->summary->setTotalRooms(-5);
        $this->summary->setAvailableRooms(10);
        
        $this->assertSame(InventorySummaryStatusEnum::SOLD_OUT, $this->summary->getStatus());
    }

    public function test_toString_returnsCorrectFormat_withValidData(): void
    {
        $hotel = $this->createMock(Hotel::class);
        $hotel->method('getName')->willReturn('Grand Hotel');
        
        $roomType = $this->createMock(RoomType::class);
        $roomType->method('getName')->willReturn('Deluxe Room');
        
        $this->summary->setHotel($hotel);
        $this->summary->setRoomType($roomType);
        $this->summary->setDate(new \DateTime('2024-01-01'));
        
        $expected = 'Grand Hotel - Deluxe Room - 2024-01-01';
        $this->assertSame($expected, (string)$this->summary);
    }

    public function test_toString_handlesNullHotel(): void
    {
        $roomType = $this->createMock(RoomType::class);
        $roomType->method('getName')->willReturn('Deluxe Room');
        
        $this->summary->setRoomType($roomType);
        $this->summary->setDate(new \DateTime('2024-01-01'));
        
        $expected = 'Unknown - Deluxe Room - 2024-01-01';
        $this->assertSame($expected, (string)$this->summary);
    }

    public function test_toString_handlesNullRoomType(): void
    {
        $hotel = $this->createMock(Hotel::class);
        $hotel->method('getName')->willReturn('Grand Hotel');
        
        $this->summary->setHotel($hotel);
        $this->summary->setDate(new \DateTime('2024-01-01'));
        
        $expected = 'Grand Hotel - Unknown - 2024-01-01';
        $this->assertSame($expected, (string)$this->summary);
    }

    public function test_toString_handlesNullDate(): void
    {
        $hotel = $this->createMock(Hotel::class);
        $hotel->method('getName')->willReturn('Grand Hotel');
        
        $roomType = $this->createMock(RoomType::class);
        $roomType->method('getName')->willReturn('Deluxe Room');
        
        $this->summary->setHotel($hotel);
        $this->summary->setRoomType($roomType);
        
        $expected = 'Grand Hotel - Deluxe Room - Unknown Date';
        $this->assertSame($expected, (string)$this->summary);
    }

    public function test_toString_handlesAllNullValues(): void
    {
        $expected = 'Unknown - Unknown - Unknown Date';
        $this->assertSame($expected, (string)$this->summary);
    }
} 