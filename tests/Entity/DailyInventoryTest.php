<?php

namespace Tourze\HotelContractBundle\Tests\Entity;

use PHPUnit\Framework\TestCase;
use Tourze\HotelContractBundle\Entity\DailyInventory;
use Tourze\HotelContractBundle\Entity\HotelContract;
use Tourze\HotelContractBundle\Enum\DailyInventoryStatusEnum;
use Tourze\HotelProfileBundle\Entity\Hotel;
use Tourze\HotelProfileBundle\Entity\RoomType;

class DailyInventoryTest extends TestCase
{
    private DailyInventory $inventory;

    protected function setUp(): void
    {
        $this->inventory = new DailyInventory();
    }

    public function test_getId_returnsNull_whenNotPersisted(): void
    {
        $this->assertNull($this->inventory->getId());
    }

    public function test_setCode_returnsInstance_withValidCode(): void
    {
        $code = 'INV-2024-001';
        $result = $this->inventory->setCode($code);
        
        $this->assertSame($this->inventory, $result);
        $this->assertSame($code, $this->inventory->getCode());
    }

    public function test_getCode_returnsEmptyString_whenNotSet(): void
    {
        $this->assertSame('', $this->inventory->getCode());
    }

    public function test_setRoomType_returnsInstance_withValidRoomType(): void
    {
        $roomType = $this->createMock(RoomType::class);
        $result = $this->inventory->setRoomType($roomType);
        
        $this->assertSame($this->inventory, $result);
        $this->assertSame($roomType, $this->inventory->getRoomType());
    }

    public function test_setRoomType_returnsInstance_withNullValue(): void
    {
        $result = $this->inventory->setRoomType(null);
        
        $this->assertSame($this->inventory, $result);
        $this->assertNull($this->inventory->getRoomType());
    }

    public function test_getRoomType_returnsNull_whenNotSet(): void
    {
        $this->assertNull($this->inventory->getRoomType());
    }

    public function test_setHotel_returnsInstance_withValidHotel(): void
    {
        $hotel = $this->createMock(Hotel::class);
        $result = $this->inventory->setHotel($hotel);
        
        $this->assertSame($this->inventory, $result);
        $this->assertSame($hotel, $this->inventory->getHotel());
    }

    public function test_setHotel_returnsInstance_withNullValue(): void
    {
        $result = $this->inventory->setHotel(null);
        
        $this->assertSame($this->inventory, $result);
        $this->assertNull($this->inventory->getHotel());
    }

    public function test_getHotel_returnsNull_whenNotSet(): void
    {
        $this->assertNull($this->inventory->getHotel());
    }

    public function test_setDate_returnsInstance_withValidDate(): void
    {
        $date = new \DateTime('2024-01-01');
        $result = $this->inventory->setDate($date);
        
        $this->assertSame($this->inventory, $result);
        $this->assertSame($date, $this->inventory->getDate());
    }

    public function test_setDate_returnsInstance_withNullValue(): void
    {
        $result = $this->inventory->setDate(null);
        
        $this->assertSame($this->inventory, $result);
        $this->assertNull($this->inventory->getDate());
    }

    public function test_getDate_returnsNull_whenNotSet(): void
    {
        $this->assertNull($this->inventory->getDate());
    }

    public function test_setIsReserved_returnsInstance_withTrueValue(): void
    {
        $result = $this->inventory->setIsReserved(true);
        
        $this->assertSame($this->inventory, $result);
        $this->assertTrue($this->inventory->isReserved());
    }

    public function test_setIsReserved_returnsInstance_withFalseValue(): void
    {
        $result = $this->inventory->setIsReserved(false);
        
        $this->assertSame($this->inventory, $result);
        $this->assertFalse($this->inventory->isReserved());
    }

    public function test_isReserved_returnsFalse_whenNotSet(): void
    {
        $this->assertFalse($this->inventory->isReserved());
    }

    public function test_setStatus_returnsInstance_withValidStatus(): void
    {
        $status = DailyInventoryStatusEnum::SOLD;
        $result = $this->inventory->setStatus($status);
        
        $this->assertSame($this->inventory, $result);
        $this->assertSame($status, $this->inventory->getStatus());
    }

    public function test_getStatus_returnsDefaultValue_whenNotSet(): void
    {
        $this->assertSame(DailyInventoryStatusEnum::AVAILABLE, $this->inventory->getStatus());
    }

    public function test_setContract_returnsInstance_withValidContract(): void
    {
        $contract = $this->createMock(HotelContract::class);
        $result = $this->inventory->setContract($contract);
        
        $this->assertSame($this->inventory, $result);
        $this->assertSame($contract, $this->inventory->getContract());
    }

    public function test_setContract_returnsInstance_withNullValue(): void
    {
        $result = $this->inventory->setContract(null);
        
        $this->assertSame($this->inventory, $result);
        $this->assertNull($this->inventory->getContract());
    }

    public function test_getContract_returnsNull_whenNotSet(): void
    {
        $this->assertNull($this->inventory->getContract());
    }

    public function test_setCostPrice_returnsInstance_withValidPrice(): void
    {
        $price = '100.50';
        $result = $this->inventory->setCostPrice($price);
        
        $this->assertSame($this->inventory, $result);
        $this->assertSame($price, $this->inventory->getCostPrice());
    }

    public function test_setCostPrice_calculatesProfit_whenSellingPriceIsSet(): void
    {
        $this->inventory->setSellingPrice('120.00');
        $this->inventory->setCostPrice('100.00');
        
        $this->assertSame('20.00', $this->inventory->getProfitRate());
    }

    public function test_getCostPrice_returnsZero_whenNotSet(): void
    {
        $this->assertSame('0.00', $this->inventory->getCostPrice());
    }

