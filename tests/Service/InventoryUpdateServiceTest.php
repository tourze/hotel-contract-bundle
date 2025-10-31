<?php

declare(strict_types=1);

namespace Tourze\HotelContractBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\HotelContractBundle\Entity\DailyInventory;
use Tourze\HotelContractBundle\Service\InventoryUpdateService;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(InventoryUpdateService::class)]
#[RunTestsInSeparateProcesses]
final class InventoryUpdateServiceTest extends AbstractIntegrationTestCase
{
    protected function onSetUp(): void
    {
        // Setup for service tests
    }

    private function getInventoryUpdateService(): InventoryUpdateService
    {
        return self::getService(InventoryUpdateService::class);
    }

    public function testBatchUpdateInventoryStatus(): void
    {
        $params = [
            'hotel_id' => 1,
            'start_date' => '2024-01-01',
            'end_date' => '2024-01-31',
            'status' => 'AVAILABLE',
        ];

        $result = $this->getInventoryUpdateService()->batchUpdateInventoryStatus($params);

        // 验证返回的数组包含预期的键
        $this->assertArrayHasKey('success', $result);
    }

    public function testBatchUpdateInventoryPrice(): void
    {
        $params = [
            'hotel_id' => 1,
            'start_date' => '2024-01-01',
            'end_date' => '2024-01-31',
            'cost_price' => 100.00,
            'selling_price' => 120.00,
        ];

        $result = $this->getInventoryUpdateService()->batchUpdateInventoryPrice($params);

        // 验证返回的数组包含预期的键
        $this->assertArrayHasKey('success', $result);
    }

    public function testClearInventoryContractAssociation(): void
    {
        $inventory = new DailyInventory();
        $inventory->setCode('TEST-001');
        $inventory->setDate(new \DateTimeImmutable('2024-01-01'));

        $this->getInventoryUpdateService()->clearInventoryContractAssociation($inventory);

        $this->assertNull($inventory->getContract());
    }

    public function testBatchClearContractAssociation(): void
    {
        $hotelId = 1;
        $startDate = new \DateTimeImmutable('2024-01-01');
        $endDate = new \DateTimeImmutable('2024-01-31');

        $result = $this->getInventoryUpdateService()->batchClearContractAssociation($hotelId, $startDate, $endDate);
        $this->assertIsInt($result);
        $this->assertGreaterThanOrEqual(0, $result);
    }
}
