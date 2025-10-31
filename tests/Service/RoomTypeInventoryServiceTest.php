<?php

declare(strict_types=1);

namespace Tourze\HotelContractBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\HotelContractBundle\Entity\DailyInventory;
use Tourze\HotelContractBundle\Entity\HotelContract;
use Tourze\HotelContractBundle\Enum\DailyInventoryStatusEnum;
use Tourze\HotelContractBundle\Service\RoomTypeInventoryService;
use Tourze\HotelProfileBundle\Entity\Hotel;
use Tourze\HotelProfileBundle\Entity\RoomType;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(RoomTypeInventoryService::class)]
#[RunTestsInSeparateProcesses]
final class RoomTypeInventoryServiceTest extends AbstractIntegrationTestCase
{
    protected function onSetUp(): void
    {
        // 设置邮件环境变量
        putenv('MAILER_DSN=smtp://localhost:1025');
    }

    private function getRoomTypeInventoryService(): RoomTypeInventoryService
    {
        return self::getService(RoomTypeInventoryService::class);
    }

    public function testBatchCreateInventoriesWithValidData(): void
    {
        // 创建真实的实体
        $hotel = new Hotel();
        $hotel->setName('Test Hotel');

        $roomType = new RoomType();
        $roomType->setName('Test Room Type');
        $roomType->setArea(30.0);
        $roomType->setBedType('大床');
        $roomType->setMaxGuests(2);
        $roomType->setBreakfastCount(0);

        $contract = new HotelContract();
        $contract->setContractNo('TEST-001');
        $contract->setStartDate(new \DateTimeImmutable('2024-01-01'));
        $contract->setEndDate(new \DateTimeImmutable('2024-01-31'));

        // 先持久化并flush hotel
        self::getEntityManager()->persist($hotel);
        self::getEntityManager()->flush();

        // 然后设置关联关系
        $roomType->setHotel($hotel);
        $contract->setHotel($hotel);

        self::getEntityManager()->persist($roomType);
        self::getEntityManager()->persist($contract);
        self::getEntityManager()->flush();

        // 刷新实体以确保关联关系正确加载
        self::getEntityManager()->refresh($hotel);
        self::getEntityManager()->refresh($roomType);
        self::getEntityManager()->refresh($contract);

        $startDate = new \DateTimeImmutable('2024-01-01');
        $endDate = new \DateTimeImmutable('2024-01-01');
        $count = 10;
        $costPrice = 100.0;
        $sellingPrice = 150.0;

        // 执行测试 - 主要验证方法不抛出异常
        $result = $this->getRoomTypeInventoryService()->batchCreateInventories(
            $roomType,
            $startDate,
            $endDate,
            $contract,
            $count,
            $costPrice,
            $sellingPrice
        );

        // 验证结果包含预期的键和值
        $this->assertArrayHasKey('created', $result);
        $this->assertGreaterThan(0, $result['created']);
    }

    public function testCreateInventories(): void
    {
        // 创建并持久化真实的实体以避免 Doctrine 关联持久化问题
        $hotel = new Hotel();
        $hotel->setName('Test Hotel');
        self::getEntityManager()->persist($hotel);

        $roomType = new RoomType();
        $roomType->setName('Test Room Type');
        $roomType->setHotel($hotel);
        $roomType->setArea(30.0);
        $roomType->setBedType('大床');
        $roomType->setMaxGuests(2);
        $roomType->setBreakfastCount(0);
        self::getEntityManager()->persist($roomType);

        $contract = new HotelContract();
        $contract->setContractNo('TEST-001');
        $contract->setHotel($hotel);
        $contract->setStartDate(new \DateTimeImmutable('2024-01-01'));
        $contract->setEndDate(new \DateTimeImmutable('2024-01-31'));
        self::getEntityManager()->persist($contract);

        self::getEntityManager()->flush();

        // 刷新实体以确保关联关系正确加载
        self::getEntityManager()->refresh($hotel);
        self::getEntityManager()->refresh($roomType);
        self::getEntityManager()->refresh($contract);

        $date = new \DateTimeImmutable('2024-01-01');
        $count = 2;
        $costPrice = 100.00;
        $sellingPrice = 120.00;

        // 执行测试 - 主要验证方法不抛出异常
        $result = $this->getRoomTypeInventoryService()->createInventories($roomType, $date, $contract, $count, $costPrice, $sellingPrice);

        // 验证结果包含预期数量的DailyInventory实例
        $this->assertCount($count, $result);
        $this->assertContainsOnlyInstancesOf(DailyInventory::class, $result);
    }

