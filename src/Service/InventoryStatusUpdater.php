<?php

declare(strict_types=1);

namespace Tourze\HotelContractBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Tourze\HotelContractBundle\Entity\DailyInventory;
use Tourze\HotelContractBundle\Enum\DailyInventoryStatusEnum;
use Tourze\HotelContractBundle\Repository\DailyInventoryRepository;

#[Autoconfigure(public: true)]
readonly class InventoryStatusUpdater
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DailyInventoryRepository $inventoryRepository,
    ) {
    }

    /**
     * 批量调整库存状态
     * 不考虑并发
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function batchUpdateInventoryStatus(array $params): array
    {
        // 支持基于ID的批量更新
        if (isset($params['inventory_ids']) && [] !== $params['inventory_ids']) {
            return $this->batchUpdateInventoryStatusByIds($params);
        }

        // 原有的基于条件的批量更新
        $validationResult = $this->validateStatusUpdateParams($params);
        $isValid = $validationResult['success'] ?? false;
        if (!is_bool($isValid) || !$isValid) {
            return $validationResult;
        }

        $inventories = $this->findInventoriesForStatusUpdate($params);
        if ([] === $inventories) {
            return [
                'success' => false,
                'message' => '未找到符合条件的库存记录',
                'updated_count' => 0,
            ];
        }

        $status = $params['status'] ?? null;
        $statusValue = is_string($status) ? DailyInventoryStatusEnum::from($status) : ($status instanceof DailyInventoryStatusEnum ? $status : null);
        $updatedCount = $this->updateInventoryStatus($inventories, $statusValue);

        return [
            'success' => true,
            'message' => sprintf('成功更新%d条库存记录', $updatedCount),
            'updated_count' => $updatedCount,
        ];
    }

    /**
     * 基于ID列表批量更新库存状态
     * 不考虑并发
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function batchUpdateInventoryStatusByIds(array $params): array
    {
        $inventoryIds = $params['inventory_ids'] ?? [];
        $status = $params['status'] ?? null;

        if ([] === $inventoryIds) {
            return [
                'success' => false,
                'message' => '库存ID列表不能为空',
                'updated_count' => 0,
            ];
        }

        if (null === $status || (is_string($status) && '' === trim($status))) {
            return [
                'success' => false,
                'message' => '状态参数不能为空',
                'updated_count' => 0,
            ];
        }

        // 将字符串状态转换为枚举
        if (is_string($status)) {
            $status = DailyInventoryStatusEnum::from($status);
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

        $statusEnum = $status instanceof DailyInventoryStatusEnum ? $status : null;
        $updatedCount = $this->updateInventoryStatus($inventories, $statusEnum);

        return [
            'success' => true,
            'message' => sprintf('成功更新%d条库存记录', $updatedCount),
            'updated_count' => $updatedCount,
        ];
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function validateStatusUpdateParams(array $params): array
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
    private function findInventoriesForStatusUpdate(array $params): array
    {
        $qb = $this->inventoryRepository->createQueryBuilder('di')
            ->where('di.date >= :startDate')
            ->andWhere('di.date <= :endDate')
            ->andWhere('di.hotel = :hotel')
            ->setParameter('startDate', $params['start_date'])
            ->setParameter('endDate', $params['end_date'])
            ->setParameter('hotel', $params['hotel'])
        ;

        if (null !== ($params['room_type'] ?? null)) {
            $qb->andWhere('di.roomType = :roomType')
                ->setParameter('roomType', $params['room_type'])
            ;
        }

        /** @var array<DailyInventory> */
        return $qb->getQuery()->getResult();
    }

    /**
     * 更新库存状态
     *
     * 不考虑并发：此方法在事务内运行，由上层调用方确保并发安全
     *
     * @param array<DailyInventory> $inventories
     */
    private function updateInventoryStatus(array $inventories, DailyInventoryStatusEnum|string|null $status): int
    {
        $updatedCount = 0;

        // 将字符串状态转换为枚举
        if (is_string($status)) {
            $status = DailyInventoryStatusEnum::from($status);
        }

        foreach ($inventories as $inventory) {
            if (null !== $status && $inventory->getStatus() !== $status) {
                $inventory->setStatus($status);
                $this->entityManager->persist($inventory);
                ++$updatedCount;
            }
        }

        if ($updatedCount > 0) {
            $this->entityManager->flush();
        }

        return $updatedCount;
    }
}
