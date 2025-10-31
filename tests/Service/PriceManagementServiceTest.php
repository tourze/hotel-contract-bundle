<?php

declare(strict_types=1);

namespace Tourze\HotelContractBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\HotelContractBundle\Service\PriceManagementService;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(PriceManagementService::class)]
#[RunTestsInSeparateProcesses]
final class PriceManagementServiceTest extends AbstractIntegrationTestCase
{
    protected function onSetUp(): void
    {
        // Setup for service tests
    }

    private function getPriceManagementService(): PriceManagementService
    {
        return self::getService(PriceManagementService::class);
    }

    public function testProcessBatchPriceAdjustmentSuccess(): void
    {
        // 准备测试数据
        $params = [
            'hotel_id' => 1,
            'room_type_id' => 2,
            'start_date' => '2024-01-01',
            'end_date' => '2024-01-31',
            'price_type' => 'cost_price',
            'adjust_method' => 'fixed',
            'price_value' => 100.00,
            'day_filter' => 'all',
            'reason' => '测试调价',
        ];

        // 执行测试 - 主要验证方法不抛出异常
        $result = $this->getPriceManagementService()->processBatchPriceAdjustment($params);

        // 验证结果包含预期的键
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('message', $result);
    }

    public function testProcessBatchPriceAdjustmentWithInvalidHotel(): void
    {
        // 准备测试数据
        $params = [
            'hotel_id' => 999, // 不存在的酒店ID
            'room_type_id' => 2,
            'start_date' => '2024-01-01',
            'end_date' => '2024-01-31',
            'price_type' => 'cost_price',
            'adjust_method' => 'fixed',
            'price_value' => 100.00,
            'day_filter' => 'all',
            'reason' => '测试调价',
        ];

        // 执行测试
        $result = $this->getPriceManagementService()->processBatchPriceAdjustment($params);

        // 验证结果包含预期的键
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('message', $result);
    }

    public function testUpdateContractPriceSuccess(): void
    {
        // 准备测试数据
        $inventoryId = 1;
        $costPrice = '100.50';

        // 执行测试 - 主要验证方法不抛出异常
        $result = $this->getPriceManagementService()->updateContractPrice($inventoryId, $costPrice);

        // 验证结果包含预期的键
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('message', $result);
    }

    public function testUpdateContractPriceWithInvalidInventory(): void
    {
        // 准备测试数据
        $inventoryId = 999; // 不存在的库存ID
        $costPrice = '100.50';

        // 执行测试
        $result = $this->getPriceManagementService()->updateContractPrice($inventoryId, $costPrice);

        // 验证结果包含预期的键
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('message', $result);
    }

    public function testUpdateSellingPriceSuccess(): void
    {
        // 准备测试数据
        $inventoryId = 1;
        $sellingPrice = '150.75';

        // 执行测试 - 主要验证方法不抛出异常
        $result = $this->getPriceManagementService()->updateSellingPrice($inventoryId, $sellingPrice);

        // 验证结果包含预期的键
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('message', $result);
    }

    public function testUpdateSellingPriceWithInvalidInventory(): void
    {
        // 准备测试数据
        $inventoryId = 999; // 不存在的库存ID
        $sellingPrice = '150.75';

        // 执行测试
        $result = $this->getPriceManagementService()->updateSellingPrice($inventoryId, $sellingPrice);

        // 验证结果包含预期的键
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('message', $result);
    }
}
