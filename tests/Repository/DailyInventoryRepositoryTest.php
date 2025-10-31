<?php

namespace Tourze\HotelContractBundle\Tests\Repository;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\HotelContractBundle\Entity\DailyInventory;
use Tourze\HotelContractBundle\Entity\HotelContract;
use Tourze\HotelContractBundle\Enum\ContractStatusEnum;
use Tourze\HotelContractBundle\Enum\ContractTypeEnum;
use Tourze\HotelContractBundle\Enum\DailyInventoryStatusEnum;
use Tourze\HotelContractBundle\Repository\DailyInventoryRepository;
use Tourze\HotelProfileBundle\Entity\Hotel;
use Tourze\HotelProfileBundle\Entity\RoomType;
use Tourze\HotelProfileBundle\Enum\HotelStatusEnum;
use Tourze\HotelProfileBundle\Enum\RoomTypeStatusEnum;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;

/**
 * @internal
 */
#[CoversClass(DailyInventoryRepository::class)]
#[RunTestsInSeparateProcesses]
final class DailyInventoryRepositoryTest extends AbstractRepositoryTestCase
{
    protected function onSetUp(): void
    {
        // Setup for repository tests
    }

    protected function createNewEntity(): DailyInventory
    {
        // 创建测试所需的关联实体
        $hotel = new Hotel();
        $hotel->setName('测试酒店_' . uniqid());
        $hotel->setAddress('测试地址');
        $hotel->setStarLevel(4);
        $hotel->setContactPerson('测试联系人');
        $hotel->setPhone('13800138000');
        $hotel->setStatus(HotelStatusEnum::OPERATING);

        $roomType = new RoomType();
        $roomType->setHotel($hotel);
        $roomType->setName('标准间_' . uniqid());
        $roomType->setCode('STD_' . uniqid());
        $roomType->setArea(25.0);
        $roomType->setBedType('标准双人床');
        $roomType->setMaxGuests(2);
        $roomType->setBreakfastCount(2);
        $roomType->setStatus(RoomTypeStatusEnum::ACTIVE);

        $inventory = new DailyInventory();
        $inventory->setCode('TEST_INV_' . uniqid());
        $inventory->setRoomType($roomType);
        $inventory->setHotel($hotel);
        $inventory->setDate(new \DateTimeImmutable('2024-01-01'));
        $inventory->setIsReserved(false);
        $inventory->setStatus(DailyInventoryStatusEnum::AVAILABLE);
        $inventory->setCostPrice('100.00');
        $inventory->setSellingPrice('200.00');

        // 持久化关联实体
        self::getEntityManager()->persist($hotel);
        self::getEntityManager()->persist($roomType);
        self::getEntityManager()->flush();

        return $inventory;
    }

    protected function getRepository(): DailyInventoryRepository
    {
        return self::getService(DailyInventoryRepository::class);
    }

    private function createTestHotel(): Hotel
    {
        $hotel = new Hotel();
        $hotel->setName('测试酒店');
        $hotel->setAddress('测试地址');
        $hotel->setStarLevel(4);
        $hotel->setContactPerson('测试联系人');
        $hotel->setPhone('13800138000');
        $hotel->setStatus(HotelStatusEnum::OPERATING);

        return $hotel;
    }

    private function createTestRoomType(Hotel $hotel, string $name = '标准间'): RoomType
    {
        $roomType = new RoomType();
        $roomType->setHotel($hotel);
        $roomType->setName($name);
        $roomType->setCode('STD');
        $roomType->setArea(25.0);
        $roomType->setBedType('标准双人床');
        $roomType->setMaxGuests(2);
        $roomType->setBreakfastCount(2);
        $roomType->setStatus(RoomTypeStatusEnum::ACTIVE);

        return $roomType;
    }

    private function createTestContract(Hotel $hotel): HotelContract
    {
        $contract = new HotelContract();
        $contract->setContractNo('CONTRACT-001');
        $contract->setHotel($hotel);
        $contract->setContractType(ContractTypeEnum::FIXED_PRICE);
        $contract->setStartDate(new \DateTimeImmutable('2024-01-01'));
        $contract->setEndDate(new \DateTimeImmutable('2024-12-31'));
        $contract->setTotalRooms(100);
        $contract->setTotalDays(365);
        $contract->setTotalAmount('100000.00');
        $contract->setStatus(ContractStatusEnum::ACTIVE);
        $contract->setPriority(1);

        return $contract;
    }

    private function createTestDailyInventory(
        RoomType $roomType,
        Hotel $hotel,
        \DateTimeInterface $date,
        string $code = 'INV-001',
    ): DailyInventory {
        $inventory = new DailyInventory();
        $inventory->setCode($code);
        $inventory->setRoomType($roomType);
        $inventory->setHotel($hotel);
        $inventory->setDate($date);
        $inventory->setIsReserved(false);
        $inventory->setStatus(DailyInventoryStatusEnum::AVAILABLE);
        $inventory->setCostPrice('100.00');
        $inventory->setSellingPrice('200.00');

        return $inventory;
    }

    public function testSaveWithValidInventoryPersistsToDatabase(): void
    {
        // Arrange
        $hotel = $this->createTestHotel();
        $roomType = $this->createTestRoomType($hotel);
        self::getEntityManager()->persist($hotel);
        self::getEntityManager()->persist($roomType);
        self::getEntityManager()->flush();

        $inventory = $this->createTestDailyInventory($roomType, $hotel, new \DateTimeImmutable('2024-01-01'));

        // Act
        self::getEntityManager()->persist($inventory);
        self::getEntityManager()->flush();

        // Assert
        $this->assertNotNull($inventory->getId());
        self::getEntityManager()->refresh($inventory);
        $this->assertEquals('INV-001', $inventory->getCode());
        $this->assertNotNull($inventory->getRoomType());
        $this->assertNotNull($inventory->getHotel());
        $this->assertEquals($roomType->getId(), $inventory->getRoomType()->getId());
        $this->assertEquals($hotel->getId(), $inventory->getHotel()->getId());
    }

