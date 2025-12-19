<?php

declare(strict_types=1);

namespace Tourze\HotelContractBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\HotelContractBundle\Entity\DailyInventory;
use Tourze\HotelContractBundle\Service\CalendarDataOrganizer;
use Tourze\HotelProfileBundle\Entity\RoomType;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(CalendarDataOrganizer::class)]
#[RunTestsInSeparateProcesses]
final class CalendarDataOrganizerTest extends AbstractIntegrationTestCase
{
    protected function onSetUp(): void
    {
    }

    private function getCalendarDataOrganizer(): CalendarDataOrganizer
    {
        return self::getService(CalendarDataOrganizer::class);
    }

    public function testGenerateCalendarDatesCreatesDateRange(): void
    {
        $startDate = new \DateTime('2024-01-01');
        $endDate = new \DateTime('2024-01-03');

        $result = $this->getCalendarDataOrganizer()->generateCalendarDates($startDate, $endDate);

        // 验证返回的数组长度
        $this->assertCount(3, $result);

        // 验证第一个日期
        $this->assertArrayHasKey('date', $result[0]);
        $this->assertArrayHasKey('day', $result[0]);
        $this->assertArrayHasKey('weekday', $result[0]);
        $this->assertArrayHasKey('is_weekend', $result[0]);

        $this->assertInstanceOf(\DateTimeInterface::class, $result[0]['date']);
        $this->assertIsString($result[0]['day']);
        $this->assertIsString($result[0]['weekday']);
        $this->assertIsBool($result[0]['is_weekend']);
    }

    public function testGenerateRoomTypePricesReturnsExpectedStructure(): void
    {
        $roomType = new RoomType();
        $reflection = new \ReflectionClass($roomType);
        $property = $reflection->getProperty('id');
        $property->setAccessible(true);
        $property->setValue($roomType, 1);

        $dates = [
            ['date' => new \DateTime('2024-01-01')],
        ];

        $priceData = [
            [
                'id' => 1,
                'roomTypeId' => 1,
                'date' => new \DateTime('2024-01-01'),
                'costPrice' => '100',
                'sellingPrice' => '150',
                'inventoryCode' => 'INV001',
            ],
        ];

        $result = $this->getCalendarDataOrganizer()->generateRoomTypePrices($roomType, $dates, $priceData);

        // 验证返回的数组包含预期的键
        $this->assertArrayHasKey('date', $result[0]);
        $this->assertArrayHasKey('inventories', $result[0]);
        // 验证inventories字段的内容
        $this->assertIsArray($result[0]['inventories']);
    }

    public function testGenerateRoomTypeInventoryPricesReturnsExpectedStructure(): void
    {
        $roomType = new RoomType();
        $reflection = new \ReflectionClass($roomType);
        $property = $reflection->getProperty('id');
        $property->setAccessible(true);
        $property->setValue($roomType, 1);

        $dates = [
            ['date' => new \DateTime('2024-01-01')],
        ];

        $inventory = new DailyInventory();
        $invReflection = new \ReflectionClass($inventory);
        $invProperty = $invReflection->getProperty('id');
        $invProperty->setAccessible(true);
        $invProperty->setValue($inventory, 1);

        $inventory->setRoomType($roomType);
        $inventory->setDate(new \DateTime('2024-01-01'));
        $inventory->setCostPrice('100');
        $inventory->setSellingPrice('150');
        $inventory->setCode('INV001');

        $inventories = [$inventory];

        $result = $this->getCalendarDataOrganizer()->generateRoomTypeInventoryPrices($roomType, $dates, $inventories);

        // 验证返回的数组包含预期的键
        $this->assertArrayHasKey('date', $result[0]);
        $this->assertArrayHasKey('inventories', $result[0]);
        // 验证inventories字段的内容
        $this->assertIsArray($result[0]['inventories']);
    }
}
