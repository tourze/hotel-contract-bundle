<?php

declare(strict_types=1);

namespace Tourze\HotelContractBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\HotelContractBundle\Entity\DailyInventory;
use Tourze\HotelContractBundle\Exception\InvalidEntityException;
use Tourze\HotelContractBundle\Service\InventoryPriceCalculator;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(InventoryPriceCalculator::class)]
#[RunTestsInSeparateProcesses]
final class InventoryPriceCalculatorTest extends AbstractIntegrationTestCase
{
    protected function onSetUp(): void
    {
    }

    private function getInventoryPriceCalculator(): InventoryPriceCalculator
    {
        return self::getService(InventoryPriceCalculator::class);
    }

    public function testCalculateNewPriceWithFixedMethod(): void
    {
        $inventory = new DailyInventory();
        $inventory->setCostPrice('100');
        $inventory->setSellingPrice('150');

        $adjustmentData = [
            'priceType' => 'cost_price',
            'adjustMethod' => 'fixed',
            'params' => [
                'price_value' => 120.0,
            ],
        ];

        $result = $this->getInventoryPriceCalculator()->calculateNewPrice($inventory, $adjustmentData);

        $this->assertIsFloat($result);
        $this->assertSame(120.0, $result);
    }

    public function testCalculateNewPriceWithPercentMethod(): void
    {
        $inventory = new DailyInventory();
        $inventory->setCostPrice('100');

        $adjustmentData = [
            'priceType' => 'cost_price',
            'adjustMethod' => 'percent',
            'params' => [
                'adjust_value' => 10.0,
            ],
        ];

        $result = $this->getInventoryPriceCalculator()->calculateNewPrice($inventory, $adjustmentData);

        $this->assertIsFloat($result);
        $this->assertEqualsWithDelta(110.0, $result, 0.0001);
    }

    public function testUpdateInventoryPriceUpdatesCostPrice(): void
    {
        $inventory = new DailyInventory();
        $inventory->setCostPrice('100');

        $this->getInventoryPriceCalculator()->updateInventoryPrice($inventory, 'cost_price', 120.0);

        $this->assertSame('120', $inventory->getCostPrice());
    }

    public function testUpdateInventoryPriceUpdatesSellingPrice(): void
    {
        $inventory = new DailyInventory();
        $inventory->setSellingPrice('150');

        $this->getInventoryPriceCalculator()->updateInventoryPrice($inventory, 'selling_price', 180.0);

        $this->assertSame('180', $inventory->getSellingPrice());
    }

    public function testCalculateNewPriceThrowsExceptionForInvalidPriceType(): void
    {
        $inventory = new DailyInventory();
        $adjustmentData = [
            'priceType' => 123,
            'adjustMethod' => 'fixed',
            'params' => [],
        ];

        $this->expectException(InvalidEntityException::class);
        $this->expectExceptionMessage('价格类型必须是字符串');

        $this->getInventoryPriceCalculator()->calculateNewPrice($inventory, $adjustmentData);
    }
}
