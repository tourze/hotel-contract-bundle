<?php

namespace Tourze\HotelContractBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Tourze\HotelContractBundle\Entity\DailyInventory;
use Tourze\HotelContractBundle\Entity\HotelContract;
use Tourze\HotelContractBundle\Enum\DailyInventoryStatusEnum;
use Tourze\HotelContractBundle\Repository\DailyInventoryRepository;
use Tourze\HotelProfileBundle\Entity\RoomType;
use Tourze\HotelProfileBundle\Repository\RoomTypeRepository;

/**
 * 房型库存管理服务
 * 替代原有的RoomService，直接管理房型库存而非具体房间
 */
class RoomTypeInventoryService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly DailyInventoryRepository $inventoryRepository,
        private readonly InventorySummaryService $summaryService,
        private readonly RoomTypeRepository $roomTypeRepository,
    ) {}

    /**
     * 为指定日期创建房型库存
     *
     * @param RoomType $roomType 房型
     * @param \DateTimeInterface $date 日期
     * @param HotelContract $contract 合同
     * @param int $count 库存数量
     * @param float $costPrice 成本价
     * @param float $sellingPrice 销售价
     * @return array 创建的库存记录数组
     */
    public function createInventories(
        RoomType $roomType,
        \DateTimeInterface $date,
        HotelContract $contract,
        int $count,
        float $costPrice = 0.0,
        float $sellingPrice = 0.0
    ): array {
        $inventories = [];
        $dateFormatted = $date->format('Y-m-d');

        for ($i = 1; $i <= $count; $i++) {
            // 生成唯一的code
            $code = sprintf(
                'INV-%s-%s-%s-%d',
                $contract->getContractNo(),
                $roomType->getId(),
                $dateFormatted,
                $i
            );

            // 检查是否已存在
            $existingInventory = $this->entityManager->getRepository(DailyInventory::class)
                ->findOneBy(['code' => $code]);

            if (!$existingInventory) {
                $inventory = new DailyInventory();
                $inventory->setRoomType($roomType)
                    ->setHotel($roomType->getHotel())
                    ->setDate($date)
                    ->setContract($contract)
                    ->setCode($code)
                    ->setStatus(DailyInventoryStatusEnum::AVAILABLE)
                    ->setCostPrice((string)$costPrice)
                    ->setSellingPrice((string)$sellingPrice);

                $this->entityManager->persist($inventory);
                $inventories[] = $inventory;
            }
        }

        $this->entityManager->flush();

        // 更新库存统计
        $this->summaryService->syncInventorySummary($date);

        return $inventories;
    }

    /**
     * 批量创建日期范围内的房型库存
     *
     * @param RoomType $roomType 房型
     * @param \DateTimeInterface $startDate 开始日期
     * @param \DateTimeInterface $endDate 结束日期
     * @param HotelContract $contract 合同
     * @param int $count 每天的库存数量
     * @param float $costPrice 成本价
     * @param float $sellingPrice 销售价
     * @return array 操作结果
     */
    public function batchCreateInventories(
        RoomType $roomType,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        HotelContract $contract,
        int $count,
        float $costPrice = 0.0,
        float $sellingPrice = 0.0
    ): array {
        $result = [
            'total_days' => 0,
            'created' => 0,
            'errors' => []
        ];

        $currentDate = clone $startDate;
        $endDateCopy = clone $endDate;

        while ($currentDate <= $endDateCopy) {
            $result['total_days']++;

            try {
                $dailyCount = $this->createInventories(
                    $roomType,
                    $currentDate,
                    $contract,
                    $count,
                    $costPrice,
                    $sellingPrice
                );

                $result['created'] += count($dailyCount);
            } catch (\Throwable $e) {
                $result['errors'][] = [
                    'date' => $currentDate->format('Y-m-d'),
                    'message' => $e->getMessage()
                ];
            }

            // 移动到下一天
            if ($currentDate instanceof \DateTime) {
                $currentDate->modify('+1 day');
            } else {
                // 如果不是DateTime实例，创建一个新的
                $nextDate = new \DateTimeImmutable($currentDate->format('Y-m-d'));
                $nextDate->modify('+1 day');
                $currentDate = $nextDate;
            }
        }

        return $result;
    }

    /**
     * 按房型查询可用库存
     *
     * @param RoomType $roomType 房型
     * @param \DateTimeInterface $date 日期
     * @param int $count 需要的数量
     * @return array|DailyInventory[] 可用库存记录数组
     */
    public function findAvailableInventories(
        RoomType $roomType,
        \DateTimeInterface $date,
        int $count = 1
    ): array {
        return $this->inventoryRepository->createQueryBuilder('di')
            ->where('di.roomType = :roomType')
            ->andWhere('di.date = :date')
            ->setParameter('roomType', $roomType)
            ->setParameter('date', $date)
            ->setMaxResults($count)
            ->getQuery()
            ->getResult();
    }

    /**
     * 检查指定日期范围内房型是否有足够库存
     *
     * @param RoomType $roomType 房型
     * @param \DateTimeInterface $startDate 开始日期
     * @param \DateTimeInterface $endDate 结束日期
     * @param int $requiredCount 需要的每日库存数量
     * @return bool 是否有足够库存
     */
    public function hasAvailableInventory(
        RoomType $roomType,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        int $requiredCount
    ): bool {
        $currentDate = clone $startDate;
        $endDateCopy = clone $endDate;

        while ($currentDate <= $endDateCopy) {
            $availableCount = $this->inventoryRepository->count([
                'roomType' => $roomType,
                'date' => $currentDate,
                'isAvailable' => true
            ]);

            if ($availableCount < $requiredCount) {
                return false;
            }

            // 移动到下一天
            if ($currentDate instanceof \DateTime) {
                $currentDate->modify('+1 day');
            } else {
                // 如果不是DateTime实例，创建一个新的
                $nextDate = new \DateTimeImmutable($currentDate->format('Y-m-d'));
                $nextDate->modify('+1 day');
                $currentDate = $nextDate;
            }
        }

        return true;
    }

    /**
     * 预留房型库存（用于预订）
     *
     * @param RoomType $roomType 房型
     * @param \DateTimeInterface $startDate 开始日期
     * @param \DateTimeInterface $endDate 结束日期
     * @param int $count 需要预留的数量
     * @return array 预留的库存记录数组
     */
    public function reserveInventories(
        RoomType $roomType,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        int $count
    ): array {
        $reserved = [];

        // 检查是否有足够库存
        if (!$this->hasAvailableInventory($roomType, $startDate, $endDate, $count)) {
            throw new \RuntimeException('指定日期范围内没有足够的可用库存');
        }

        $currentDate = clone $startDate;
        $endDateCopy = clone $endDate;

        while ($currentDate <= $endDateCopy) {
            $availableInventories = $this->findAvailableInventories($roomType, $currentDate, $count);

            foreach ($availableInventories as $inventory) {
                $inventory->setStatus(DailyInventoryStatusEnum::RESERVED);
                $reserved[] = $inventory;
            }

            // 移动到下一天
            if ($currentDate instanceof \DateTime) {
                $currentDate->modify('+1 day');
            } else {
                // 如果不是DateTime实例，创建一个新的
                $nextDate = new \DateTimeImmutable($currentDate->format('Y-m-d'));
                $nextDate->modify('+1 day');
                $currentDate = $nextDate;
            }
        }

        $this->entityManager->flush();

        return $reserved;
    }

    /**
     * 一键为房型创建库存
     *
     * @param int $contractId 合同ID
     * @param int $roomTypeId 房型ID
     * @param int $inventoryCount 库存数量
     * @param \DateTimeInterface|null $startDate 开始日期
     * @param \DateTimeInterface|null $endDate 结束日期
     * @param float $costPrice 成本价
     * @param float $sellingPrice 销售价
     * @return array 操作结果
     */
    public function oneClickGenerateRoomTypeInventory(
        int $contractId,
        int $roomTypeId,
        int $inventoryCount,
        ?\DateTimeInterface $startDate = null,
        ?\DateTimeInterface $endDate = null,
        float $costPrice = 0.0,
        float $sellingPrice = 0.0
    ): array {
        // 获取合同和房型
        $contract = $this->entityManager->getRepository(\Tourze\HotelContractBundle\Entity\HotelContract::class)->find($contractId);
        if (!$contract) {
            return [
                'success' => false,
                'message' => '合同不存在',
                'created' => 0
            ];
        }

        $roomType = $this->roomTypeRepository->find($roomTypeId);
        if (!$roomType) {
            return [
                'success' => false,
                'message' => '房型不存在',
                'created' => 0
            ];
        }

        // 如果未指定日期，使用合同日期
        if (!$startDate) {
            $startDate = $contract->getStartDate();
        }
        if (!$endDate) {
            $endDate = $contract->getEndDate();
        }

        // 创建库存
        $result = $this->batchCreateInventories(
            $roomType,
            $startDate,
            $endDate,
            $contract,
            $inventoryCount,
            $costPrice,
            $sellingPrice
        );

        return [
            'success' => true,
            'message' => sprintf('成功创建 %d 条库存记录', $result['created']),
            'created' => $result['created'],
            'details' => $result
        ];
    }
}
