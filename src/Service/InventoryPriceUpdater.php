<?php

declare(strict_types=1);

namespace Tourze\HotelContractBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Tourze\HotelContractBundle\Entity\DailyInventory;
use Tourze\HotelContractBundle\Repository\DailyInventoryRepository;

#[Autoconfigure(public: true)]
readonly final class InventoryPriceUpdater
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DailyInventoryRepository $inventoryRepository,
    ) {
    }

    /**
     * 批量调整库存价格
     * 不考虑并发
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function batchUpdateInventoryPrice(array $params): array
    {
        // 支持基于ID的批量更新
        if (isset($params['inventory_ids']) && [] !== $params['inventory_ids']) {
            return $this->batchUpdateInventoryPriceByIds($params);
        }

        // 原有的基于条件的批量更新
        $validationResult = $this->validateBatchUpdateParams($params);
        $isValid = $validationResult['success'] ?? false;
        if (!is_bool($isValid) || !$isValid) {
            return $validationResult;
        }

        $inventories = $this->findInventoriesForUpdate($params);
        if ([] === $inventories) {
            return [
                'success' => false,
                'message' => '未找到符合条件的库存记录',
                'updated_count' => 0,
            ];
        }

        $updatedCount = $this->updateInventoryPrices($inventories, $params);

        if ($updatedCount > 0) {
            $this->entityManager->flush();
        }

        return [
            'success' => true,
            'message' => sprintf('成功更新%d条库存价格', $updatedCount),
            'updated_count' => $updatedCount,
        ];
    }

    /**
     * 基于ID列表批量更新库存价格
     * 不考虑并发
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function batchUpdateInventoryPriceByIds(array $params): array
    {
        $inventoryIds = $params['inventory_ids'] ?? [];

        if ([] === $inventoryIds) {
            return [
                'success' => false,
                'message' => '库存ID列表不能为空',
                'updated_count' => 0,
            ];
        }

        // 查找库存记录
        /** @var DailyInventory[] $inventories */
        $inventories = $this->inventoryRepository
            ->createQueryBuilder('di')
            ->where('di.id IN (:ids)')
            ->setParameter('ids', $inventoryIds)
            ->getQuery()
            ->getResult()
        ;

        if ([] === $inventories) {
            return [
                'success' => false,
                'message' => '未找到指定的库存记录',
                'updated_count' => 0,
            ];
        }

        $updatedCount = $this->updateInventoryPrices($inventories, $params);

        if ($updatedCount > 0) {
            $this->entityManager->flush();
        }

        return [
            'success' => true,
            'message' => sprintf('成功更新%d条库存价格', $updatedCount),
            'updated_count' => $updatedCount,
        ];
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function validateBatchUpdateParams(array $params): array
    {
        $hotel = $params['hotel'] ?? null;
        $startDate = $params['start_date'] ?? null;
        $endDate = $params['end_date'] ?? null;

        if (null === $hotel || null === $startDate || null === $endDate) {
            return [
                'success' => false,
                'message' => '缺少必要参数',
                'updated_count' => 0,
            ];
        }

        return ['success' => true];
    }

    /**
     * @param array<string, mixed> $params
     * @return array<DailyInventory>
     */
    private function findInventoriesForUpdate(array $params): array
    {
        $criteria = ['di.hotel' => $params['hotel']];
        $roomType = $params['room_type'] ?? null;

        if (null !== $roomType) {
            $criteria['di.roomType'] = $roomType;
        }

        $startDate = $params['start_date'];
        $endDate = $params['end_date'];
        $dayFilter = $params['day_filter'] ?? null;
        $days = $params['days'] ?? [];

        if (!$startDate instanceof \DateTimeInterface || !$endDate instanceof \DateTimeInterface) {
            return [];
        }

        if (!is_string($dayFilter) && null !== $dayFilter) {
            return [];
        }

        if (!is_array($days)) {
            return [];
        }

        /** @var int[] $validDays */
        $validDays = array_filter($days, fn ($day): bool => is_int($day));

        return $this->inventoryRepository->findByDateRangeAndWeekdays(
            $startDate,
            $endDate,
            $criteria,
            $dayFilter,
            $validDays
        );
    }

    /**
     * @param array<DailyInventory> $inventories
     * @param array<string, mixed> $params
     */
    private function updateInventoryPrices(array $inventories, array $params): int
    {
        $updatedCount = 0;

        foreach ($inventories as $inventory) {
            if ($this->updateInventoryPriceByType($inventory, $params)) {
                $this->entityManager->persist($inventory);
                ++$updatedCount;
            }
        }

        return $updatedCount;
    }

    /**
     * @param array<string, mixed> $params
     */
    private function updateInventoryPriceByType(DailyInventory $inventory, array $params): bool
    {
        $priceType = $params['price_type'] ?? 'both';
        $priceTypeStr = is_string($priceType) ? $priceType : 'both';
        $updated = false;

        if ($this->shouldUpdateCostPrice($priceTypeStr)) {
            $updated = $this->updateCostPrice($inventory, $params);
        }

        if ($this->shouldUpdateSellingPrice($priceTypeStr)) {
            $updated = $this->updateSellingPrice($inventory, $params) || $updated;
        }

        return $updated;
    }

    private function shouldUpdateCostPrice(string $priceType): bool
    {
        return 'cost' === $priceType || 'both' === $priceType;
    }

    private function shouldUpdateSellingPrice(string $priceType): bool
    {
        return 'selling' === $priceType || 'both' === $priceType;
    }

    /**
     * @param array<string, mixed> $params
     */
    private function updateCostPrice(DailyInventory $inventory, array $params): bool
    {
        $originalPrice = (float) $inventory->getCostPrice();
        $adjustMethod = $params['adjust_method'] ?? 'fixed';
        $costPrice = $params['cost_price'] ?? null;
        $adjustValue = $params['adjust_value'] ?? null;

        $newPrice = $this->calculateNewPrice(
            $originalPrice,
            is_string($adjustMethod) ? $adjustMethod : 'fixed',
            is_float($costPrice) || is_int($costPrice) ? (float) $costPrice : null,
            is_float($adjustValue) || is_int($adjustValue) ? (float) $adjustValue : null
        );

        if ($newPrice !== $originalPrice) {
            $inventory->setCostPrice((string) $newPrice);

            return true;
        }

        return false;
    }

    /**
     * @param array<string, mixed> $params
     */
    private function updateSellingPrice(DailyInventory $inventory, array $params): bool
    {
        $originalPrice = (float) $inventory->getSellingPrice();
        $adjustMethod = $params['adjust_method'] ?? 'fixed';
        $sellingPrice = $params['selling_price'] ?? null;
        $adjustValue = $params['adjust_value'] ?? null;

        $newPrice = $this->calculateNewPrice(
            $originalPrice,
            is_string($adjustMethod) ? $adjustMethod : 'fixed',
            is_float($sellingPrice) || is_int($sellingPrice) ? (float) $sellingPrice : null,
            is_float($adjustValue) || is_int($adjustValue) ? (float) $adjustValue : null
        );

        if ($newPrice !== $originalPrice) {
            $inventory->setSellingPrice((string) $newPrice);

            return true;
        }

        return false;
    }

    private function calculateNewPrice(float $originalPrice, string $adjustMethod, ?float $fixedPrice, ?float $adjustValue): float
    {
        switch ($adjustMethod) {
            case 'fixed':
                return null !== $fixedPrice ? $fixedPrice : $originalPrice;
            case 'percent':
                return null !== $adjustValue ? $originalPrice * (1 + $adjustValue / 100) : $originalPrice;
            case 'increment':
                return null !== $adjustValue ? $originalPrice + $adjustValue : $originalPrice;
            case 'decrement':
                return null !== $adjustValue ? max(0, $originalPrice - $adjustValue) : $originalPrice;
            default:
                return $originalPrice;
        }
    }
}
