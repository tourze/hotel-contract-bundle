<?php

declare(strict_types=1);

namespace Tourze\HotelContractBundle\Tests\Service;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tourze\HotelContractBundle\Entity\DailyInventory;
use Tourze\HotelContractBundle\Enum\DailyInventoryStatusEnum;
use Tourze\HotelContractBundle\Repository\DailyInventoryRepository;
use Tourze\HotelContractBundle\Service\InventoryUpdateService;
use Tourze\HotelProfileBundle\Entity\Hotel;
use Tourze\HotelProfileBundle\Entity\RoomType;

/**
 * InventoryUpdateService 单元测试
 */
class InventoryUpdateServiceTest extends TestCase
{
    private EntityManagerInterface|MockObject $entityManager;
    private DailyInventoryRepository|MockObject $inventoryRepository;
    private InventoryUpdateService $service;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->inventoryRepository = $this->createMock(DailyInventoryRepository::class);

        $this->service = new InventoryUpdateService(
            $this->entityManager,
            $this->inventoryRepository
        );
    }

    /**
     * 测试批量更新库存状态 - 成功场景
     */
    public function testBatchUpdateInventoryStatusSuccess(): void
    {
        // 准备测试数据
        $hotel = $this->createMock(Hotel::class);
        $roomType = $this->createMock(RoomType::class);

        $inventory1 = $this->createMock(DailyInventory::class);
        $inventory2 = $this->createMock(DailyInventory::class);

        $params = [
            'hotel' => $hotel,
            'room_type' => $roomType,
            'start_date' => '2024-01-01',
            'end_date' => '2024-01-03',
            'status' => DailyInventoryStatusEnum::AVAILABLE
        ];

        // 模拟QueryBuilder行为
        $queryBuilder = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $query = $this->createMock(\Doctrine\ORM\Query::class);

        $this->inventoryRepository->expects($this->once())
            ->method('createQueryBuilder')
            ->with('di')
            ->willReturn($queryBuilder);

        $queryBuilder->expects($this->once())
            ->method('where')
            ->willReturnSelf();
        $queryBuilder->expects($this->exactly(3))
            ->method('andWhere')
            ->willReturnSelf();
        $queryBuilder->expects($this->exactly(4))
            ->method('setParameter')
            ->willReturnSelf();
        $queryBuilder->expects($this->once())
            ->method('getQuery')
            ->willReturn($query);

        $query->expects($this->once())
            ->method('getResult')
            ->willReturn([$inventory1, $inventory2]);

        // 模拟库存状态检查和更新
        $inventory1->expects($this->once())
            ->method('getStatus')
            ->willReturn(DailyInventoryStatusEnum::PENDING);
        $inventory1->expects($this->once())
            ->method('setStatus')
            ->with(DailyInventoryStatusEnum::AVAILABLE);

        $inventory2->expects($this->once())
            ->method('getStatus')
            ->willReturn(DailyInventoryStatusEnum::AVAILABLE);

        // 预期只会持久化一个更新过的实体
        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($inventory1);
        $this->entityManager->expects($this->once())
            ->method('flush');

        // 执行测试
        $result = $this->service->batchUpdateInventoryStatus($params);

        // 验证结果
        $this->assertTrue($result['success']);
        $this->assertEquals('成功更新1条库存记录', $result['message']);
        $this->assertEquals(1, $result['updated_count']);
    }

    /**
     * 测试批量更新库存状态 - 缺少必要参数
     */
    public function testBatchUpdateInventoryStatusMissingParams(): void
    {
        $params = [
            'room_type' => $this->createMock(RoomType::class),
            'start_date' => '2024-01-01',
            // 缺少 hotel 和 end_date
        ];

        $result = $this->service->batchUpdateInventoryStatus($params);

        $this->assertFalse($result['success']);
        $this->assertEquals('缺少必要参数', $result['message']);
        $this->assertEquals(0, $result['updated_count']);
    }

    /**
     * 测试批量更新库存状态 - 未找到记录
     */
    public function testBatchUpdateInventoryStatusNoRecords(): void
    {
        $params = [
            'hotel' => $this->createMock(Hotel::class),
            'start_date' => '2024-01-01',
            'end_date' => '2024-01-03',
            'status' => DailyInventoryStatusEnum::AVAILABLE
        ];

        // 模拟QueryBuilder返回空结果
        $queryBuilder = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $query = $this->createMock(\Doctrine\ORM\Query::class);

        $this->inventoryRepository->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);
        $query->method('getResult')->willReturn([]);

        $result = $this->service->batchUpdateInventoryStatus($params);

        $this->assertFalse($result['success']);
        $this->assertEquals('未找到符合条件的库存记录', $result['message']);
        $this->assertEquals(0, $result['updated_count']);
    }

    /**
     * 测试批量更新库存价格 - 固定价格调整
     */
    public function testBatchUpdateInventoryPriceFixed(): void
    {
        $hotel = $this->createMock(Hotel::class);
        $inventory = $this->createMock(DailyInventory::class);

        $params = [
            'hotel' => $hotel,
            'start_date' => new \DateTime('2024-01-01'),
            'end_date' => new \DateTime('2024-01-03'),
            'price_type' => 'cost',
            'adjust_method' => 'fixed',
            'cost_price' => '150.00'
        ];

        // 模拟repository方法
        $this->inventoryRepository->expects($this->once())
            ->method('findByDateRangeAndWeekdays')
            ->willReturn([$inventory]);

        // 模拟价格更新
        $inventory->expects($this->once())
            ->method('getCostPrice')
            ->willReturn('100.00');
        $inventory->expects($this->once())
            ->method('setCostPrice')
            ->with('150');

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($inventory);
        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->service->batchUpdateInventoryPrice($params);

        $this->assertTrue($result['success']);
        $this->assertEquals('成功更新1条库存价格', $result['message']);
        $this->assertEquals(1, $result['updated_count']);
    }

    /**
     * 测试批量更新库存价格 - 百分比调整
     */
    public function testBatchUpdateInventoryPricePercent(): void
    {
        $hotel = $this->createMock(Hotel::class);
        $inventory = $this->createMock(DailyInventory::class);

        $params = [
            'hotel' => $hotel,
            'start_date' => new \DateTime('2024-01-01'),
            'end_date' => new \DateTime('2024-01-03'),
            'price_type' => 'cost',
            'adjust_method' => 'percent',
            'adjust_value' => 20 // 增加20%
        ];

        $this->inventoryRepository->expects($this->once())
            ->method('findByDateRangeAndWeekdays')
            ->willReturn([$inventory]);

        // 模拟价格计算: 100 * (1 + 20/100) = 120
        $inventory->expects($this->once())
            ->method('getCostPrice')
            ->willReturn('100.00');
        $inventory->expects($this->once())
            ->method('setCostPrice')
            ->with('120');

        $this->entityManager->expects($this->once())
            ->method('persist');
        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->service->batchUpdateInventoryPrice($params);

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['updated_count']);
    }

    /**
     * 测试批量更新库存价格 - 递减调整
     */
    public function testBatchUpdateInventoryPriceDecrement(): void
    {
        $hotel = $this->createMock(Hotel::class);
        $inventory = $this->createMock(DailyInventory::class);

        $params = [
            'hotel' => $hotel,
            'start_date' => new \DateTime('2024-01-01'),
            'end_date' => new \DateTime('2024-01-03'),
            'price_type' => 'selling',
            'adjust_method' => 'decrement',
            'adjust_value' => 30 // 减少30元
        ];

        $this->inventoryRepository->expects($this->once())
            ->method('findByDateRangeAndWeekdays')
            ->willReturn([$inventory]);

        // 模拟销售价格计算: 200 - 30 = 170
        $inventory->expects($this->once())
            ->method('getSellingPrice')
            ->willReturn('200.00');
        $inventory->expects($this->once())
            ->method('setSellingPrice')
            ->with('170');

        $this->entityManager->expects($this->once())
            ->method('persist');
        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->service->batchUpdateInventoryPrice($params);

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['updated_count']);
    }

    /**
     * 测试批量更新库存价格 - 价格不为负数
     */
    public function testBatchUpdateInventoryPriceMinimumZero(): void
    {
        $hotel = $this->createMock(Hotel::class);
        $inventory = $this->createMock(DailyInventory::class);

        $params = [
            'hotel' => $hotel,
            'start_date' => new \DateTime('2024-01-01'),
            'end_date' => new \DateTime('2024-01-03'),
            'price_type' => 'cost',
            'adjust_method' => 'decrement',
            'adjust_value' => 150 // 减少150元，但原价只有100
        ];

        $this->inventoryRepository->expects($this->once())
            ->method('findByDateRangeAndWeekdays')
            ->willReturn([$inventory]);

        // 模拟价格计算: max(0, 100 - 150) = 0
        $inventory->expects($this->once())
            ->method('getCostPrice')
            ->willReturn('100.00');
        $inventory->expects($this->once())
            ->method('setCostPrice')
            ->with('0');

        $this->entityManager->expects($this->once())
            ->method('persist');
        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->service->batchUpdateInventoryPrice($params);

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['updated_count']);
    }

    /**
     * 测试清除库存合同关联
     */
    public function testClearInventoryContractAssociation(): void
    {
        $inventory = $this->createMock(DailyInventory::class);

        $inventory->expects($this->once())
            ->method('setContract')
            ->with(null);
        $inventory->expects($this->once())
            ->method('setStatus')
            ->with(DailyInventoryStatusEnum::AVAILABLE);

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($inventory);
        // 注意：clearInventoryContractAssociation方法本身不调用flush

        $this->service->clearInventoryContractAssociation($inventory);
    }

    /**
     * 测试批量清除合同关联
     */
    public function testBatchClearContractAssociation(): void
    {
        $hotelId = 1;
        $startDate = new \DateTime('2024-01-01');
        $endDate = new \DateTime('2024-01-03');
        $roomTypeId = 1;
        $contractId = 1;

        // 创建测试数据
        $inventory1 = $this->createMock(DailyInventory::class);
        $inventory2 = $this->createMock(DailyInventory::class);

        // 模拟QueryBuilder
        $queryBuilder = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $query = $this->createMock(\Doctrine\ORM\Query::class);

        $this->inventoryRepository->expects($this->once())
            ->method('createQueryBuilder')
            ->with('di')
            ->willReturn($queryBuilder);

        $queryBuilder->expects($this->once())
            ->method('where')
            ->willReturnSelf();
        $queryBuilder->expects($this->exactly(4))
            ->method('andWhere')
            ->willReturnSelf();
        $queryBuilder->expects($this->exactly(5))
            ->method('setParameter')
            ->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);

        $query->expects($this->once())
            ->method('getResult')
            ->willReturn([$inventory1, $inventory2]);

        // 模拟库存状态检查
        $inventory1->expects($this->once())
            ->method('getStatus')
            ->willReturn(DailyInventoryStatusEnum::AVAILABLE);
        $inventory2->expects($this->once())
            ->method('getStatus')
            ->willReturn(DailyInventoryStatusEnum::PENDING);

        // 模拟清除操作
        $inventory1->expects($this->once())->method('setContract')->with(null);
        $inventory1->expects($this->once())->method('setStatus')->with(DailyInventoryStatusEnum::AVAILABLE);
        $inventory2->expects($this->once())->method('setContract')->with(null);
        $inventory2->expects($this->once())->method('setStatus')->with(DailyInventoryStatusEnum::AVAILABLE);

        $this->entityManager->expects($this->exactly(2))
            ->method('persist');
        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->service->batchClearContractAssociation(
            $hotelId,
            $startDate,
            $endDate,
            $roomTypeId,
            $contractId
        );

        $this->assertEquals(2, $result);
    }

    /**
     * 测试批量清除合同关联 - 只有基本参数
     */
    public function testBatchClearContractAssociationBasicParams(): void
    {
        $hotelId = 1;
        $startDate = new \DateTime('2024-01-01');
        $endDate = new \DateTime('2024-01-03');

        // 创建测试数据 - 5个库存记录
        $inventories = [];
        for ($i = 0; $i < 5; $i++) {
            $inventory = $this->createMock(DailyInventory::class);
            $inventory->expects($this->once())
                ->method('getStatus')
                ->willReturn(DailyInventoryStatusEnum::AVAILABLE);
            $inventory->expects($this->once())->method('setContract')->with(null);
            $inventory->expects($this->once())->method('setStatus')->with(DailyInventoryStatusEnum::AVAILABLE);
            $inventories[] = $inventory;
        }

        // 模拟QueryBuilder
        $queryBuilder = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $query = $this->createMock(\Doctrine\ORM\Query::class);

        $this->inventoryRepository->expects($this->once())
            ->method('createQueryBuilder')
            ->with('di')
            ->willReturn($queryBuilder);

        $queryBuilder->expects($this->once())
            ->method('where')
            ->willReturnSelf();
        $queryBuilder->expects($this->exactly(2))
            ->method('andWhere')
            ->willReturnSelf();
        $queryBuilder->expects($this->exactly(3))
            ->method('setParameter')
            ->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);

        $query->expects($this->once())
            ->method('getResult')
            ->willReturn($inventories);

        $this->entityManager->expects($this->exactly(5))
            ->method('persist');
        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->service->batchClearContractAssociation($hotelId, $startDate, $endDate);

        $this->assertEquals(5, $result);
    }
}
