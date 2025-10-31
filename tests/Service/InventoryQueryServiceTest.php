<?php

declare(strict_types=1);

namespace Tourze\HotelContractBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\HotelContractBundle\Service\InventoryQueryService;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(InventoryQueryService::class)]
#[RunTestsInSeparateProcesses]
final class InventoryQueryServiceTest extends AbstractIntegrationTestCase
{
    protected function onSetUp(): void
    {
        // Setup for service tests
    }

    private function getInventoryQueryService(): InventoryQueryService
    {
        return self::getService(InventoryQueryService::class);
    }

    public function testGetInventoryData(): void
    {
        $roomTypeId = 1;
        $startDate = new \DateTimeImmutable('2024-01-01');
        $endDate = new \DateTimeImmutable('2024-01-31');

        $result = $this->getInventoryQueryService()->getInventoryData(
            $roomTypeId,
            $startDate->format('Y-m-d'),
            $endDate->format('Y-m-d'),
            1
        );

        // 验证返回的数组包含预期的数据结构
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('message', $result);
        $this->assertArrayHasKey('data', $result);

        // 验证 data 字段是数组
        $this->assertIsArray($result['data']); // @phpstan-ignore method.alreadyNarrowedType (保留测试意图明确性)
        $data = $result['data'];

        // 如果查询失败（房型不存在），data 应该是空数组
        if (false === $result['success']) {
            $this->assertEmpty($data);
        } else {
            // 如果查询成功，验证 data 的结构
            $this->assertArrayHasKey('roomType', $data);
            $this->assertArrayHasKey('checkInDate', $data);
            $this->assertArrayHasKey('checkOutDate', $data);
            $this->assertArrayHasKey('roomCount', $data);
            $this->assertArrayHasKey('dailyInventories', $data);
            $this->assertArrayHasKey('totalDays', $data);
            $this->assertIsArray($data['dailyInventories']); // @phpstan-ignore method.alreadyNarrowedType (保留测试意图明确性)
        }
    }
}