    public function testSaveWithoutFlushDoesNotPersistImmediately(): void
    {
        // Arrange
        $hotel = $this->createTestHotel();
        $roomType = $this->createTestRoomType($hotel);
        self::getEntityManager()->persist($hotel);
        self::getEntityManager()->persist($roomType);
        self::getEntityManager()->flush();

        $inventory = $this->createTestDailyInventory($roomType, $hotel, new \DateTimeImmutable('2024-01-01'));

        // Act
        self::getEntityManager()->persist($inventory);

        // Assert
        $this->assertNull($inventory->getId());

        // Flush and verify
        self::getEntityManager()->flush();
        $this->assertNotNull($inventory->getId());
    }

    public function testRemoveWithValidInventoryDeletesFromDatabase(): void
    {
        // Arrange
        $hotel = $this->createTestHotel();
        $roomType = $this->createTestRoomType($hotel);
        $inventory = $this->createTestDailyInventory($roomType, $hotel, new \DateTimeImmutable('2024-01-01'));
        self::getEntityManager()->persist($hotel);
        self::getEntityManager()->persist($roomType);
        self::getEntityManager()->persist($inventory);
        self::getEntityManager()->flush();

        $inventoryId = $inventory->getId();

        // Act
        self::getEntityManager()->remove($inventory);
        self::getEntityManager()->flush();

        // Assert
        $deletedInventory = $this->getRepository()->find($inventoryId);
        $this->assertNull($deletedInventory);
    }

    public function testFindByRoomTypeAndDateWithExistingInventoryReturnsInventory(): void
    {
        // Arrange - 查询数据库中现有的库存数据
        $allInventories = $this->getRepository()->findAll();
        $this->assertGreaterThan(0, count($allInventories), 'No inventories found in fixtures');

        // 取第一个可用的库存数据进行测试
        $existingInventory = $allInventories[0];
        $this->assertNotNull($existingInventory->getRoomType());
        $roomTypeId = $existingInventory->getRoomType()->getId();
        $this->assertNotNull($roomTypeId);
        $date = $existingInventory->getDate();
        $this->assertNotNull($date);

        // Act
        $foundInventory = $this->getRepository()->findByRoomTypeAndDate($roomTypeId, $date);

        // Assert
        $this->assertNotNull($foundInventory);
        $this->assertNotNull($foundInventory->getRoomType());
        $this->assertEquals($existingInventory->getId(), $foundInventory->getId());
        $this->assertEquals($roomTypeId, $foundInventory->getRoomType()->getId());
        $this->assertNotNull($foundInventory->getDate());
        $this->assertEquals($date->format('Y-m-d'), $foundInventory->getDate()->format('Y-m-d'));
    }

    public function testFindByRoomTypeAndDateWithNonExistentInventoryReturnsNull(): void
    {
        // Arrange
        $hotel = $this->createTestHotel();
        $roomType = $this->createTestRoomType($hotel);
        self::getEntityManager()->persist($hotel);
        self::getEntityManager()->persist($roomType);
        self::getEntityManager()->flush();

        // Act
        $this->assertNotNull($roomType->getId());
        $foundInventory = $this->getRepository()->findByRoomTypeAndDate($roomType->getId(), new \DateTimeImmutable('2024-01-01'));

        // Assert
        $this->assertNull($foundInventory);
    }

    public function testFindAvailableByDateRangeReturnsOnlyAvailableInventories(): void
    {
        // Arrange
        $hotel = $this->createTestHotel();
        $roomType = $this->createTestRoomType($hotel);
        self::getEntityManager()->persist($hotel);
        self::getEntityManager()->persist($roomType);

        $availableInventory = $this->createTestDailyInventory($roomType, $hotel, new \DateTimeImmutable('2024-01-01'), 'AVAILABLE-001');
        $availableInventory->setStatus(DailyInventoryStatusEnum::AVAILABLE);

        $soldOutInventory = $this->createTestDailyInventory($roomType, $hotel, new \DateTimeImmutable('2024-01-02'), 'SOLDOUT-001');
        $soldOutInventory->setStatus(DailyInventoryStatusEnum::SOLD);

        self::getEntityManager()->persist($availableInventory);
        self::getEntityManager()->persist($soldOutInventory);
        self::getEntityManager()->flush();

        // Act
        $startDate = new \DateTimeImmutable('2024-01-01');
        $endDate = new \DateTimeImmutable('2024-01-02');
        $availableInventories = $this->getRepository()->findAvailableByDateRange($startDate, $endDate);

        // Assert
        $this->assertCount(1, $availableInventories);
        $this->assertEquals('AVAILABLE-001', $availableInventories[0]->getCode());
        $this->assertEquals(DailyInventoryStatusEnum::AVAILABLE, $availableInventories[0]->getStatus());
    }

