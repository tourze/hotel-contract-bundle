<?php

namespace Tourze\HotelContractBundle\Tests\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\HotelContractBundle\Entity\DailyInventory;
use Tourze\HotelContractBundle\Entity\HotelContract;
use Tourze\HotelContractBundle\Entity\InventorySummary;
use Tourze\HotelContractBundle\Enum\ContractStatusEnum;
use Tourze\HotelContractBundle\Enum\ContractTypeEnum;
use Tourze\PHPUnitDoctrineEntity\AbstractEntityTestCase;

/**
 * @internal
 */
#[CoversClass(HotelContract::class)]
final class HotelContractTest extends AbstractEntityTestCase
{
    protected function createEntity(): HotelContract
    {
        return new HotelContract();
    }

    public function testIsActiveReturnsTrueWhenStatusIsActive(): void
    {
        $contract = $this->createEntity();
        $contract->setStatus(ContractStatusEnum::ACTIVE);

        $this->assertTrue($contract->isActive());
    }

    public function testIsActiveReturnsFalseWhenStatusIsNotActive(): void
    {
        $contract = $this->createEntity();
        $contract->setStatus(ContractStatusEnum::PENDING);

        $this->assertFalse($contract->isActive());
    }

    public function testIsActiveReturnsFalseWhenStatusIsTerminated(): void
    {
        $contract = $this->createEntity();
        $contract->setStatus(ContractStatusEnum::TERMINATED);

        $this->assertFalse($contract->isActive());
    }

    public function testGetTotalSellingAmountReturnsZeroWhenNoInventories(): void
    {
        $contract = $this->createEntity();
        $result = $contract->getTotalSellingAmount();

        $this->assertSame(0.0, $result);
    }

    public function testGetTotalCostAmountReturnsZeroWhenNoInventories(): void
    {
        $contract = $this->createEntity();
        $result = $contract->getTotalCostAmount();

        $this->assertSame(0.0, $result);
    }

    public function testGetProfitRateReturnsZeroWhenNoCostAmount(): void
    {
        $contract = $this->createEntity();
        $result = $contract->getProfitRate();

        $this->assertSame(0.0, $result);
    }

    public function testToStringReturnsContractNo(): void
    {
        $contract = $this->createEntity();
        $contractNo = 'TEST-2024-001';
        $contract->setContractNo($contractNo);

        $this->assertSame($contractNo, $contract->__toString());
    }

    public function testToStringReturnsEmptyStringWhenContractNoNotSet(): void
    {
        $contract = $this->createEntity();
        $this->assertSame('', $contract->__toString());
    }

    public function testAddDailyInventoryReturnsInstanceWithValidInventory(): void
    {
        $contract = $this->createEntity();
        $inventory = new DailyInventory();

        $contract->addDailyInventory($inventory);
        $this->assertTrue($contract->getDailyInventories()->contains($inventory));
        $this->assertSame($contract, $inventory->getContract());
    }

    public function testAddDailyInventoryDoesNotAddDuplicateWhenAlreadyExists(): void
    {
        $contract = $this->createEntity();
        $inventory = new DailyInventory();

        $contract->addDailyInventory($inventory);
        $contract->addDailyInventory($inventory);

        $this->assertCount(1, $contract->getDailyInventories());
    }

    public function testRemoveDailyInventoryReturnsInstanceWithValidInventory(): void
    {
        $contract = $this->createEntity();
        $inventory = new DailyInventory();

        $contract->addDailyInventory($inventory);
        $contract->removeDailyInventory($inventory);
        $this->assertFalse($contract->getDailyInventories()->contains($inventory));
    }

    public function testAddInventorySummaryReturnsInstanceWithValidSummary(): void
    {
        $contract = $this->createEntity();
        $summary = new InventorySummary();

        $contract->addInventorySummary($summary);
        $this->assertTrue($contract->getInventorySummaries()->contains($summary));
        $this->assertSame($contract, $summary->getLowestContract());
    }

    public function testRemoveInventorySummaryReturnsInstanceWithValidSummary(): void
    {
        $contract = $this->createEntity();
        $summary = new InventorySummary();

        $contract->addInventorySummary($summary);
        $contract->removeInventorySummary($summary);
        $this->assertFalse($contract->getInventorySummaries()->contains($summary));
    }

    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function propertiesProvider(): iterable
    {
        yield 'contractNo' => ['contractNo', 'TEST-2024-001'];
        yield 'hotel' => ['hotel', null];
        yield 'contractType' => ['contractType', ContractTypeEnum::DYNAMIC_PRICE];
        yield 'startDate' => ['startDate', new \DateTimeImmutable('2024-01-01')];
        yield 'endDate' => ['endDate', new \DateTimeImmutable('2024-12-31')];
        yield 'totalRooms' => ['totalRooms', 100];
        yield 'totalDays' => ['totalDays', 365];
        yield 'totalAmount' => ['totalAmount', '100000.50'];
        yield 'attachmentUrl' => ['attachmentUrl', 'https://example.com/contract.pdf'];
        yield 'status' => ['status', ContractStatusEnum::ACTIVE];
        yield 'terminationReason' => ['terminationReason', 'Contract breach'];
        yield 'priority' => ['priority', 5];
        yield 'createTime' => ['createTime', new \DateTimeImmutable('2024-01-01 10:00:00')];
        yield 'updateTime' => ['updateTime', new \DateTimeImmutable('2024-01-01 10:00:00')];
        yield 'createdBy' => ['createdBy', 'admin'];
    }
}
