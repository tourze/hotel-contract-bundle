<?php

declare(strict_types=1);

namespace Tourze\HotelContractBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\HotelContractBundle\Service\InventoryStatusUpdater;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(InventoryStatusUpdater::class)]
#[RunTestsInSeparateProcesses]
final class InventoryStatusUpdaterTest extends AbstractIntegrationTestCase
{
    protected function onSetUp(): void
    {
    }

    private function getInventoryStatusUpdater(): InventoryStatusUpdater
    {
        return self::getService(InventoryStatusUpdater::class);
    }

    public function testBatchUpdateInventoryStatusReturnsMissingParamsError(): void
    {
        $result = $this->getInventoryStatusUpdater()->batchUpdateInventoryStatus([]);

        // 验证结果包含预期的键
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('message', $result);
        $this->assertArrayHasKey('updated_count', $result);

        $this->assertFalse($result['success']);
        $this->assertSame(0, $result['updated_count']);
    }

    public function testBatchUpdateInventoryStatusByIdsValidatesEmptyIds(): void
    {
        $params = [
            'inventory_ids' => [],
        ];

        $result = $this->getInventoryStatusUpdater()->batchUpdateInventoryStatus($params);

        // 验证结果包含预期的键和值
        $this->assertFalse($result['success']);
        $this->assertSame('缺少必要参数', $result['message']);
        $this->assertSame(0, $result['updated_count']);
    }

    public function testBatchUpdateInventoryStatusByIdsValidatesEmptyStatus(): void
    {
        $params = [
            'inventory_ids' => [1, 2, 3],
            'status' => null,
        ];

        $result = $this->getInventoryStatusUpdater()->batchUpdateInventoryStatus($params);

        // 验证结果包含预期的键和值
        $this->assertFalse($result['success']);
        $this->assertSame('状态参数不能为空', $result['message']);
        $this->assertSame(0, $result['updated_count']);
    }
}