    public function testFindByContractIdWithExistingContractReturnsInventories(): void
    {
        // Arrange
        $hotel = $this->createTestHotel();
        $roomType = $this->createTestRoomType($hotel);
        $contract = $this->createTestContract($hotel);
        self::getEntityManager()->persist($hotel);
        self::getEntityManager()->persist($roomType);
        self::getEntityManager()->persist($contract);

        $inventory1 = $this->createTestDailyInventory($roomType, $hotel, new \DateTimeImmutable('2024-01-01'), 'INV-001');
        $inventory1->setContract($contract);
        $inventory2 = $this->createTestDailyInventory($roomType, $hotel, new \DateTimeImmutable('2024-01-02'), 'INV-002');
        $inventory2->setContract($contract);

        self::getEntityManager()->persist($inventory1);
        self::getEntityManager()->persist($inventory2);
        self::getEntityManager()->flush();

        // Act
        $this->assertNotNull($contract->getId());
        $inventories = $this->getRepository()->findByContractId($contract->getId());

        // Assert
        $this->assertCount(2, $inventories);
        // 按日期升序排列
        $this->assertEquals('INV-001', $inventories[0]->getCode());
        $this->assertEquals('INV-002', $inventories[1]->getCode());
    }

    public function testFindByDateWithSpecificDateReturnsAllInventoriesForDate(): void
    {
        // Arrange
        $hotel = $this->createTestHotel();
        $roomType1 = $this->createTestRoomType($hotel, '标准间');
        $roomType2 = $this->createTestRoomType($hotel, '豪华间');
        self::getEntityManager()->persist($hotel);
        self::getEntityManager()->persist($roomType1);
        self::getEntityManager()->persist($roomType2);

        $date = new \DateTimeImmutable('2024-01-01');
        $inventory1 = $this->createTestDailyInventory($roomType1, $hotel, $date, 'INV-001');
        $inventory2 = $this->createTestDailyInventory($roomType2, $hotel, $date, 'INV-002');
        $inventory3 = $this->createTestDailyInventory($roomType1, $hotel, new \DateTimeImmutable('2024-01-02'), 'INV-003');

        self::getEntityManager()->persist($inventory1);
        self::getEntityManager()->persist($inventory2);
        self::getEntityManager()->persist($inventory3);
        self::getEntityManager()->flush();

        // Act
        $inventories = $this->getRepository()->findByDate($date);

        // Assert
        $this->assertCount(2, $inventories);
        $codes = array_map(fn ($inv) => $inv->getCode(), $inventories);
        $this->assertContains('INV-001', $codes);
        $this->assertContains('INV-002', $codes);
        $this->assertNotContains('INV-003', $codes);
    }

    public function testFindByRoomTypeIdReturnsInventoriesOrderedByDate(): void
    {
        // Arrange
        $hotel = $this->createTestHotel();
        $roomType = $this->createTestRoomType($hotel);
        self::getEntityManager()->persist($hotel);
        self::getEntityManager()->persist($roomType);

        $inventory1 = $this->createTestDailyInventory($roomType, $hotel, new \DateTimeImmutable('2024-01-02'), 'INV-002');
        $inventory2 = $this->createTestDailyInventory($roomType, $hotel, new \DateTimeImmutable('2024-01-01'), 'INV-001');
        $inventory3 = $this->createTestDailyInventory($roomType, $hotel, new \DateTimeImmutable('2024-01-03'), 'INV-003');

        self::getEntityManager()->persist($inventory1);
        self::getEntityManager()->persist($inventory2);
        self::getEntityManager()->persist($inventory3);
        self::getEntityManager()->flush();

        // Act
        $this->assertNotNull($roomType->getId());
        $inventories = $this->getRepository()->findByRoomTypeId($roomType->getId());

        // Assert
        $this->assertCount(3, $inventories);
        // 按日期升序排列
        $this->assertEquals('INV-001', $inventories[0]->getCode());
        $this->assertEquals('INV-002', $inventories[1]->getCode());
        $this->assertEquals('INV-003', $inventories[2]->getCode());
    }

    public function testFindByStatusReturnsInventoriesWithSpecificStatus(): void
    {
        // Arrange
        $hotel = $this->createTestHotel();
        $roomType = $this->createTestRoomType($hotel);
        self::getEntityManager()->persist($hotel);
        self::getEntityManager()->persist($roomType);

        $availableInventory = $this->createTestDailyInventory($roomType, $hotel, new \DateTimeImmutable('2024-01-01'), 'AVAILABLE-001');
        $availableInventory->setStatus(DailyInventoryStatusEnum::AVAILABLE);

        $soldOutInventory = $this->createTestDailyInventory($roomType, $hotel, new \DateTimeImmutable('2024-01-02'), 'SOLDOUT-001');
        $soldOutInventory->setStatus(DailyInventoryStatusEnum::SOLD);

        $reservedInventory = $this->createTestDailyInventory($roomType, $hotel, new \DateTimeImmutable('2024-01-03'), 'RESERVED-001');
        $reservedInventory->setStatus(DailyInventoryStatusEnum::RESERVED);

        self::getEntityManager()->persist($availableInventory);
        self::getEntityManager()->persist($soldOutInventory);
        self::getEntityManager()->persist($reservedInventory);
        self::getEntityManager()->flush();

        // Act
        $availableInventories = $this->getRepository()->findByStatus(DailyInventoryStatusEnum::AVAILABLE);
        $soldInventories = $this->getRepository()->findByStatus(DailyInventoryStatusEnum::SOLD);

        // Assert - 检查是否包含我们创建的测试数据
        $this->assertGreaterThanOrEqual(1, count($availableInventories));
        $availableCodes = array_map(fn ($inv) => $inv->getCode(), $availableInventories);
        $this->assertContains('AVAILABLE-001', $availableCodes);

        $this->assertGreaterThanOrEqual(1, count($soldInventories));
        $soldCodes = array_map(fn ($inv) => $inv->getCode(), $soldInventories);
        $this->assertContains('SOLDOUT-001', $soldCodes);
    }

