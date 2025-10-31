<?php

namespace Tourze\HotelContractBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Tourze\HotelContractBundle\Entity\DailyInventory;
use Tourze\HotelContractBundle\Entity\HotelContract;
use Tourze\HotelContractBundle\Enum\DailyInventoryStatusEnum;
use Tourze\HotelContractBundle\Exception\InsufficientInventoryException;
use Tourze\HotelContractBundle\Exception\InventoryMismatchException;
use Tourze\HotelContractBundle\Exception\InventoryNotFoundException;
use Tourze\HotelContractBundle\Exception\InventoryUnavailableException;
use Tourze\HotelContractBundle\Repository\DailyInventoryRepository;
use Tourze\HotelContractBundle\Repository\HotelContractRepository;
use Tourze\HotelProfileBundle\Entity\RoomType;
use Tourze\HotelProfileBundle\Service\RoomTypeService;

/**
 * 房型库存管理服务
 * 替代原有的RoomService，直接管理房型库存而非具体房间
 */
#[Autoconfigure(public: true)]
readonly class RoomTypeInventoryService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DailyInventoryRepository $inventoryRepository,
        private InventorySummaryService $summaryService,
        private RoomTypeService $roomTypeService,
        private HotelContractRepository $hotelContractRepository,
    ) {
    }

    /**
     * 为指定日期创建房型库存
     *
     * @param RoomType           $roomType     房型
     * @param \DateTimeInterface $date         日期
     * @param HotelContract      $contract     合同
     * @param int                $count        库存数量
     * @param float              $costPrice    成本价
     * @param float              $sellingPrice 销售价
     *
     * @return array 创建的库存记录数组
     */
    /**
     * @return array<DailyInventory>
     */
    public function createInventories(
        RoomType $roomType,
        \DateTimeInterface $date,
        HotelContract $contract,
        int $count,
        float $costPrice = 0.0,
        float $sellingPrice = 0.0,
    ): array {
        $inventories = [];
        $dateFormatted = $date->format('Y-m-d');

        for ($i = 1; $i <= $count; ++$i) {
            // 生成唯一的code
            $code = sprintf(
                'INV-%s-%s-%s-%d',
                $contract->getContractNo(),
                $roomType->getId(),
                $dateFormatted,
                $i
            );

            // 检查是否已存在
            $existingInventory = $this->inventoryRepository
                ->findOneBy(['code' => $code])
            ;

            if (null === $existingInventory) {
                $inventory = new DailyInventory();
                $inventory->setRoomType($roomType);
                $inventory->setHotel($roomType->getHotel());
                $inventory->setDate($date);
                $inventory->setContract($contract);
                $inventory->setCode($code);
                $inventory->setStatus(DailyInventoryStatusEnum::AVAILABLE);
                $inventory->setCostPrice((string) $costPrice);
                $inventory->setSellingPrice((string) $sellingPrice);

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
     * @param RoomType           $roomType     房型
     * @param \DateTimeInterface $startDate    开始日期
     * @param \DateTimeInterface $endDate      结束日期
     * @param HotelContract      $contract     合同
     * @param int                $count        每天的库存数量
     * @param float              $costPrice    成本价
     * @param float              $sellingPrice 销售价
     *
     * @return array 操作结果
     */
    /**
     * @return array<string, mixed>
     */
    public function batchCreateInventories(
        RoomType $roomType,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        HotelContract $contract,
        int $count,
        float $costPrice = 0.0,
        float $sellingPrice = 0.0,
    ): array {
        $result = [
            'total_days' => 0,
            'created' => 0,
            'errors' => [],
        ];

        $currentDate = clone $startDate;
        $endDateCopy = clone $endDate;

        while ($currentDate <= $endDateCopy) {
            ++$result['total_days'];

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
                    'message' => $e->getMessage(),
                ];
            }

            // 移动到下一天
            if ($currentDate instanceof \DateTime) {
                $currentDate->modify('+1 day');
            } else {
                // 如果不是DateTime实例，创建一个新的
                $nextDate = new \DateTimeImmutable($currentDate->format('Y-m-d'));
                $currentDate = $nextDate->modify('+1 day');
            }
        }

        return $result;
    }

    /**
     * 按房型查询可用库存
     *
     * @param RoomType           $roomType 房型
     * @param \DateTimeInterface $date     日期
     * @param int                $count    需要的数量
     *
     * @return array|DailyInventory[] 可用库存记录数组
     */
    /**
     * @return array<DailyInventory>
     */
    public function findAvailableInventories(
        RoomType $roomType,
        \DateTimeInterface $date,
        int $count = 1,
    ): array {
        /** @var array<DailyInventory> */
        return $this->inventoryRepository->createQueryBuilder('di')
            ->where('di.roomType = :roomType')
            ->andWhere('di.date = :date')
            ->andWhere('di.status = :status')
            ->setParameter('roomType', $roomType)
            ->setParameter('date', $date->format('Y-m-d'))
            ->setParameter('status', DailyInventoryStatusEnum::AVAILABLE)
            ->setMaxResults($count)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * 检查指定日期范围内房型是否有足够库存
     *
     * 不考虑并发 - 在当前业务场景下，库存查询的并发冲突影响有限
     *
     * @param RoomType           $roomType      房型
     * @param \DateTimeInterface $startDate     开始日期
     * @param \DateTimeInterface $endDate       结束日期
     * @param int                $requiredCount 需要的每日库存数量
     *
     * @return bool 是否有足够库存
     */
    public function hasAvailableInventory(
        RoomType $roomType,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        int $requiredCount,
    ): bool {
        $currentDate = clone $startDate;
        $endDateCopy = clone $endDate;

        while ($currentDate <= $endDateCopy) {
            $availableCount = $this->inventoryRepository->count([
                'roomType' => $roomType,
                'date' => $currentDate,
                'status' => DailyInventoryStatusEnum::AVAILABLE,
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
                $currentDate = $nextDate->modify('+1 day');
            }
        }

        return true;
    }

    /**
     * 预留房型库存（用于预订）
     *
     * 不考虑并发 - 在当前业务场景下，库存预留操作频率较低，并发冲突概率小
     *
     * @param RoomType           $roomType  房型
     * @param \DateTimeInterface $startDate 开始日期
     * @param \DateTimeInterface $endDate   结束日期
     * @param int                $count     需要预留的数量
     *
     * @return array 预留的库存记录数组
     */
    /**
     * @return array<DailyInventory>
     */
    public function reserveInventories(
        RoomType $roomType,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        int $count,
    ): array {
        $reserved = [];

        // 检查是否有足够库存
        if (!$this->hasAvailableInventory($roomType, $startDate, $endDate, $count)) {
            throw new InsufficientInventoryException('指定日期范围内没有足够的可用库存');
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
                $currentDate = $nextDate->modify('+1 day');
            }
        }

        $this->entityManager->flush();

        return $reserved;
    }

    /**
     * 一键为房型创建库存
     *
     * 不考虑并发 - 库存创建通常在管理后台操作，并发频率低
     *
     * @param int                     $contractId     合同ID
     * @param int                     $roomTypeId     房型ID
     * @param int                     $inventoryCount 库存数量
     * @param \DateTimeInterface|null $startDate      开始日期
     * @param \DateTimeInterface|null $endDate        结束日期
     * @param float                   $costPrice      成本价
     * @param float                   $sellingPrice   销售价
     *
     * @return array 操作结果
     */
    /**
     * @return array<string, mixed>
     */
    public function oneClickGenerateRoomTypeInventory(
        int $contractId,
        int $roomTypeId,
        int $inventoryCount,
        ?\DateTimeInterface $startDate = null,
        ?\DateTimeInterface $endDate = null,
        float $costPrice = 0.0,
        float $sellingPrice = 0.0,
    ): array {
        // 获取合同和房型
        $contract = $this->hotelContractRepository->find($contractId);
        if (null === $contract) {
            return [
                'success' => false,
                'message' => '合同不存在',
                'created' => 0,
            ];
        }

        $roomType = $this->roomTypeService->findRoomTypeById($roomTypeId);
        if (null === $roomType) {
            return [
                'success' => false,
                'message' => '房型不存在',
                'created' => 0,
            ];
        }

        // 如果未指定日期，使用合同日期
        if (null === $startDate) {
            $startDate = $contract->getStartDate();
        }
        if (null === $endDate) {
            $endDate = $contract->getEndDate();
        }

        if (null === $startDate || null === $endDate) {
            return [
                'success' => false,
                'message' => '合同日期不完整，无法创建库存',
                'created' => 0,
            ];
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

        $createdCount = is_int($result['created'] ?? null) ? $result['created'] : 0;

        return [
            'success' => true,
            'message' => sprintf('成功创建 %d 条库存记录', $createdCount),
            'created' => $createdCount,
            'details' => $result,
        ];
    }

    /**
     * 通过 ID 查找库存
     *
     * 不考虑并发 - 简单的查询操作，不涉及状态变更
     */
    public function findInventoryById(int $inventoryId): ?DailyInventory
    {
        return $this->inventoryRepository->find($inventoryId);
    }

    /**
     * 验证并预留特定库存
     *
     * 不考虑并发 - 在当前业务场景下，库存预留冲突通过异常处理机制解决
     */
    public function validateAndReserveInventoryById(
        int $inventoryId,
        RoomType $roomType,
        string $dateStr,
    ): DailyInventory {
        $dailyInventory = $this->findInventoryById($inventoryId);

        if (null === $dailyInventory) {
            throw new InventoryNotFoundException("日期 {$dateStr} 选择的库存不存在");
        }

        // 验证库存是否匹配房型和日期
        $inventoryRoomType = $dailyInventory->getRoomType();
        $inventoryDate = $dailyInventory->getDate();
        if (
            null === $inventoryRoomType
            || null === $inventoryDate
            || $inventoryRoomType->getId() !== $roomType->getId()
            || $inventoryDate->format('Y-m-d') !== $dateStr
        ) {
            throw new InventoryMismatchException("日期 {$dateStr} 选择的库存不匹配");
        }

        // 检查库存状态 - 只能选择可用状态的库存
        if (DailyInventoryStatusEnum::AVAILABLE !== $dailyInventory->getStatus()) {
            throw new InventoryUnavailableException("日期 {$dateStr} 选择的库存已被占用或不可用，当前状态：{$dailyInventory->getStatus()->getLabel()}");
        }

        // 占用库存 - 设置为待确认状态
        $dailyInventory->setStatus(DailyInventoryStatusEnum::PENDING);
        $this->entityManager->persist($dailyInventory);

        return $dailyInventory;
    }

    /**
     * 释放库存（恢复为可用状态）
     *
     * 不考虑并发 - 库存释放操作相对简单，通过数据库约束保证一致性
     */
    public function releaseInventory(DailyInventory $dailyInventory): void
    {
        $dailyInventory->setStatus(DailyInventoryStatusEnum::AVAILABLE);
        $this->entityManager->persist($dailyInventory);
    }
}