    public function testFindAvailableInventories(): void
    {
        // 创建真实的实体
        $hotel = new Hotel();
        $hotel->setName('Test Hotel');
        self::getEntityManager()->persist($hotel);

        $roomType = new RoomType();
        $roomType->setName('Test Room Type');
        $roomType->setHotel($hotel);
        $roomType->setArea(30.0);
        $roomType->setBedType('大床');
        $roomType->setMaxGuests(2);
        $roomType->setBreakfastCount(0);
        self::getEntityManager()->persist($roomType);

        self::getEntityManager()->flush();

        // 刷新实体以确保关联关系正确加载
        self::getEntityManager()->refresh($hotel);
        self::getEntityManager()->refresh($roomType);

        $date = new \DateTimeImmutable('2024-01-01');
        $count = 10;

        // 执行测试 - 主要验证方法不抛出异常
        $result = $this->getRoomTypeInventoryService()->findAvailableInventories($roomType, $date, $count);

        // 验证结果是数组且包含DailyInventory实例或为空
        $this->assertIsArray($result); // @phpstan-ignore method.alreadyNarrowedType (保留测试意图明确性)
        if (count($result) > 0) {
            $this->assertContainsOnlyInstancesOf(DailyInventory::class, $result);
        } else {
            $this->assertCount(0, $result, '没有找到可用的库存');
        }
    }

    public function testFindInventoryById(): void
    {
        // 测试不存在的库存ID
        $inventoryId = 999999;

        // 执行测试 - 查找不存在的库存应该返回null
        $result = $this->getRoomTypeInventoryService()->findInventoryById($inventoryId);
        $this->assertNull($result);
    }

    public function testOneClickGenerateRoomTypeInventory(): void
    {
        // 创建真实的实体以避免关联问题
        $hotel = new Hotel();
        $hotel->setName('Test Hotel');
        self::getEntityManager()->persist($hotel);

        $roomType = new RoomType();
        $roomType->setName('Test Room Type');
        $roomType->setHotel($hotel);
        $roomType->setArea(30.0);
        $roomType->setBedType('大床');
        $roomType->setMaxGuests(2);
        $roomType->setBreakfastCount(0);
        self::getEntityManager()->persist($roomType);

        $startDate = new \DateTimeImmutable('2024-01-01');
        $endDate = new \DateTimeImmutable('2024-01-01'); // 单天测试避免创建过多数据
        $contract = new HotelContract();
        $contract->setContractNo('TEST-001');
        $contract->setHotel($hotel);
        $contract->setStartDate($startDate);
        $contract->setEndDate($endDate);
        self::getEntityManager()->persist($contract);

        self::getEntityManager()->flush();

        // 刷新实体以确保关联关系正确加载
        self::getEntityManager()->refresh($hotel);
        self::getEntityManager()->refresh($roomType);
        self::getEntityManager()->refresh($contract);

        $count = 2;
        $costPrice = 100.00;
        $sellingPrice = 120.00;

        // 执行测试 - 使用实际的ID进行测试
        $result = $this->getRoomTypeInventoryService()->oneClickGenerateRoomTypeInventory(
            (int) $contract->getId(),
            (int) $roomType->getId(),
            $count,
            $startDate,
            $endDate,
            $costPrice,
            $sellingPrice
        );

        // 验证结果包含预期的键
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('message', $result);
        $this->assertArrayHasKey('created', $result);
        $this->assertArrayHasKey('details', $result);
        $this->assertTrue($result['success']);
        $this->assertEquals($count, $result['created']);
    }

    public function testReleaseInventory(): void
    {
        // 清理数据库确保测试隔离
        self::getEntityManager()->createQuery('DELETE FROM ' . DailyInventory::class)->execute();

        // 创建真实的实体以避免 Doctrine 映射错误
        $hotel = new Hotel();
        $hotel->setName('Test Hotel For Release');
        self::getEntityManager()->persist($hotel);

        $roomType = new RoomType();
        $roomType->setName('Test Room Type For Release');
        $roomType->setHotel($hotel);
        self::getEntityManager()->persist($roomType);

        $contract = new HotelContract();
        $contract->setContractNo('TEST-RELEASE-001');
        $contract->setHotel($hotel);
        $contract->setStartDate(new \DateTimeImmutable('2024-01-01'));
        $contract->setEndDate(new \DateTimeImmutable('2024-01-31'));
        self::getEntityManager()->persist($contract);

        $inventory = new DailyInventory();
        $inventory->setCode('TEST-INV-RELEASE-' . uniqid());
        $inventory->setRoomType($roomType);
        $inventory->setHotel($hotel);
        $inventory->setDate(new \DateTimeImmutable('2024-01-01'));
        $inventory->setContract($contract);
        $inventory->setStatus(DailyInventoryStatusEnum::RESERVED);
        self::getEntityManager()->persist($inventory);

        self::getEntityManager()->flush();

        // 刷新实体以确保关联关系正确加载
        self::getEntityManager()->refresh($hotel);
        self::getEntityManager()->refresh($roomType);
        self::getEntityManager()->refresh($contract);
        self::getEntityManager()->refresh($inventory);

        $this->getRoomTypeInventoryService()->releaseInventory($inventory);

        // 验证状态被正确设置为可用
        $this->assertEquals(DailyInventoryStatusEnum::AVAILABLE, $inventory->getStatus());
    }

