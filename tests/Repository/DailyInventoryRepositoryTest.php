<?php

namespace Tourze\HotelContractBundle\Tests\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\KernelInterface;
use Tourze\HotelContractBundle\Entity\DailyInventory;
use Tourze\HotelContractBundle\Entity\HotelContract;
use Tourze\HotelContractBundle\Enum\ContractStatusEnum;
use Tourze\HotelContractBundle\Enum\ContractTypeEnum;
use Tourze\HotelContractBundle\Enum\DailyInventoryStatusEnum;
use Tourze\HotelContractBundle\HotelContractBundle;
use Tourze\HotelContractBundle\Repository\DailyInventoryRepository;
use Tourze\HotelProfileBundle\Entity\Hotel;
use Tourze\HotelProfileBundle\Entity\RoomType;
use Tourze\HotelProfileBundle\Enum\HotelStatusEnum;
use Tourze\HotelProfileBundle\Enum\RoomTypeStatusEnum;
use Tourze\HotelProfileBundle\HotelProfileBundle;
use Tourze\IntegrationTestKernel\IntegrationTestKernel;

class DailyInventoryRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private DailyInventoryRepository $repository;

    protected static function createKernel(array $options = []): KernelInterface
    {
        $env = $options['environment'] ?? $_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? 'test';
        $debug = $options['debug'] ?? $_ENV['APP_DEBUG'] ?? $_SERVER['APP_DEBUG'] ?? true;

        return new IntegrationTestKernel($env, $debug, [
            HotelContractBundle::class => ['all' => true],
            HotelProfileBundle::class => ['all' => true],
        ]);
    }

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        /** @var DailyInventoryRepository $repository */
        $repository = static::getContainer()->get(DailyInventoryRepository::class);
        $this->repository = $repository;
        $this->cleanDatabase();
    }

    protected function tearDown(): void
    {
        $this->cleanDatabase();
        self::ensureKernelShutdown();
        parent::tearDown();
    }

    private function cleanDatabase(): void
    {
        $connection = $this->entityManager->getConnection();
        $connection->executeStatement('DELETE FROM daily_inventory');
        $connection->executeStatement('DELETE FROM hotel_contract');
        $connection->executeStatement('DELETE FROM ims_hotel_room_type');
        $connection->executeStatement('DELETE FROM ims_hotel_profile');
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
        string $code = 'INV-001'
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

    public function test_save_withValidInventory_persistsToDatabase(): void
    {
        // Arrange
        $hotel = $this->createTestHotel();
        $roomType = $this->createTestRoomType($hotel);
        $this->entityManager->persist($hotel);
        $this->entityManager->persist($roomType);
        $this->entityManager->flush();

        $inventory = $this->createTestDailyInventory($roomType, $hotel, new \DateTimeImmutable('2024-01-01'));

        // Act
        $this->repository->save($inventory, true);

        // Assert
        $this->assertNotNull($inventory->getId());
        $this->entityManager->refresh($inventory);
        $this->assertEquals('INV-001', $inventory->getCode());
        $this->assertEquals($roomType->getId(), $inventory->getRoomType()->getId());
        $this->assertEquals($hotel->getId(), $inventory->getHotel()->getId());
    }

    public function test_save_withoutFlush_doesNotPersistImmediately(): void
    {
        // Arrange
        $hotel = $this->createTestHotel();
        $roomType = $this->createTestRoomType($hotel);
        $this->entityManager->persist($hotel);
        $this->entityManager->persist($roomType);
        $this->entityManager->flush();

        $inventory = $this->createTestDailyInventory($roomType, $hotel, new \DateTimeImmutable('2024-01-01'));

        // Act
        $this->repository->save($inventory, false);

        // Assert
        $this->assertNull($inventory->getId());

        // Flush and verify
        $this->entityManager->flush();
        $this->assertNotNull($inventory->getId());
    }

    public function test_remove_withValidInventory_deletesFromDatabase(): void
    {
        // Arrange
        $hotel = $this->createTestHotel();
        $roomType = $this->createTestRoomType($hotel);
        $inventory = $this->createTestDailyInventory($roomType, $hotel, new \DateTimeImmutable('2024-01-01'));
        $this->entityManager->persist($hotel);
        $this->entityManager->persist($roomType);
        $this->entityManager->persist($inventory);
        $this->entityManager->flush();

        $inventoryId = $inventory->getId();

        // Act
        $this->repository->remove($inventory, true);

        // Assert
        $deletedInventory = $this->repository->find($inventoryId);
        $this->assertNull($deletedInventory);
    }

    public function test_findByRoomTypeAndDate_withExistingInventory_returnsInventory(): void
    {
        // Arrange
        $hotel = $this->createTestHotel();
        $roomType = $this->createTestRoomType($hotel);
        $date = new \DateTimeImmutable('2024-01-01');
        $inventory = $this->createTestDailyInventory($roomType, $hotel, $date);

        $this->entityManager->persist($hotel);
        $this->entityManager->persist($roomType);
        $this->entityManager->persist($inventory);
        $this->entityManager->flush();

        // Act
        $foundInventory = $this->repository->findByRoomTypeAndDate($roomType->getId(), $date);

        // Debug: 检查数据是否保存
        $allInventories = $this->repository->findAll();
        $this->assertGreaterThan(0, count($allInventories), 'No inventories found in database');

        if (count($allInventories) > 0) {
            $firstInventory = $allInventories[0];
            $this->assertEquals($roomType->getId(), $firstInventory->getRoomType()->getId(), 'RoomType ID mismatch');
            $this->assertEquals($date->format('Y-m-d'), $firstInventory->getDate()->format('Y-m-d'), 'Date mismatch');
        }

        // Assert
        $this->assertNotNull($foundInventory);
        $this->assertEquals($inventory->getId(), $foundInventory->getId());
        $this->assertEquals($roomType->getId(), $foundInventory->getRoomType()->getId());
        $this->assertEquals($date->format('Y-m-d'), $foundInventory->getDate()->format('Y-m-d'));
    }

    public function test_findByRoomTypeAndDate_withNonExistentInventory_returnsNull(): void
    {
        // Arrange
        $hotel = $this->createTestHotel();
        $roomType = $this->createTestRoomType($hotel);
        $this->entityManager->persist($hotel);
        $this->entityManager->persist($roomType);
        $this->entityManager->flush();

        // Act
        $foundInventory = $this->repository->findByRoomTypeAndDate($roomType->getId(), new \DateTimeImmutable('2024-01-01'));

        // Assert
        $this->assertNull($foundInventory);
    }

    public function test_findAvailableByDateRange_returnsOnlyAvailableInventories(): void
    {
        // Arrange
        $hotel = $this->createTestHotel();
        $roomType = $this->createTestRoomType($hotel);
        $this->entityManager->persist($hotel);
        $this->entityManager->persist($roomType);

        $availableInventory = $this->createTestDailyInventory($roomType, $hotel, new \DateTimeImmutable('2024-01-01'), 'AVAILABLE-001');
        $availableInventory->setStatus(DailyInventoryStatusEnum::AVAILABLE);

        $soldOutInventory = $this->createTestDailyInventory($roomType, $hotel, new \DateTimeImmutable('2024-01-02'), 'SOLDOUT-001');
        $soldOutInventory->setStatus(DailyInventoryStatusEnum::SOLD);

        $this->entityManager->persist($availableInventory);
        $this->entityManager->persist($soldOutInventory);
        $this->entityManager->flush();

        // Act
        $startDate = new \DateTimeImmutable('2024-01-01');
        $endDate = new \DateTimeImmutable('2024-01-02');
        $availableInventories = $this->repository->findAvailableByDateRange($startDate, $endDate);

        // Assert
        $this->assertCount(1, $availableInventories);
        $this->assertEquals('AVAILABLE-001', $availableInventories[0]->getCode());
        $this->assertEquals(DailyInventoryStatusEnum::AVAILABLE, $availableInventories[0]->getStatus());
    }

    public function test_findByContractId_withExistingContract_returnsInventories(): void
    {
        // Arrange
        $hotel = $this->createTestHotel();
        $roomType = $this->createTestRoomType($hotel);
        $contract = $this->createTestContract($hotel);
        $this->entityManager->persist($hotel);
        $this->entityManager->persist($roomType);
        $this->entityManager->persist($contract);

        $inventory1 = $this->createTestDailyInventory($roomType, $hotel, new \DateTimeImmutable('2024-01-01'), 'INV-001');
        $inventory1->setContract($contract);
        $inventory2 = $this->createTestDailyInventory($roomType, $hotel, new \DateTimeImmutable('2024-01-02'), 'INV-002');
        $inventory2->setContract($contract);

        $this->entityManager->persist($inventory1);
        $this->entityManager->persist($inventory2);
        $this->entityManager->flush();

        // Act
        $inventories = $this->repository->findByContractId($contract->getId());

        // Assert
        $this->assertCount(2, $inventories);
        // 按日期升序排列
        $this->assertEquals('INV-001', $inventories[0]->getCode());
        $this->assertEquals('INV-002', $inventories[1]->getCode());
    }

    public function test_findByDate_withSpecificDate_returnsAllInventoriesForDate(): void
    {
        // Arrange
        $hotel = $this->createTestHotel();
        $roomType1 = $this->createTestRoomType($hotel, '标准间');
        $roomType2 = $this->createTestRoomType($hotel, '豪华间');
        $this->entityManager->persist($hotel);
        $this->entityManager->persist($roomType1);
        $this->entityManager->persist($roomType2);

        $date = new \DateTimeImmutable('2024-01-01');
        $inventory1 = $this->createTestDailyInventory($roomType1, $hotel, $date, 'INV-001');
        $inventory2 = $this->createTestDailyInventory($roomType2, $hotel, $date, 'INV-002');
        $inventory3 = $this->createTestDailyInventory($roomType1, $hotel, new \DateTimeImmutable('2024-01-02'), 'INV-003');

        $this->entityManager->persist($inventory1);
        $this->entityManager->persist($inventory2);
        $this->entityManager->persist($inventory3);
        $this->entityManager->flush();

        // Act
        $inventories = $this->repository->findByDate($date);

        // Assert
        $this->assertCount(2, $inventories);
        $codes = array_map(fn($inv) => $inv->getCode(), $inventories);
        $this->assertContains('INV-001', $codes);
        $this->assertContains('INV-002', $codes);
        $this->assertNotContains('INV-003', $codes);
    }

    public function test_findByRoomTypeId_returnsInventoriesOrderedByDate(): void
    {
        // Arrange
        $hotel = $this->createTestHotel();
        $roomType = $this->createTestRoomType($hotel);
        $this->entityManager->persist($hotel);
        $this->entityManager->persist($roomType);

        $inventory1 = $this->createTestDailyInventory($roomType, $hotel, new \DateTimeImmutable('2024-01-02'), 'INV-002');
        $inventory2 = $this->createTestDailyInventory($roomType, $hotel, new \DateTimeImmutable('2024-01-01'), 'INV-001');
        $inventory3 = $this->createTestDailyInventory($roomType, $hotel, new \DateTimeImmutable('2024-01-03'), 'INV-003');

        $this->entityManager->persist($inventory1);
        $this->entityManager->persist($inventory2);
        $this->entityManager->persist($inventory3);
        $this->entityManager->flush();

        // Act
        $inventories = $this->repository->findByRoomTypeId($roomType->getId());

        // Assert
        $this->assertCount(3, $inventories);
        // 按日期升序排列
        $this->assertEquals('INV-001', $inventories[0]->getCode());
        $this->assertEquals('INV-002', $inventories[1]->getCode());
        $this->assertEquals('INV-003', $inventories[2]->getCode());
    }

    public function test_findByStatus_returnsInventoriesWithSpecificStatus(): void
    {
        // Arrange
        $hotel = $this->createTestHotel();
        $roomType = $this->createTestRoomType($hotel);
        $this->entityManager->persist($hotel);
        $this->entityManager->persist($roomType);

        $availableInventory = $this->createTestDailyInventory($roomType, $hotel, new \DateTimeImmutable('2024-01-01'), 'AVAILABLE-001');
        $availableInventory->setStatus(DailyInventoryStatusEnum::AVAILABLE);

        $soldOutInventory = $this->createTestDailyInventory($roomType, $hotel, new \DateTimeImmutable('2024-01-02'), 'SOLDOUT-001');
        $soldOutInventory->setStatus(DailyInventoryStatusEnum::SOLD);

        $reservedInventory = $this->createTestDailyInventory($roomType, $hotel, new \DateTimeImmutable('2024-01-03'), 'RESERVED-001');
        $reservedInventory->setStatus(DailyInventoryStatusEnum::RESERVED);

        $this->entityManager->persist($availableInventory);
        $this->entityManager->persist($soldOutInventory);
        $this->entityManager->persist($reservedInventory);
        $this->entityManager->flush();

        // Act
        $availableInventories = $this->repository->findByStatus(DailyInventoryStatusEnum::AVAILABLE);
        $soldInventories = $this->repository->findByStatus(DailyInventoryStatusEnum::SOLD);

        // Assert
        $this->assertCount(1, $availableInventories);
        $this->assertEquals('AVAILABLE-001', $availableInventories[0]->getCode());

        $this->assertCount(1, $soldInventories);
        $this->assertEquals('SOLDOUT-001', $soldInventories[0]->getCode());
    }

    public function test_findDistinctRoomTypesByContract_returnsUniqueRoomTypeIds(): void
    {
        // Arrange
        $hotel = $this->createTestHotel();
        $roomType1 = $this->createTestRoomType($hotel, '标准间');
        $roomType2 = $this->createTestRoomType($hotel, '豪华间');
        $contract = $this->createTestContract($hotel);
        $this->entityManager->persist($hotel);
        $this->entityManager->persist($roomType1);
        $this->entityManager->persist($roomType2);
        $this->entityManager->persist($contract);

        // 创建多个库存，但只有两种房型
        $inventory1 = $this->createTestDailyInventory($roomType1, $hotel, new \DateTimeImmutable('2024-01-01'), 'INV-001');
        $inventory1->setContract($contract);
        $inventory2 = $this->createTestDailyInventory($roomType1, $hotel, new \DateTimeImmutable('2024-01-02'), 'INV-002');
        $inventory2->setContract($contract);
        $inventory3 = $this->createTestDailyInventory($roomType2, $hotel, new \DateTimeImmutable('2024-01-01'), 'INV-003');
        $inventory3->setContract($contract);

        $this->entityManager->persist($inventory1);
        $this->entityManager->persist($inventory2);
        $this->entityManager->persist($inventory3);
        $this->entityManager->flush();

        // Act
        $roomTypeIds = $this->repository->findDistinctRoomTypesByContract($contract->getId());

        // Assert
        $this->assertCount(2, $roomTypeIds);
        $this->assertContains($roomType1->getId(), $roomTypeIds);
        $this->assertContains($roomType2->getId(), $roomTypeIds);
    }

    public function test_findPriceDataByContractAndDateRange_returnsFormattedPriceData(): void
    {
        // Arrange
        $hotel = $this->createTestHotel();
        $roomType = $this->createTestRoomType($hotel, '标准间');
        $contract = $this->createTestContract($hotel);
        $this->entityManager->persist($hotel);
        $this->entityManager->persist($roomType);
        $this->entityManager->persist($contract);

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

        $this->entityManager->persist($inventory1);
        $this->entityManager->persist($inventory2);
        $this->entityManager->persist($inventory3);
        $this->entityManager->flush();

        // Act
        $startDate = new \DateTimeImmutable('2024-01-01');
        $endDate = new \DateTimeImmutable('2024-01-31');
        $priceData = $this->repository->findPriceDataByContractAndDateRange(
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
}