    public function testFindDistinctRoomTypesByContractReturnsUniqueRoomTypeIds(): void
    {
        // Arrange
        $hotel = $this->createTestHotel();
        $roomType1 = $this->createTestRoomType($hotel, '标准间');
        $roomType2 = $this->createTestRoomType($hotel, '豪华间');
        $contract = $this->createTestContract($hotel);
        self::getEntityManager()->persist($hotel);
        self::getEntityManager()->persist($roomType1);
        self::getEntityManager()->persist($roomType2);
        self::getEntityManager()->persist($contract);

        // 创建多个库存，但只有两种房型
        $inventory1 = $this->createTestDailyInventory($roomType1, $hotel, new \DateTimeImmutable('2024-01-01'), 'INV-001');
        $inventory1->setContract($contract);
        $inventory2 = $this->createTestDailyInventory($roomType1, $hotel, new \DateTimeImmutable('2024-01-02'), 'INV-002');
        $inventory2->setContract($contract);
        $inventory3 = $this->createTestDailyInventory($roomType2, $hotel, new \DateTimeImmutable('2024-01-01'), 'INV-003');
        $inventory3->setContract($contract);

        self::getEntityManager()->persist($inventory1);
        self::getEntityManager()->persist($inventory2);
        self::getEntityManager()->persist($inventory3);
        self::getEntityManager()->flush();

        // Act
        $this->assertNotNull($contract->getId());
        $roomTypeIds = $this->getRepository()->findDistinctRoomTypesByContract($contract->getId());

        // Assert
        $this->assertCount(2, $roomTypeIds);
        $this->assertNotNull($roomType1->getId());
        $this->assertNotNull($roomType2->getId());
        $this->assertContains($roomType1->getId(), $roomTypeIds);
        $this->assertContains($roomType2->getId(), $roomTypeIds);
    }

    public function testFindPriceDataByContractAndDateRangeReturnsFormattedPriceData(): void
    {
        // Arrange
        $hotel = $this->createTestHotel();
        $roomType = $this->createTestRoomType($hotel, '标准间');
        $contract = $this->createTestContract($hotel);
        self::getEntityManager()->persist($hotel);
        self::getEntityManager()->persist($roomType);
        self::getEntityManager()->persist($contract);

        $inventory1 = $this->createTestDailyInventory($roomType, $hotel, new \DateTimeImmutable('2024-01-01'), 'INV-001');
        $inventory1->setContract($contract);
        $inventory1->setCostPrice('100.00');
        $inventory1->setSellingPrice('200.00');

        $inventory2 = $this->createTestDailyInventory($roomType, $hotel, new \DateTimeImmutable('2024-01-02'), 'INV-002');
        $inventory2->setContract($contract);
        $inventory2->setCostPrice('120.00');
        $inventory2->setSellingPrice('240.00');

        // 超出日期范围的库存（不应包含）
        $inventory3 = $this->createTestDailyInventory($roomType, $hotel, new \DateTimeImmutable('2024-02-01'), 'INV-003');
        $inventory3->setContract($contract);

        self::getEntityManager()->persist($inventory1);
        self::getEntityManager()->persist($inventory2);
        self::getEntityManager()->persist($inventory3);
        self::getEntityManager()->flush();

        // Act
        $startDate = new \DateTimeImmutable('2024-01-01');
        $endDate = new \DateTimeImmutable('2024-01-31');
        $this->assertNotNull($contract->getId());
        $priceData = $this->getRepository()->findPriceDataByContractAndDateRange(
            $contract->getId(),
            $startDate,
            $endDate
        );

        // Assert
        $this->assertCount(2, $priceData);

        // 验证返回的数据结构
        $firstItem = $priceData[0];
        $this->assertArrayHasKey('id', $firstItem);
        $this->assertArrayHasKey('costPrice', $firstItem);
        $this->assertArrayHasKey('sellingPrice', $firstItem);
        $this->assertArrayHasKey('date', $firstItem);
        $this->assertArrayHasKey('inventoryCode', $firstItem);
        $this->assertArrayHasKey('roomTypeId', $firstItem);
        $this->assertArrayHasKey('roomTypeName', $firstItem);

        // 验证价格数据
        $codes = array_column($priceData, 'inventoryCode');
        $this->assertContains('INV-001', $codes);
        $this->assertContains('INV-002', $codes);
        $this->assertNotContains('INV-003', $codes);
    }

    public function testFindByDateRangeAndCriteria(): void
    {
        $repository = $this->getRepository();

        $hotel = $this->createTestHotel();
        $roomType = $this->createTestRoomType($hotel);
        self::getEntityManager()->persist($hotel);
        self::getEntityManager()->persist($roomType);
        self::getEntityManager()->flush();

        $startDate = new \DateTimeImmutable('2024-01-01');
        $endDate = new \DateTimeImmutable('2024-01-31');

        $this->assertNotNull($hotel->getId());
        $criteria = ['di.hotel' => $hotel->getId()];
        $result = $repository->findByDateRangeAndCriteria($startDate, $endDate, $criteria);

        // 验证返回的数组中所有元素都是DailyInventory实例
        $this->assertContainsOnlyInstancesOf(DailyInventory::class, $result);
    }

