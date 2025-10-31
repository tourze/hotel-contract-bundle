<?php

declare(strict_types=1);

namespace Tourze\HotelContractBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\HotelContractBundle\Entity\DailyInventory;
use Tourze\HotelContractBundle\Enum\DailyInventoryStatusEnum;
use Tourze\HotelContractBundle\Repository\InventorySummaryRepository;
use Tourze\HotelContractBundle\Service\InventorySummaryService;
use Tourze\HotelProfileBundle\Entity\Hotel;
use Tourze\HotelProfileBundle\Entity\RoomType;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * InventorySummaryService 集成测试
 *
 * @internal
 */
#[CoversClass(InventorySummaryService::class)]
#[RunTestsInSeparateProcesses]
final class InventorySummaryServiceTest extends AbstractIntegrationTestCase
{
    protected function onSetUp(): void
    {
        // Setup for service tests
    }

    private function getInventorySummaryService(): InventorySummaryService
    {
        return self::getService(InventorySummaryService::class);
    }

    private function getInventorySummaryRepository(): InventorySummaryRepository
    {
        return self::getService(InventorySummaryRepository::class);
    }

    /**
     * 测试同步库存汇总 - 成功场景
     */
    public function testSyncInventorySummarySuccess(): void
    {
        // 准备测试数据
        $hotel = new Hotel();
        $hotel->setName('测试酒店');
        $this->persistAndFlush($hotel);

        $roomType = new RoomType();
        $roomType->setName('标准间');
        $roomType->setHotel($hotel);
        $this->persistAndFlush($roomType);

        // 创建一些库存数据
        for ($i = 0; $i < 5; ++$i) {
            $inventory = new DailyInventory();
            $inventory->setCode('INV-' . ($i + 1));
            $inventory->setDate(new \DateTimeImmutable('2024-01-0' . ($i + 1)));
            $inventory->setHotel($hotel);
            $inventory->setRoomType($roomType);
            $inventory->setCostPrice('180.00');
            $inventory->setSellingPrice('200.00');
            $inventory->setProfitRate('11.11');
            $inventory->setStatus(DailyInventoryStatusEnum::AVAILABLE);
            $this->persistAndFlush($inventory);
        }

        $testDate = new \DateTimeImmutable('2024-01-01');

        // 执行测试
        $result = $this->getInventorySummaryService()->syncInventorySummary($testDate);

        // 验证结果包含预期的键
        $this->assertArrayHasKey('processed_count', $result);
        $this->assertArrayHasKey('summary_count', $result);
    }

    /**
     * 测试更新库存汇总 - 成功场景
     */
    public function testUpdateInventorySummarySuccess(): void
    {
        // 准备测试数据
        $hotel = new Hotel();
        $hotel->setName('测试酒店');
        $this->persistAndFlush($hotel);

        $roomType = new RoomType();
        $roomType->setName('标准间');
        $roomType->setHotel($hotel);
        $this->persistAndFlush($roomType);

        // 创建一些库存数据
        for ($i = 0; $i < 3; ++$i) {
            $inventory = new DailyInventory();
            $inventory->setCode('INV-' . ($i + 1));
            $inventory->setDate(new \DateTimeImmutable('2024-01-0' . ($i + 1)));
            $inventory->setHotel($hotel);
            $inventory->setRoomType($roomType);
            $inventory->setCostPrice('180.00');
            $inventory->setSellingPrice('200.00');
            $inventory->setProfitRate('11.11');
            $inventory->setStatus(DailyInventoryStatusEnum::AVAILABLE);
            $this->persistAndFlush($inventory);
        }

        $startDate = new \DateTimeImmutable('2024-01-01');
        $endDate = new \DateTimeImmutable('2024-01-03');

        // 执行测试
        $this->getInventorySummaryService()->updateInventorySummary($hotel, $roomType, $startDate, $endDate);

        // 验证库存汇总数据被创建或更新
        $hotelId = $hotel->getId();
        $roomTypeId = $roomType->getId();
        $this->assertNotNull($hotelId);
        $this->assertNotNull($roomTypeId);
        $summary = $this->getInventorySummaryRepository()->findByHotelRoomTypeAndDate(
            $hotelId,
            $roomTypeId,
            $startDate
        );
        $this->assertNotNull($summary);
    }

    /**
     * 测试更新日库存汇总 - 成功场景
     */
    public function testUpdateDailyInventorySummarySuccess(): void
    {
        // 准备测试数据
        $hotel = new Hotel();
        $hotel->setName('测试酒店');
        $this->persistAndFlush($hotel);

        $roomType = new RoomType();
        $roomType->setName('标准间');
        $roomType->setHotel($hotel);
        $this->persistAndFlush($roomType);

        $inventory = new DailyInventory();
        $inventory->setCode('INV-001');
        $inventory->setDate(new \DateTimeImmutable('2024-01-01'));
        $inventory->setHotel($hotel);
        $inventory->setRoomType($roomType);
        $inventory->setCostPrice('180.00');
        $inventory->setSellingPrice('200.00');
        $inventory->setProfitRate('11.11');
        $inventory->setStatus(DailyInventoryStatusEnum::AVAILABLE);
        $this->persistAndFlush($inventory);

        $testDate = new \DateTimeImmutable('2024-01-01');

        // 执行测试
        $this->getInventorySummaryService()->updateDailyInventorySummary($hotel, $roomType, $testDate);

        // 验证库存汇总数据被创建或更新
        $hotelId = $hotel->getId();
        $roomTypeId = $roomType->getId();
        $this->assertNotNull($hotelId);
        $this->assertNotNull($roomTypeId);
        $summary = $this->getInventorySummaryRepository()->findByHotelRoomTypeAndDate(
            $hotelId,
            $roomTypeId,
            $testDate
        );
        $this->assertNotNull($summary);
    }

    /**
     * 测试更新库存汇总状态 - 成功场景
     */
    public function testUpdateInventorySummaryStatusSuccess(): void
    {
        // 准备测试数据
        $hotel = new Hotel();
        $hotel->setName('测试酒店');
        $this->persistAndFlush($hotel);

        $roomType = new RoomType();
        $roomType->setName('标准间');
        $roomType->setHotel($hotel);
        $this->persistAndFlush($roomType);

        // 创建一些库存数据
        for ($i = 0; $i < 5; ++$i) {
            $inventory = new DailyInventory();
            $inventory->setCode('INV-' . ($i + 1));
            $inventory->setDate(new \DateTimeImmutable('2024-01-0' . ($i + 1)));
            $inventory->setHotel($hotel);
            $inventory->setRoomType($roomType);
            $inventory->setCostPrice('180.00');
            $inventory->setSellingPrice('200.00');
            $inventory->setProfitRate('11.11');
            $inventory->setStatus(DailyInventoryStatusEnum::AVAILABLE);
            $this->persistAndFlush($inventory);
        }

        $warningThreshold = 10;

        // 执行测试
        $result = $this->getInventorySummaryService()->updateInventorySummaryStatus($warningThreshold);

        // 验证结果包含预期的键
        $this->assertArrayHasKey('processed_count', $result);
        $this->assertArrayHasKey('warning_count', $result);
    }
}
