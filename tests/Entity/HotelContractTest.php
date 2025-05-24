<?php

namespace Tourze\HotelContractBundle\Tests\Entity;

use PHPUnit\Framework\TestCase;
use Tourze\HotelContractBundle\Entity\DailyInventory;
use Tourze\HotelContractBundle\Entity\HotelContract;
use Tourze\HotelContractBundle\Entity\InventorySummary;
use Tourze\HotelContractBundle\Enum\ContractStatusEnum;
use Tourze\HotelContractBundle\Enum\ContractTypeEnum;
use Tourze\HotelProfileBundle\Entity\Hotel;

class HotelContractTest extends TestCase
{
    private HotelContract $contract;

    protected function setUp(): void
    {
        $this->contract = new HotelContract();
    }

    public function test_getId_returnsNull_whenNotPersisted(): void
    {
        $this->assertNull($this->contract->getId());
    }

    public function test_setContractNo_returnsInstance_withValidValue(): void
    {
        $contractNo = 'TEST-2024-001';
        $result = $this->contract->setContractNo($contractNo);

        $this->assertSame($this->contract, $result);
        $this->assertSame($contractNo, $this->contract->getContractNo());
    }

    public function test_getContractNo_returnsEmptyString_whenNotSet(): void
    {
        $this->assertSame('', $this->contract->getContractNo());
    }

    public function test_setHotel_returnsInstance_withValidHotel(): void
    {
        $hotel = $this->createMock(Hotel::class);
        $result = $this->contract->setHotel($hotel);
        
        $this->assertSame($this->contract, $result);
        $this->assertSame($hotel, $this->contract->getHotel());
    }

    public function test_setHotel_returnsInstance_withNullValue(): void
    {
        $result = $this->contract->setHotel(null);
        
        $this->assertSame($this->contract, $result);
        $this->assertNull($this->contract->getHotel());
    }

    public function test_getHotel_returnsNull_whenNotSet(): void
    {
        $this->assertNull($this->contract->getHotel());
    }

    public function test_setContractType_returnsInstance_withValidType(): void
    {
        $type = ContractTypeEnum::DYNAMIC_PRICE;
        $result = $this->contract->setContractType($type);
        
        $this->assertSame($this->contract, $result);
        $this->assertSame($type, $this->contract->getContractType());
    }

    public function test_getContractType_returnsDefaultValue_whenNotSet(): void
    {
        $this->assertSame(ContractTypeEnum::FIXED_PRICE, $this->contract->getContractType());
    }

    public function test_setStartDate_returnsInstance_withValidDate(): void
    {
        $date = new \DateTime('2024-01-01');
        $result = $this->contract->setStartDate($date);
        
        $this->assertSame($this->contract, $result);
        $this->assertSame($date, $this->contract->getStartDate());
    }

    public function test_setStartDate_returnsInstance_withNullValue(): void
    {
        $result = $this->contract->setStartDate(null);
        
        $this->assertSame($this->contract, $result);
        $this->assertNull($this->contract->getStartDate());
    }

    public function test_setEndDate_returnsInstance_withValidDate(): void
    {
        $date = new \DateTime('2024-12-31');
        $result = $this->contract->setEndDate($date);
        
        $this->assertSame($this->contract, $result);
        $this->assertSame($date, $this->contract->getEndDate());
    }

    public function test_setTotalRooms_returnsInstance_withPositiveValue(): void
    {
        $totalRooms = 100;
        $result = $this->contract->setTotalRooms($totalRooms);
        
        $this->assertSame($this->contract, $result);
        $this->assertSame($totalRooms, $this->contract->getTotalRooms());
    }

    public function test_getTotalRooms_returnsZero_whenNotSet(): void
    {
        $this->assertSame(0, $this->contract->getTotalRooms());
    }

    public function test_setTotalDays_returnsInstance_withPositiveValue(): void
    {
        $totalDays = 365;
        $result = $this->contract->setTotalDays($totalDays);
        
        $this->assertSame($this->contract, $result);
        $this->assertSame($totalDays, $this->contract->getTotalDays());
    }

    public function test_setTotalAmount_returnsInstance_withValidAmount(): void
    {
        $amount = '100000.50';
        $result = $this->contract->setTotalAmount($amount);
        
        $this->assertSame($this->contract, $result);
        $this->assertSame($amount, $this->contract->getTotalAmount());
    }

    public function test_getTotalAmount_returnsZero_whenNotSet(): void
    {
        $this->assertSame('0.00', $this->contract->getTotalAmount());
    }

    public function test_setAttachmentUrl_returnsInstance_withValidUrl(): void
    {
        $url = 'https://example.com/contract.pdf';
        $result = $this->contract->setAttachmentUrl($url);
        
        $this->assertSame($this->contract, $result);
        $this->assertSame($url, $this->contract->getAttachmentUrl());
    }

    public function test_setStatus_returnsInstance_withValidStatus(): void
    {
        $status = ContractStatusEnum::ACTIVE;
        $result = $this->contract->setStatus($status);
        
        $this->assertSame($this->contract, $result);
        $this->assertSame($status, $this->contract->getStatus());
    }

    public function test_getStatus_returnsDefaultValue_whenNotSet(): void
    {
        $this->assertSame(ContractStatusEnum::PENDING, $this->contract->getStatus());
    }

    public function test_setTerminationReason_returnsInstance_withValidReason(): void
    {
        $reason = 'Contract breach';
        $result = $this->contract->setTerminationReason($reason);
        
        $this->assertSame($this->contract, $result);
        $this->assertSame($reason, $this->contract->getTerminationReason());
    }

