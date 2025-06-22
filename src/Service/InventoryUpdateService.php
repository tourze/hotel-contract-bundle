<?php

namespace Tourze\HotelContractBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Tourze\HotelContractBundle\Entity\DailyInventory;
use Tourze\HotelContractBundle\Enum\DailyInventoryStatusEnum;
use Tourze\HotelContractBundle\Repository\DailyInventoryRepository;

class InventoryUpdateService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly DailyInventoryRepository $inventoryRepository,
    ) {}

    /**
     * 批量调整库存状态
     *
     * @param array $params 调整参数
     *
     * @return array 操作结果
     */
    public function batchUpdateInventoryStatus(array $params): array
    {
        $hotel = $params['hotel'] ?? null;
        $roomType = $params['room_type'] ?? null;
        $startDate = $params['start_date'] ?? null;
        $endDate = $params['end_date'] ?? null;
        $status = $params['status'] ?? null;
        $isAvailable = $params['is_available'] ?? null;

        if (!$hotel || !$startDate || !$endDate) {
            return [
                'success' => false,
                'message' => '缺少必要参数',
                'updated_count' => 0
            ];
        }

        // 查找日期范围内的库存
        $qb = $this->inventoryRepository->createQueryBuilder('di')
            ->where('di.date >= :startDate')
            ->andWhere('di.date <= :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate);

        $qb->andWhere('di.hotel = :hotel')
            ->setParameter('hotel', $hotel);

        if ($roomType) {
            $qb->andWhere('di.roomType = :roomType')
                ->setParameter('roomType', $roomType);
        }

        $inventories = $qb->getQuery()->getResult();

        if (empty($inventories)) {
            return [
                'success' => false,
                'message' => '未找到符合条件的库存记录',
                'updated_count' => 0
            ];
        }

        $updatedCount = 0;

        foreach ($inventories as $inventory) {
            $updated = false;

            // 更新状态
            if ($status && $inventory->getStatus() != $status) {
                $inventory->setStatus($status);
                $updated = true;
            }

            if ($updated) {
                $this->entityManager->persist($inventory);
                $updatedCount++;
            }
        }

        if ($updatedCount > 0) {
            $this->entityManager->flush();
        }

        return [
            'success' => true,
            'message' => sprintf('成功更新%d条库存记录', $updatedCount),
            'updated_count' => $updatedCount
        ];
    }

    /**
     * 批量调整库存价格
     *
     * @param array $params 调整参数
     *
     * @return array 操作结果
     */
    public function batchUpdateInventoryPrice(array $params): array
    {
        $hotel = $params['hotel'] ?? null;
        $roomType = $params['room_type'] ?? null;
        $startDate = $params['start_date'] ?? null;
        $endDate = $params['end_date'] ?? null;
        $priceType = $params['price_type'] ?? 'both';
        $adjustMethod = $params['adjust_method'] ?? 'fixed';
        $costPrice = $params['cost_price'] ?? null;
        $sellingPrice = $params['selling_price'] ?? null;
        $adjustValue = $params['adjust_value'] ?? null;
        $dayFilter = $params['day_filter'] ?? null;
        $days = $params['days'] ?? [];
        $reason = $params['reason'] ?? '';

        if (!$hotel || !$startDate || !$endDate) {
            return [
                'success' => false,
                'message' => '缺少必要参数',
                'updated_count' => 0
            ];
        }

        // 构建查询条件
        $criteria = [];
        $criteria['di.hotel'] = $hotel;

        if ($roomType) {
            $criteria['di.roomType'] = $roomType;
        }

        // 查询日期范围内的库存
        $inventories = $this->inventoryRepository->findByDateRangeAndWeekdays(
            $startDate,
            $endDate,
            $criteria,
            $dayFilter,
            $days
        );

        if (empty($inventories)) {
            return [
                'success' => false,
                'message' => '未找到符合条件的库存记录',
                'updated_count' => 0
            ];
        }

        $updatedCount = 0;

        foreach ($inventories as $inventory) {
            $updated = false;

            // 处理采购成本价
            if ($priceType === 'cost' || $priceType === 'both') {
                $originalCostPrice = (float)$inventory->getCostPrice();
                $newCostPrice = $originalCostPrice;

                switch ($adjustMethod) {
                    case 'fixed':
                        if ($costPrice !== null) {
                            $newCostPrice = (float)$costPrice;
                        }
                        break;
                    case 'percent':
                        if ($adjustValue !== null) {
                            $newCostPrice = $originalCostPrice * (1 + $adjustValue / 100);
                        }
                        break;
                    case 'increment':
                        if ($adjustValue !== null) {
                            $newCostPrice = $originalCostPrice + (float)$adjustValue;
                        }
                        break;
                    case 'decrement':
                        if ($adjustValue !== null) {
                            $newCostPrice = max(0, $originalCostPrice - (float)$adjustValue);
                        }
                        break;
                }

                if ($newCostPrice != $originalCostPrice) {
                    $inventory->setCostPrice((string)$newCostPrice);
                    $updated = true;
                }
            }

            // 处理销售价格
            if ($priceType === 'selling' || $priceType === 'both') {
                $originalSellingPrice = (float)$inventory->getSellingPrice();
                $newSellingPrice = $originalSellingPrice;

                switch ($adjustMethod) {
                    case 'fixed':
                        if ($sellingPrice !== null) {
                            $newSellingPrice = (float)$sellingPrice;
                        }
                        break;
                    case 'percent':
                        if ($adjustValue !== null) {
                            $newSellingPrice = $originalSellingPrice * (1 + $adjustValue / 100);
                        }
                        break;
                    case 'increment':
                        if ($adjustValue !== null) {
                            $newSellingPrice = $originalSellingPrice + (float)$adjustValue;
                        }
                        break;
                    case 'decrement':
                        if ($adjustValue !== null) {
                            $newSellingPrice = max(0, $originalSellingPrice - (float)$adjustValue);
                        }
                        break;
                }

                if ($newSellingPrice != $originalSellingPrice) {
                    $inventory->setSellingPrice((string)$newSellingPrice);
                    $updated = true;
                }
            }

            if ($updated) {
                $this->entityManager->persist($inventory);
                $updatedCount++;
            }
        }

        if ($updatedCount > 0) {
            $this->entityManager->flush();
        }

        return [
            'success' => true,
            'message' => sprintf('成功更新%d条库存价格', $updatedCount),
            'updated_count' => $updatedCount
        ];
    }

    /**
     * 清除日库存与合同的关联
     *
     * @param DailyInventory $inventory 日库存记录
     */
    public function clearInventoryContractAssociation(DailyInventory $inventory): void
    {
        $inventory->setContract(null);
        $inventory->setStatus(DailyInventoryStatusEnum::AVAILABLE);
        $this->entityManager->persist($inventory);
    }

    /**
     * 批量清除日期范围内的合同关联
     *
     * @param int $hotelId 酒店ID
     * @param int|null $roomTypeId 房型ID (可选)
     * @param \DateTimeInterface $startDate 开始日期
     * @param \DateTimeInterface $endDate 结束日期
     * @param int|null $contractId 合同ID (可选，指定后只清除该合同关联)
     * @return int 处理的记录数
     */
    public function batchClearContractAssociation(
        int $hotelId,
        ?\DateTimeInterface $startDate,
        ?\DateTimeInterface $endDate,
        ?int $roomTypeId = null,
        ?int $contractId = null
    ): int {
        // 查找该酒店在日期范围内的所有库存记录
        $qb = $this->inventoryRepository->createQueryBuilder('di')
            ->where('di.hotel = :hotelId')
            ->setParameter('hotelId', $hotelId);

        if ($startDate !== null) {
            $qb->andWhere('di.date >= :startDate')
                ->setParameter('startDate', $startDate);
        }

        if ($endDate !== null) {
            $qb->andWhere('di.date <= :endDate')
                ->setParameter('endDate', $endDate);
        }

        if ($roomTypeId !== null) {
            $qb->andWhere('di.roomType = :roomTypeId')
                ->setParameter('roomTypeId', $roomTypeId);
        }

        if ($contractId !== null) {
            $qb->andWhere('di.contract = :contractId')
                ->setParameter('contractId', $contractId);
        }

        $inventories = $qb->getQuery()->getResult();

        $processedCount = 0;
        foreach ($inventories as $inventory) {
            // 检查是否已被预订
            if ($inventory->getStatus() === DailyInventoryStatusEnum::SOLD) {
                // 已被预订的库存记录保持不变
                continue;
            }

            $this->clearInventoryContractAssociation($inventory);
            $processedCount++;
        }

        if ($processedCount > 0) {
            $this->entityManager->flush();
        }

        return $processedCount;
    }
}