    public function test_setSellingPrice_returnsInstance_withValidPrice(): void
    {
        $price = '150.75';
        $result = $this->inventory->setSellingPrice($price);
        
        $this->assertSame($this->inventory, $result);
        $this->assertSame($price, $this->inventory->getSellingPrice());
    }

    public function test_setSellingPrice_calculatesProfit_whenCostPriceIsSet(): void
    {
        $this->inventory->setCostPrice('100.00');
        $this->inventory->setSellingPrice('120.00');
        
        $this->assertSame('20.00', $this->inventory->getProfitRate());
    }

    public function test_getSellingPrice_returnsZero_whenNotSet(): void
    {
        $this->assertSame('0.00', $this->inventory->getSellingPrice());
    }

    public function test_setProfitRate_returnsInstance_withValidRate(): void
    {
        $rate = '25.50';
        $result = $this->inventory->setProfitRate($rate);
        
        $this->assertSame($this->inventory, $result);
        $this->assertSame($rate, $this->inventory->getProfitRate());
    }

    public function test_getProfitRate_returnsZero_whenNotSet(): void
    {
        $this->assertSame('0.00', $this->inventory->getProfitRate());
    }

    public function test_setPriceAdjustReason_returnsInstance_withValidReason(): void
    {
        $reason = 'Seasonal adjustment';
        $result = $this->inventory->setPriceAdjustReason($reason);
        
        $this->assertSame($this->inventory, $result);
        $this->assertSame($reason, $this->inventory->getPriceAdjustReason());
    }

    public function test_setPriceAdjustReason_returnsInstance_withNullValue(): void
    {
        $result = $this->inventory->setPriceAdjustReason(null);
        
        $this->assertSame($this->inventory, $result);
        $this->assertNull($this->inventory->getPriceAdjustReason());
    }

    public function test_getPriceAdjustReason_returnsNull_whenNotSet(): void
    {
        $this->assertNull($this->inventory->getPriceAdjustReason());
    }

    public function test_getCreateTime_returnsNull_whenNotSet(): void
    {
        $this->assertNull($this->inventory->getCreateTime());
    }

    public function test_getUpdateTime_returnsNull_whenNotSet(): void
    {
        $this->assertNull($this->inventory->getUpdateTime());
    }

    public function test_getLastModifiedBy_returnsNull_whenNotSet(): void
    {
        $this->assertNull($this->inventory->getLastModifiedBy());
    }

    public function test_setCreateTime_setsValue_withValidDateTime(): void
    {
        $dateTime = new \DateTime('2024-01-01 10:00:00');
        $this->inventory->setCreateTime($dateTime);
        
        $this->assertSame($dateTime, $this->inventory->getCreateTime());
    }

    public function test_setUpdateTime_setsValue_withValidDateTime(): void
    {
        $dateTime = new \DateTime('2024-01-01 10:00:00');
        $this->inventory->setUpdateTime($dateTime);
        
        $this->assertSame($dateTime, $this->inventory->getUpdateTime());
    }

    public function test_profitRateCalculation_returnsZero_whenCostPriceIsZero(): void
    {
        $this->inventory->setCostPrice('0.00');
        $this->inventory->setSellingPrice('100.00');
        
        $this->assertSame('0.00', $this->inventory->getProfitRate());
    }

    public function test_profitRateCalculation_returnsZero_whenSellingPriceIsZero(): void
    {
        $this->inventory->setCostPrice('100.00');
        $this->inventory->setSellingPrice('0.00');
        
        $this->assertSame('0.00', $this->inventory->getProfitRate());
    }

    public function test_profitRateCalculation_returnsCorrectValue_withValidPrices(): void
    {
        $this->inventory->setCostPrice('100.00');
        $this->inventory->setSellingPrice('150.00');
        
        $this->assertSame('50.00', $this->inventory->getProfitRate());
    }

    public function test_profitRateCalculation_handlesDecimals_correctly(): void
    {
        $this->inventory->setCostPrice('100.00');
        $this->inventory->setSellingPrice('133.33');
        
        $this->assertSame('33.33', $this->inventory->getProfitRate());
    }

    public function test_toString_returnsCorrectFormat_withValidData(): void
    {
        $roomType = $this->createMock(RoomType::class);
        $roomType->method('getName')->willReturn('Deluxe Room');
        
        $this->inventory->setRoomType($roomType);
        $this->inventory->setCode('INV-001');
        $this->inventory->setDate(new \DateTime('2024-01-01'));
        
        $expected = 'Deluxe Room - INV-001 - 2024-01-01';
        $this->assertSame($expected, (string)$this->inventory);
    }

    public function test_toString_handlesNullRoomType(): void
    {
        $this->inventory->setCode('INV-001');
        $this->inventory->setDate(new \DateTime('2024-01-01'));
        
        $expected = 'Unknown - INV-001 - 2024-01-01';
        $this->assertSame($expected, (string)$this->inventory);
    }

    public function test_toString_handlesNullDate(): void
    {
        $roomType = $this->createMock(RoomType::class);
        $roomType->method('getName')->willReturn('Deluxe Room');
        
        $this->inventory->setRoomType($roomType);
        $this->inventory->setCode('INV-001');
        
        $expected = 'Deluxe Room - INV-001 - Unknown Date';
        $this->assertSame($expected, (string)$this->inventory);
    }

    public function test_toString_handlesEmptyCode(): void
    {
        $roomType = $this->createMock(RoomType::class);
        $roomType->method('getName')->willReturn('Deluxe Room');
        
        $this->inventory->setRoomType($roomType);
        $this->inventory->setDate(new \DateTime('2024-01-01'));
        
        $expected = 'Deluxe Room -  - 2024-01-01';
        $this->assertSame($expected, (string)$this->inventory);
    }
} 