    public function testFindByDateRangeAndWeekdays(): void
    {
        $repository = $this->getRepository();

        $hotel = $this->createTestHotel();
        $roomType = $this->createTestRoomType($hotel);
        self::getEntityManager()->persist($hotel);
        self::getEntityManager()->persist($roomType);
        self::getEntityManager()->flush();

        $startDate = new \DateTimeImmutable('2024-01-01');
        $endDate = new \DateTimeImmutable('2024-01-31');
        $this->assertNotNull($roomType->getId());
        $criteria = ['di.roomType' => $roomType->getId()];
        $dayFilter = 'weekdays';
        $days = [1, 2, 3, 4, 5];

        $result = $repository->findByDateRangeAndWeekdays($startDate, $endDate, $criteria, $dayFilter, $days);

        // 验证返回的数组中所有元素都是DailyInventory实例
        $this->assertContainsOnlyInstancesOf(DailyInventory::class, $result);
    }

    public function testFindByRoomId(): void
    {
        $repository = $this->getRepository();

        $hotel = $this->createTestHotel();
        $roomType = $this->createTestRoomType($hotel);
        self::getEntityManager()->persist($hotel);
        self::getEntityManager()->persist($roomType);
        self::getEntityManager()->flush();

        $this->assertNotNull($roomType->getId());
        $result = $repository->findByRoomTypeId($roomType->getId());

        // 验证返回的数组中所有元素都是DailyInventory实例
        $this->assertContainsOnlyInstancesOf(DailyInventory::class, $result);
    }

    public function testFindByRoomAndDate(): void
    {
        $repository = $this->getRepository();

        $hotel = $this->createTestHotel();
        $roomType = $this->createTestRoomType($hotel);
        self::getEntityManager()->persist($hotel);
        self::getEntityManager()->persist($roomType);
        self::getEntityManager()->flush();

        $date = new \DateTimeImmutable('2024-01-01');
        $this->assertNotNull($roomType->getId());
        $result = $repository->findByRoomTypeAndDate($roomType->getId(), $date);

        $this->assertNull($result);
    }

    // 标准 Repository 测试方法

    // 关联查询测试
    public function testFindByRoomTypeAssociation(): void
    {
        $hotel = $this->createTestHotel();
        $roomType1 = $this->createTestRoomType($hotel, '标准间');
        $roomType2 = $this->createTestRoomType($hotel, '豪华间');
        $inventory1 = $this->createTestDailyInventory($roomType1, $hotel, new \DateTimeImmutable('2024-01-01'), 'STD-001');
        $inventory2 = $this->createTestDailyInventory($roomType2, $hotel, new \DateTimeImmutable('2024-01-01'), 'LUX-001');

        $this->persistAndFlush($hotel);
        $this->persistAndFlush($roomType1);
        $this->persistAndFlush($roomType2);
        $this->persistAndFlush($inventory1);
        $this->persistAndFlush($inventory2);

        $result = $this->getRepository()->findBy(['roomType' => $roomType1]);

        // 验证查询结果的业务逻辑
        $this->assertCount(1, $result);
        $this->assertContainsOnlyInstancesOf(DailyInventory::class, $result);
        $this->assertEquals('STD-001', $result[0]->getCode());
        $this->assertNotNull($result[0]->getRoomType());
        $this->assertNotNull($roomType1->getId());
        $this->assertEquals($roomType1->getId(), $result[0]->getRoomType()->getId());
    }

    public function testFindByHotelAssociation(): void
    {
        $hotel1 = $this->createTestHotel();
        $hotel1->setName('酒店1');
        $hotel2 = $this->createTestHotel();
        $hotel2->setName('酒店2');
        $roomType1 = $this->createTestRoomType($hotel1);
        $roomType2 = $this->createTestRoomType($hotel2);
        $inventory1 = $this->createTestDailyInventory($roomType1, $hotel1, new \DateTimeImmutable('2024-01-01'), 'H1-001');
        $inventory2 = $this->createTestDailyInventory($roomType2, $hotel2, new \DateTimeImmutable('2024-01-01'), 'H2-001');

        $this->persistAndFlush($hotel1);
        $this->persistAndFlush($hotel2);
        $this->persistAndFlush($roomType1);
        $this->persistAndFlush($roomType2);
        $this->persistAndFlush($inventory1);
        $this->persistAndFlush($inventory2);

        $result = $this->getRepository()->findBy(['hotel' => $hotel1]);

        // 验证查询结果的业务逻辑
        $this->assertCount(1, $result);
        $this->assertContainsOnlyInstancesOf(DailyInventory::class, $result);
        $this->assertEquals('H1-001', $result[0]->getCode());
        $this->assertNotNull($result[0]->getHotel());
        $this->assertNotNull($hotel1->getId());
        $this->assertEquals($hotel1->getId(), $result[0]->getHotel()->getId());
    }

    public function testFindByContractAssociation(): void
    {
        $hotel = $this->createTestHotel();
        $roomType = $this->createTestRoomType($hotel);
        $contract = $this->createTestContract($hotel);
        $inventory1 = $this->createTestDailyInventory($roomType, $hotel, new \DateTimeImmutable('2024-01-01'), 'CONTRACT-001');
        $inventory1->setContract($contract);
        $inventory2 = $this->createTestDailyInventory($roomType, $hotel, new \DateTimeImmutable('2024-01-02'), 'NO-CONTRACT-001');

        $this->persistAndFlush($hotel);
        $this->persistAndFlush($roomType);
        $this->persistAndFlush($contract);
        $this->persistAndFlush($inventory1);
        $this->persistAndFlush($inventory2);

        $result = $this->getRepository()->findBy(['contract' => $contract]);

        // 验证查询结果的业务逻辑
        $this->assertCount(1, $result);
        $this->assertContainsOnlyInstancesOf(DailyInventory::class, $result);
        $this->assertEquals('CONTRACT-001', $result[0]->getCode());
        $this->assertNotNull($result[0]->getContract());
        $this->assertNotNull($contract->getId());
        $this->assertEquals($contract->getId(), $result[0]->getContract()->getId());
    }

