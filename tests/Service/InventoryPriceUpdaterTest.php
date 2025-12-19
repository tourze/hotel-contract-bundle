<?php

declare(strict_types=1);

namespace Tourze\HotelContractBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\HotelContractBundle\Service\InventoryPriceUpdater;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(InventoryPriceUpdater::class)]
#[RunTestsInSeparateProcesses]
final class InventoryPriceUpdaterTest extends AbstractIntegrationTestCase
{
    protected function onSetUp(): void
    {
    }

    private function getInventoryPriceUpdater(): InventoryPriceUpdater
    {
        return self::getService(InventoryPriceUpdater::class);
    }

    public function testBatchUpdateInventoryPriceReturnsMissingParamsError(): void
    {
        $result = $this->getInventoryPriceUpdater()->batchUpdateInventoryPrice([]);

        // 验证结果包含预期的键
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('message', $result);
        $this->assertArrayHasKey('updated_count', $result);

        $this->assertFalse($result['success']);
        $this->assertSame(0, $result['updated_count']);
    }

    public function testBatchUpdateInventoryPriceByIdsValidatesEmptyIds(): void
    {
        $params = [
            'inventory_ids' => [],
        ];

        $result = $this->getInventoryPriceUpdater()->batchUpdateInventoryPrice($params);

        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
        $this->assertSame('缺少必要参数', $result['message']);
        $this->assertSame(0, $result['updated_count']);
    }
}
