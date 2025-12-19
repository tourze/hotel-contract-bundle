<?php

declare(strict_types=1);

namespace Tourze\HotelContractBundle\Service;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Tourze\HotelContractBundle\Entity\DailyInventory;
use Tourze\HotelContractBundle\Exception\InvalidEntityException;

#[Autoconfigure(public: true)]
readonly final class InventoryPriceCalculator
{
    /**
     * @param array<string, mixed> $adjustmentData
     */
    public function calculateNewPrice(DailyInventory $inventory, array $adjustmentData): float
    {
        $priceType = $adjustmentData['priceType'] ?? 'cost_price';
        if (!is_string($priceType)) {
            throw new InvalidEntityException('价格类型必须是字符串');
        }

        $currentPrice = $this->getCurrentPrice($inventory, $priceType);
        $params = $adjustmentData['params'] ?? [];
        if (!is_array($params)) {
            throw new InvalidEntityException('参数必须是数组');
        }

        /** @var array<string, mixed> $params */
        $adjustMethod = $adjustmentData['adjustMethod'] ?? 'fixed';
        if (!is_string($adjustMethod)) {
            throw new InvalidEntityException('调整方法必须是字符串');
        }

        return match ($adjustMethod) {
            'fixed' => $this->extractFloatValue($params, 'price_value'),
            'percent' => $currentPrice * (1 + $this->extractFloatValue($params, 'adjust_value') / 100),
            'increment' => $currentPrice + $this->extractFloatValue($params, 'adjust_value'),
            'decrement' => $currentPrice - $this->extractFloatValue($params, 'adjust_value'),
            'profit_rate' => $this->calculateProfitRatePrice($inventory, $adjustmentData),
            default => $currentPrice,
        };
    }

    /**
     * @param array<string, mixed> $params
     */
    private function extractFloatValue(array $params, string $key): float
    {
        $value = $params[$key] ?? 0;
        if (is_float($value) || is_int($value)) {
            return (float) $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return (float) $value;
        }

        return 0.0;
    }

    private function getCurrentPrice(DailyInventory $inventory, string $priceType): float
    {
        return (float) ('cost_price' === $priceType ? $inventory->getCostPrice() : $inventory->getSellingPrice());
    }

    /**
     * @param array<string, mixed> $adjustmentData
     */
    private function calculateProfitRatePrice(DailyInventory $inventory, array $adjustmentData): float
    {
        $priceType = $adjustmentData['priceType'] ?? 'cost_price';
        if (!is_string($priceType)) {
            throw new InvalidEntityException('价格类型必须是字符串');
        }

        $params = $adjustmentData['params'] ?? [];
        if (!is_array($params)) {
            throw new InvalidEntityException('参数必须是数组');
        }

        if ('selling_price' === $priceType && isset($params['profit_rate'])) {
            $costPrice = (float) $inventory->getCostPrice();
            $profitRate = $this->extractFloatValue($params, 'profit_rate');

            return $costPrice * (1 + $profitRate / 100);
        }

        return $this->getCurrentPrice($inventory, $priceType);
    }

    public function updateInventoryPrice(DailyInventory $inventory, string $priceType, float $newPrice): void
    {
        $newPrice = max(0, $newPrice);
        $newPriceString = (string) $newPrice;

        if ('cost_price' === $priceType) {
            $inventory->setCostPrice($newPriceString);
        } else {
            $inventory->setSellingPrice($newPriceString);
        }
    }
}