    // count 关联查询测试
    public function testCountByRoomTypeAssociation(): void
    {
        $hotel = $this->createTestHotel();
        $roomType1 = $this->createTestRoomType($hotel, '标准间');
        $roomType2 = $this->createTestRoomType($hotel, '豪华间');
        $inventory1 = $this->createTestDailyInventory($roomType1, $hotel, new \DateTimeImmutable('2024-01-01'), 'STD-001');
        $inventory2 = $this->createTestDailyInventory($roomType1, $hotel, new \DateTimeImmutable('2024-01-02'), 'STD-002');
        $inventory3 = $this->createTestDailyInventory($roomType2, $hotel, new \DateTimeImmutable('2024-01-01'), 'LUX-001');

        $this->persistAndFlush($hotel);
        $this->persistAndFlush($roomType1);
        $this->persistAndFlush($roomType2);
        $this->persistAndFlush($inventory1);
        $this->persistAndFlush($inventory2);
        $this->persistAndFlush($inventory3);

        $count = $this->getRepository()->count(['roomType' => $roomType1]);

        $this->assertEquals(2, $count);
    }

    public function testCountByHotelAssociation(): void
    {
        $hotel1 = $this->createTestHotel();
        $hotel1->setName('酒店1');
        $hotel2 = $this->createTestHotel();
        $hotel2->setName('酒店2');
        $roomType1 = $this->createTestRoomType($hotel1);
        $roomType2 = $this->createTestRoomType($hotel2);
        $inventory1 = $this->createTestDailyInventory($roomType1, $hotel1, new \DateTimeImmutable('2024-01-01'), 'H1-001');
        $inventory2 = $this->createTestDailyInventory($roomType2, $hotel2, new \DateTimeImmutable('2024-01-01'), 'H2-001');

        $this->persistAndFlush($hotel1);
        $this->persistAndFlush($hotel2);
        $this->persistAndFlush($roomType1);
        $this->persistAndFlush($roomType2);
        $this->persistAndFlush($inventory1);
        $this->persistAndFlush($inventory2);

        $count = $this->getRepository()->count(['hotel' => $hotel1]);

        $this->assertEquals(1, $count);
    }

    public function testCountByContractAssociation(): void
    {
        $hotel = $this->createTestHotel();
        $roomType = $this->createTestRoomType($hotel);
        $contract = $this->createTestContract($hotel);
        $inventory1 = $this->createTestDailyInventory($roomType, $hotel, new \DateTimeImmutable('2024-01-01'), 'CONTRACT-001');
        $inventory1->setContract($contract);
        $inventory2 = $this->createTestDailyInventory($roomType, $hotel, new \DateTimeImmutable('2024-01-02'), 'CONTRACT-002');
        $inventory2->setContract($contract);
        $inventory3 = $this->createTestDailyInventory($roomType, $hotel, new \DateTimeImmutable('2024-01-03'), 'NO-CONTRACT-001');

        $this->persistAndFlush($hotel);
        $this->persistAndFlush($roomType);
        $this->persistAndFlush($contract);
        $this->persistAndFlush($inventory1);
        $this->persistAndFlush($inventory2);
        $this->persistAndFlush($inventory3);

        $count = $this->getRepository()->count(['contract' => $contract]);

        $this->assertEquals(2, $count);
    }

    // IS NULL 查询测试
    public function testFindByContractIsNull(): void
    {
        // Arrange - 查询没有合同的库存
        $result = $this->getRepository()->findBy(['contract' => null]);

        // Assert - 验证查询结果包含DailyInventory实例
        $this->assertContainsOnlyInstancesOf(DailyInventory::class, $result);

        // 验证所有结果都没有合同
        foreach ($result as $inventory) {
            $this->assertNull($inventory->getContract(), 'All inventories should have null contract');
            $this->assertNotEmpty($inventory->getCode());
        }
    }

    public function testFindByPriceAdjustReasonIsNull(): void
    {
        // Arrange - 查询没有价格调整原因的库存
        $result = $this->getRepository()->findBy(['priceAdjustReason' => null]);

        // Assert - 验证查询结果包含DailyInventory实例
        $this->assertContainsOnlyInstancesOf(DailyInventory::class, $result);

        // 验证所有结果都没有价格调整原因
        foreach ($result as $inventory) {
            $this->assertNull($inventory->getPriceAdjustReason(), 'All inventories should have null priceAdjustReason');
            $this->assertNotEmpty($inventory->getCode());
        }
    }

    // count IS NULL 查询测试
    public function testCountByContractIsNull(): void
    {
        $hotel = $this->createTestHotel();
        $roomType = $this->createTestRoomType($hotel);
        $contract = $this->createTestContract($hotel);
        $inventory1 = $this->createTestDailyInventory($roomType, $hotel, new \DateTimeImmutable('2024-01-01'), 'CONTRACT-COUNT-001');
        $inventory1->setContract($contract);
        $inventory2 = $this->createTestDailyInventory($roomType, $hotel, new \DateTimeImmutable('2024-01-02'), 'CONTRACT-COUNT-002');
        $inventory2->setContract(null);
        $inventory3 = $this->createTestDailyInventory($roomType, $hotel, new \DateTimeImmutable('2024-01-03'), 'CONTRACT-COUNT-003');
        $inventory3->setContract(null);

        $this->persistAndFlush($hotel);
        $this->persistAndFlush($roomType);
        $this->persistAndFlush($contract);
        $this->persistAndFlush($inventory1);
        $this->persistAndFlush($inventory2);
        $this->persistAndFlush($inventory3);

        // 添加更精确的查询条件以避免其他测试的干扰
        $count = $this->getRepository()->count([
            'contract' => null,
            'hotel' => $hotel,
            'roomType' => $roomType,
        ]);

        $this->assertEquals(2, $count);
    }