    public function testReserveInventories(): void
    {
        // 创建真实的实体
        $hotel = new Hotel();
        $hotel->setName('Test Hotel For Reserve');
        self::getEntityManager()->persist($hotel);

        $roomType = new RoomType();
        $roomType->setName('Test Room Type For Reserve');
        $roomType->setHotel($hotel);
        self::getEntityManager()->persist($roomType);

        $contract = new HotelContract();
        $contract->setContractNo('TEST-RESERVE-001');
        $contract->setHotel($hotel);
        $contract->setStartDate(new \DateTimeImmutable('2024-01-01'));
        $contract->setEndDate(new \DateTimeImmutable('2024-01-31'));
        self::getEntityManager()->persist($contract);

        $date = new \DateTimeImmutable('2024-01-01');
        $endDate = new \DateTimeImmutable('2024-01-02');
        $count = 2;

        // 为每天创建可用的库存
        $currentDate = $date;
        while ($currentDate <= $endDate) {
            $inventoryDate = $currentDate; // 使用独立的变量避免修改问题
            for ($i = 0; $i < $count; ++$i) {
                $inventory = new DailyInventory();
                $inventory->setCode('TEST-INV-RESERVE-' . $inventoryDate->format('Y-m-d') . '-' . $i);
                $inventory->setRoomType($roomType);
                $inventory->setHotel($hotel);
                $inventory->setDate($inventoryDate);
                $inventory->setContract($contract);
                $inventory->setStatus(DailyInventoryStatusEnum::AVAILABLE);
                self::getEntityManager()->persist($inventory);
            }
            $currentDate = $currentDate->modify('+1 day');
        }

        self::getEntityManager()->flush();

        // 刷新实体以确保关联关系正确加载
        self::getEntityManager()->refresh($hotel);
        self::getEntityManager()->refresh($roomType);
        self::getEntityManager()->refresh($contract);

        // 执行测试 - 预定库存
        $result = $this->getRoomTypeInventoryService()->reserveInventories($roomType, $date, $endDate, $count);

        // 验证结果包含预期数量的库存
        $this->assertCount($count * 2, $result); // 两天 × 每天数量

        // 验证所有返回的库存都是 DailyInventory 实例
        foreach ($result as $inventory) {
            $this->assertInstanceOf(DailyInventory::class, $inventory);
            $this->assertEquals(DailyInventoryStatusEnum::RESERVED, $inventory->getStatus());
        }
    }

    public function testValidateAndReserveInventoryById(): void
    {
        $dateStr = '2024-01-01';

        // 清理数据库确保测试隔离
        self::getEntityManager()->createQuery('DELETE FROM ' . DailyInventory::class)->execute();

        // 创建真实的实体以避免 Doctrine 映射错误
        $hotel = new Hotel();
        $hotel->setName('Test Hotel For Validate');
        self::getEntityManager()->persist($hotel);

        $roomType = new RoomType();
        $roomType->setName('Test Room Type For Validate');
        $roomType->setHotel($hotel);
        self::getEntityManager()->persist($roomType);

        $contract = new HotelContract();
        $contract->setContractNo('TEST-VALIDATE-001');
        $contract->setHotel($hotel);
        $contract->setStartDate(new \DateTimeImmutable('2024-01-01'));
        $contract->setEndDate(new \DateTimeImmutable('2024-01-31'));
        self::getEntityManager()->persist($contract);

        $inventory = new DailyInventory();
        $inventory->setCode('TEST-INV-VALIDATE-' . uniqid());
        $inventory->setRoomType($roomType);
        $inventory->setHotel($hotel);
        $inventory->setDate(new \DateTimeImmutable($dateStr));
        $inventory->setContract($contract);
        $inventory->setStatus(DailyInventoryStatusEnum::AVAILABLE);
        self::getEntityManager()->persist($inventory);

        self::getEntityManager()->flush();

        // 刷新实体以确保关联关系正确加载
        self::getEntityManager()->refresh($hotel);
        self::getEntityManager()->refresh($roomType);
        self::getEntityManager()->refresh($contract);
        self::getEntityManager()->refresh($inventory);

        // 执行测试 - 使用实际的库存ID
        $result = $this->getRoomTypeInventoryService()->validateAndReserveInventoryById((int) $inventory->getId(), $roomType, $dateStr);

        // 验证结果
        $this->assertInstanceOf(DailyInventory::class, $result);
        $this->assertEquals(DailyInventoryStatusEnum::PENDING, $result->getStatus());
    }
}