    public function test_setPriority_returnsInstance_withValidPriority(): void
    {
        $priority = 5;
        $result = $this->contract->setPriority($priority);
        
        $this->assertSame($this->contract, $result);
        $this->assertSame($priority, $this->contract->getPriority());
    }

    public function test_getPriority_returnsZero_whenNotSet(): void
    {
        $this->assertSame(0, $this->contract->getPriority());
    }

    public function test_getCreateTime_returnsNull_whenNotSet(): void
    {
        $this->assertNull($this->contract->getCreateTime());
    }

    public function test_getUpdateTime_returnsNull_whenNotSet(): void
    {
        $this->assertNull($this->contract->getUpdateTime());
    }

    public function test_getCreatedBy_returnsNull_whenNotSet(): void
    {
        $this->assertNull($this->contract->getCreatedBy());
    }

    public function test_getDailyInventories_returnsEmptyCollection_whenInitialized(): void
    {
        $inventories = $this->contract->getDailyInventories();
        
        $this->assertCount(0, $inventories);
    }

    public function test_addDailyInventory_returnsInstance_withValidInventory(): void
    {
        $inventory = $this->createMock(DailyInventory::class);
        $inventory->expects($this->once())
            ->method('setContract')
            ->with($this->contract);
        
        $result = $this->contract->addDailyInventory($inventory);
        
        $this->assertSame($this->contract, $result);
        $this->assertTrue($this->contract->getDailyInventories()->contains($inventory));
    }

    public function test_addDailyInventory_doesNotAddDuplicate_whenAlreadyExists(): void
    {
        $inventory = $this->createMock(DailyInventory::class);
        $inventory->expects($this->once())
            ->method('setContract')
            ->with($this->contract);
        
        $this->contract->addDailyInventory($inventory);
        $this->contract->addDailyInventory($inventory);
        
        $this->assertCount(1, $this->contract->getDailyInventories());
    }

    public function test_removeDailyInventory_returnsInstance_withValidInventory(): void
    {
        $inventory = $this->createMock(DailyInventory::class);
        $inventory->method('setContract')->willReturn($inventory);
        $inventory->method('getContract')->willReturn($this->contract);
        
        $this->contract->addDailyInventory($inventory);
        $result = $this->contract->removeDailyInventory($inventory);
        
        $this->assertSame($this->contract, $result);
    }

    public function test_getInventorySummaries_returnsEmptyCollection_whenInitialized(): void
    {
        $summaries = $this->contract->getInventorySummaries();
        
        $this->assertCount(0, $summaries);
    }

    public function test_addInventorySummary_returnsInstance_withValidSummary(): void
    {
        $summary = $this->createMock(InventorySummary::class);
        $summary->expects($this->once())
            ->method('setLowestContract')
            ->with($this->contract);
        
        $result = $this->contract->addInventorySummary($summary);
        
        $this->assertSame($this->contract, $result);
        $this->assertTrue($this->contract->getInventorySummaries()->contains($summary));
    }

    public function test_removeInventorySummary_returnsInstance_withValidSummary(): void
    {
        $summary = $this->createMock(InventorySummary::class);
        $summary->method('setLowestContract')->willReturn($summary);
        $summary->method('getLowestContract')->willReturn($this->contract);
        
        $this->contract->addInventorySummary($summary);
        $result = $this->contract->removeInventorySummary($summary);
        
        $this->assertSame($this->contract, $result);
    }

    public function test_setCreateTime_setsValue_withValidDateTime(): void
    {
        $dateTime = new \DateTime('2024-01-01 10:00:00');
        $this->contract->setCreateTime($dateTime);
        
        $this->assertSame($dateTime, $this->contract->getCreateTime());
    }

    public function test_setUpdateTime_setsValue_withValidDateTime(): void
    {
        $dateTime = new \DateTime('2024-01-01 10:00:00');
        $this->contract->setUpdateTime($dateTime);
        
        $this->assertSame($dateTime, $this->contract->getUpdateTime());
    }

    public function test_isActive_returnsTrue_whenStatusIsActive(): void
    {
        $this->contract->setStatus(ContractStatusEnum::ACTIVE);
        
        $this->assertTrue($this->contract->isActive());
    }

    public function test_isActive_returnsFalse_whenStatusIsNotActive(): void
    {
        $this->contract->setStatus(ContractStatusEnum::PENDING);
        
        $this->assertFalse($this->contract->isActive());
    }

    public function test_isActive_returnsFalse_whenStatusIsTerminated(): void
    {
        $this->contract->setStatus(ContractStatusEnum::TERMINATED);
        
        $this->assertFalse($this->contract->isActive());
    }

    public function test_getTotalSellingAmount_returnsZero_whenNoInventories(): void
    {
        $result = $this->contract->getTotalSellingAmount();
        
        $this->assertSame(0.0, $result);
    }

    public function test_getTotalCostAmount_returnsZero_whenNoInventories(): void
    {
        $result = $this->contract->getTotalCostAmount();
        
        $this->assertSame(0.0, $result);
    }

    public function test_getProfitRate_returnsZero_whenNoCostAmount(): void
    {
        $result = $this->contract->getProfitRate();
        
        $this->assertSame(0.0, $result);
    }

    public function test_toString_returnsContractNo(): void
    {
        $contractNo = 'TEST-2024-001';
        $this->contract->setContractNo($contractNo);
        
        $this->assertSame($contractNo, (string)$this->contract);
    }

    public function test_toString_returnsEmptyString_whenContractNoNotSet(): void
    {
        $this->assertSame('', (string)$this->contract);
    }
} 