    public function testCountByPriceAdjustReasonIsNull(): void
    {
        $hotel = $this->createTestHotel();
        $roomType = $this->createTestRoomType($hotel);
        $inventory1 = $this->createTestDailyInventory($roomType, $hotel, new \DateTimeImmutable('2024-01-01'), 'NO-REASON-001');
        $inventory1->setPriceAdjustReason(null);
        $inventory2 = $this->createTestDailyInventory($roomType, $hotel, new \DateTimeImmutable('2024-01-02'), 'NO-REASON-002');
        $inventory2->setPriceAdjustReason(null);
        $inventory3 = $this->createTestDailyInventory($roomType, $hotel, new \DateTimeImmutable('2024-01-03'), 'WITH-REASON-001');
        $inventory3->setPriceAdjustReason('价格调整');

        $this->persistAndFlush($hotel);
        $this->persistAndFlush($roomType);
        $this->persistAndFlush($inventory1);
        $this->persistAndFlush($inventory2);
        $this->persistAndFlush($inventory3);

        // 添加更精确的查询条件以避免其他测试的干扰
        $count = $this->getRepository()->count([
            'priceAdjustReason' => null,
            'hotel' => $hotel,
            'roomType' => $roomType,
        ]);

        $this->assertEquals(2, $count);
    }

    public function testFindOneByAssociationRoomTypeShouldReturnMatchingEntity(): void
    {
        $hotel = $this->createTestHotel();
        $roomType = $this->createTestRoomType($hotel);
        $inventory = $this->createTestDailyInventory($roomType, $hotel, new \DateTimeImmutable('2024-01-01'), 'ASSOC-TEST-001');

        $this->persistAndFlush($hotel);
        $this->persistAndFlush($roomType);
        $this->persistAndFlush($inventory);

        $result = $this->getRepository()->findOneBy(['roomType' => $roomType]);

        $this->assertNotNull($result);
        $this->assertNotNull($result->getRoomType());
        $this->assertNotNull($roomType->getId());
        $this->assertEquals($roomType->getId(), $result->getRoomType()->getId());
        $this->assertEquals('ASSOC-TEST-001', $result->getCode());
    }

    public function testFindOneByAssociationHotelShouldReturnMatchingEntity(): void
    {
        $hotel = $this->createTestHotel();
        $roomType = $this->createTestRoomType($hotel);
        $inventory = $this->createTestDailyInventory($roomType, $hotel, new \DateTimeImmutable('2024-01-01'), 'HOTEL-ASSOC-001');

        $this->persistAndFlush($hotel);
        $this->persistAndFlush($roomType);
        $this->persistAndFlush($inventory);

        $result = $this->getRepository()->findOneBy(['hotel' => $hotel]);

        $this->assertNotNull($result);
        $this->assertNotNull($result->getHotel());
        $this->assertNotNull($hotel->getId());
        $this->assertEquals($hotel->getId(), $result->getHotel()->getId());
        $this->assertEquals('HOTEL-ASSOC-001', $result->getCode());
    }

    public function testFindOneByAssociationContractShouldReturnMatchingEntity(): void
    {
        $hotel = $this->createTestHotel();
        $roomType = $this->createTestRoomType($hotel);
        $contract = $this->createTestContract($hotel);
        $inventory = $this->createTestDailyInventory($roomType, $hotel, new \DateTimeImmutable('2024-01-01'), 'CONTRACT-ASSOC-001');
        $inventory->setContract($contract);

        $this->persistAndFlush($hotel);
        $this->persistAndFlush($roomType);
        $this->persistAndFlush($contract);
        $this->persistAndFlush($inventory);

        $result = $this->getRepository()->findOneBy(['contract' => $contract]);

        $this->assertNotNull($result);
        $this->assertNotNull($result->getContract());
        $this->assertNotNull($contract->getId());
        $this->assertEquals($contract->getId(), $result->getContract()->getId());
        $this->assertEquals('CONTRACT-ASSOC-001', $result->getCode());
    }

    public function testCountByAssociationRoomTypeShouldReturnCorrectNumber(): void
    {
        $hotel = $this->createTestHotel();
        $roomType = $this->createTestRoomType($hotel);
        $inventory1 = $this->createTestDailyInventory($roomType, $hotel, new \DateTimeImmutable('2024-01-01'), 'COUNT-ROOM-001');
        $inventory2 = $this->createTestDailyInventory($roomType, $hotel, new \DateTimeImmutable('2024-01-02'), 'COUNT-ROOM-002');

        $this->persistAndFlush($hotel);
        $this->persistAndFlush($roomType);
        $this->persistAndFlush($inventory1);
        $this->persistAndFlush($inventory2);

        $count = $this->getRepository()->count(['roomType' => $roomType]);

        $this->assertEquals(2, $count);
    }

    public function testCountByAssociationHotelShouldReturnCorrectNumber(): void
    {
        $hotel = $this->createTestHotel();
        $roomType = $this->createTestRoomType($hotel);
        $inventory1 = $this->createTestDailyInventory($roomType, $hotel, new \DateTimeImmutable('2024-01-01'), 'COUNT-HOTEL-001');
        $inventory2 = $this->createTestDailyInventory($roomType, $hotel, new \DateTimeImmutable('2024-01-02'), 'COUNT-HOTEL-002');

        $this->persistAndFlush($hotel);
        $this->persistAndFlush($roomType);
        $this->persistAndFlush($inventory1);
        $this->persistAndFlush($inventory2);

        $count = $this->getRepository()->count(['hotel' => $hotel]);

        $this->assertEquals(2, $count);
    }

    public function testCountByAssociationContractShouldReturnCorrectNumber(): void
    {
        $hotel = $this->createTestHotel();
        $roomType = $this->createTestRoomType($hotel);
        $contract = $this->createTestContract($hotel);
        $inventory1 = $this->createTestDailyInventory($roomType, $hotel, new \DateTimeImmutable('2024-01-01'), 'COUNT-CONTRACT-001');
        $inventory1->setContract($contract);
        $inventory2 = $this->createTestDailyInventory($roomType, $hotel, new \DateTimeImmutable('2024-01-02'), 'COUNT-CONTRACT-002');
        $inventory2->setContract($contract);

        $this->persistAndFlush($hotel);
        $this->persistAndFlush($roomType);
        $this->persistAndFlush($contract);
        $this->persistAndFlush($inventory1);
        $this->persistAndFlush($inventory2);

        $count = $this->getRepository()->count(['contract' => $contract]);

        $this->assertEquals(2, $count);
    }

    public function testFindAvailableByRoomTypeAndDateReturnsAvailableInventoriesOrderedByCostPrice(): void
    {
        // Arrange
        $hotel = $this->createTestHotel();
        $roomType = $this->createTestRoomType($hotel);
        self::getEntityManager()->persist($hotel);
        self::getEntityManager()->persist($roomType);
        self::getEntityManager()->flush(); // 确保ID分配

        $date = new \DateTimeImmutable('2024-01-01');

        // 创建三个库存：一个不可用，两个可用（价格不同）
        $unavailableInventory = $this->createTestDailyInventory($roomType, $hotel, $date, 'UNAVAILABLE-001');
        $unavailableInventory->setStatus(DailyInventoryStatusEnum::SOLD);
        $unavailableInventory->setCostPrice('150.00');

        $availableInventory1 = $this->createTestDailyInventory($roomType, $hotel, $date, 'AVAILABLE-001');
        $availableInventory1->setStatus(DailyInventoryStatusEnum::AVAILABLE);
        $availableInventory1->setCostPrice('200.00'); // 更高的成本价

        $availableInventory2 = $this->createTestDailyInventory($roomType, $hotel, $date, 'AVAILABLE-002');
        $availableInventory2->setStatus(DailyInventoryStatusEnum::AVAILABLE);
        $availableInventory2->setCostPrice('100.00'); // 更低的成本价

        self::getEntityManager()->persist($unavailableInventory);
        self::getEntityManager()->persist($availableInventory1);
        self::getEntityManager()->persist($availableInventory2);
        self::getEntityManager()->flush();

        // Act
        $this->assertNotNull($roomType->getId());
        $availableInventories = $this->getRepository()->findAvailableByRoomTypeAndDate($roomType->getId(), $date);

        // Assert
        $this->assertCount(2, $availableInventories, 'Should find 2 available inventories');

        // 验证只返回可用状态的库存
        foreach ($availableInventories as $inventory) {
            $this->assertEquals(DailyInventoryStatusEnum::AVAILABLE, $inventory->getStatus());
        }

        // 验证按成本价升序排列
        $this->assertEquals('AVAILABLE-002', $availableInventories[0]->getCode()); // 成本价100.00
        $this->assertEquals('AVAILABLE-001', $availableInventories[1]->getCode()); // 成本价200.00
        $this->assertEquals('100.00', $availableInventories[0]->getCostPrice());
        $this->assertEquals('200.00', $availableInventories[1]->getCostPrice());
    }

    public function testFindAvailableByRoomTypeAndDateWithNoAvailableInventoryReturnsEmptyArray(): void
    {
        // Arrange
        $hotel = $this->createTestHotel();
        $roomType = $this->createTestRoomType($hotel);
        self::getEntityManager()->persist($hotel);
        self::getEntityManager()->persist($roomType);

        $date = new \DateTimeImmutable('2024-01-01');

        // 创建一个已售出的库存
        $soldInventory = $this->createTestDailyInventory($roomType, $hotel, $date, 'SOLD-001');
        $soldInventory->setStatus(DailyInventoryStatusEnum::SOLD);

        self::getEntityManager()->persist($soldInventory);
        self::getEntityManager()->flush();

        // Act
        $this->assertNotNull($roomType->getId());
        $availableInventories = $this->getRepository()->findAvailableByRoomTypeAndDate($roomType->getId(), $date);

        // Assert
        $this->assertCount(0, $availableInventories);
    }

    public function testFindAvailableByRoomTypeAndDateWithNonExistentRoomTypeReturnsEmptyArray(): void
    {
        // Arrange
        $date = new \DateTimeImmutable('2024-01-01');
        $nonExistentRoomTypeId = 999999;

        // Act
        $availableInventories = $this->getRepository()->findAvailableByRoomTypeAndDate($nonExistentRoomTypeId, $date);

        // Assert
        $this->assertCount(0, $availableInventories);
    }
}
