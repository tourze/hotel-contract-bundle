<?php

namespace Tourze\HotelContractBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Tourze\HotelContractBundle\Entity\InventorySummary;
use Tourze\HotelContractBundle\Enum\DailyInventoryStatusEnum;
use Tourze\HotelContractBundle\Enum\InventorySummaryStatusEnum;
use Tourze\HotelContractBundle\Repository\DailyInventoryRepository;
use Tourze\HotelContractBundle\Repository\InventorySummaryRepository;
use Tourze\HotelProfileBundle\Entity\Hotel;
use Tourze\HotelProfileBundle\Entity\RoomType;
use Tourze\HotelProfileBundle\Repository\HotelRepository;
use Tourze\HotelProfileBundle\Repository\RoomTypeRepository;

class InventorySummaryService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly DailyInventoryRepository $inventoryRepository,
        private readonly InventorySummaryRepository $inventorySummaryRepository,
        private readonly HotelRepository $hotelRepository,
        private readonly RoomTypeRepository $roomTypeRepository,
    ) {}

    /**
     * 同步库存统计数据
     *
     * @param \DateTimeInterface|null $date 指定日期，null表示处理未来一个月
     */
    public function syncInventorySummary(?\DateTimeInterface $date = null): array
    {
        try {
            $this->entityManager->getConnection()->beginTransaction();

            if ($date !== null) {
                // 处理指定日期
                $this->syncSingleDate($date);
                $message = sprintf('成功同步 %s 的库存统计数据', $date->format('Y-m-d'));
            } else {
                // 处理未来一个月的数据
                $startDate = new \DateTimeImmutable();
                $endDate = $startDate->modify('+1 month');

                $syncCount = $this->syncDateRange($startDate, $endDate);
                $message = sprintf('成功同步 %d 天的库存统计数据', $syncCount);
            }

            $this->entityManager->getConnection()->commit();

            return [
                'success' => true,
                'message' => $message,
            ];
        } catch (\Exception $e) {
            $this->entityManager->getConnection()->rollBack();

            return [
                'success' => false,
                'message' => '同步库存统计数据失败: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * 同步指定日期范围的库存统计
     */
    private function syncDateRange(\DateTimeInterface $startDate, \DateTimeInterface $endDate): int
    {
        $hotels = $this->hotelRepository->findAll();
        $roomTypes = $this->roomTypeRepository->findAll();

        $syncCount = 0;
        $currentDate = clone $startDate;

        while ($currentDate <= $endDate) {
            foreach ($hotels as $hotel) {
                $hotelRoomTypes = array_filter($roomTypes, function (RoomType $roomType) use ($hotel) {
                    return $roomType->getHotel()->getId() === $hotel->getId();
                });

                foreach ($hotelRoomTypes as $roomType) {
                    $this->updateDailyInventorySummary($hotel, $roomType, $currentDate);
                }
            }

            $syncCount++;
            $currentDate = new \DateTimeImmutable($currentDate->format('Y-m-d H:i:s'));
            $currentDate = $currentDate->modify('+1 day');
        }

        return $syncCount;
    }

    /**
     * 同步单个日期的库存统计
     */
    private function syncSingleDate(\DateTimeInterface $date): void
    {
        // 获取所有酒店和房型的库存统计
        $existingSummaries = $this->inventorySummaryRepository
            ->createQueryBuilder('is')
            ->leftJoin('is.hotel', 'h')
            ->leftJoin('is.roomType', 'rt')
            ->addSelect('h', 'rt')
            ->where('is.date = :date')
            ->setParameter('date', $date)
            ->getQuery()
            ->getResult();

        // 获取需要重新计算的酒店和房型组合
        $hotelsAndRoomTypes = [];
        foreach ($existingSummaries as $summary) {
            $key = $summary->getHotel()->getId() . '_' . $summary->getRoomType()->getId();
            $hotelsAndRoomTypes[$key] = [
                'hotel' => $summary->getHotel(),
                'roomType' => $summary->getRoomType(),
            ];
        }

        // 如果没有现有的统计记录，则查找当天有库存记录的酒店和房型
        if (empty($hotelsAndRoomTypes)) {
            $inventories = $this->inventoryRepository
                ->createQueryBuilder('di')
                ->leftJoin('di.hotel', 'h')
                ->leftJoin('di.roomType', 'rt')
                ->addSelect('h', 'rt')
                ->where('di.date = :date')
                ->setParameter('date', $date)
                ->getQuery()
                ->getResult();

            foreach ($inventories as $inventory) {
                $key = $inventory->getHotel()->getId() . '_' . $inventory->getRoomType()->getId();
                $hotelsAndRoomTypes[$key] = [
                    'hotel' => $inventory->getHotel(),
                    'roomType' => $inventory->getRoomType(),
                ];
            }
        }

        // 更新每个酒店+房型组合的库存统计
        foreach ($hotelsAndRoomTypes as $combination) {
            $this->updateDailyInventorySummary(
                $combination['hotel'],
                $combination['roomType'],
                $date
            );
        }
    }

    /**
     * 更新库存统计数据（指定酒店、房型和日期范围）
     */
    public function updateInventorySummary(Hotel $hotel, RoomType $roomType, \DateTimeInterface $startDate, \DateTimeInterface $endDate): void
    {
        $currentDate = clone $startDate;

        while ($currentDate <= $endDate) {
            $this->updateDailyInventorySummary($hotel, $roomType, $currentDate);
            $currentDate = new \DateTimeImmutable($currentDate->format('Y-m-d H:i:s'));
            $currentDate = $currentDate->modify('+1 day');
        }
    }

    /**
     * 更新每日库存统计数据
     */
    public function updateDailyInventorySummary(Hotel $hotel, RoomType $roomType, \DateTimeInterface $date): void
    {
        // 查找现有的库存统计记录
        $summary = $this->inventorySummaryRepository
            ->findByHotelRoomTypeAndDate($hotel->getId(), $roomType->getId(), $date);

        if ($summary === null) {
            $summary = new InventorySummary();
            $summary->setHotel($hotel);
            $summary->setRoomType($roomType);
            $summary->setDate($date);
            $this->entityManager->persist($summary);
        }

        // 统计该日期的库存数据
        $inventoryStats = $this->inventoryRepository
            ->createQueryBuilder('di')
            ->select([
                'COUNT(di.id) as totalCount',
                'SUM(CASE WHEN di.status = :available THEN 1 ELSE 0 END) as availableCount',
                'SUM(CASE WHEN di.status = :reserved THEN 1 ELSE 0 END) as reservedCount',
                'SUM(CASE WHEN di.status = :sold THEN 1 ELSE 0 END) as soldCount',
                'SUM(CASE WHEN di.status = :pending THEN 1 ELSE 0 END) as pendingCount',
                'MIN(CASE WHEN di.status = :available AND di.costPrice > 0 THEN di.costPrice ELSE NULL END) as lowestPrice'
            ])
            ->where('di.hotel = :hotel')
            ->andWhere('di.roomType = :roomType')
            ->andWhere('di.date = :date')
            ->setParameter('hotel', $hotel)
            ->setParameter('roomType', $roomType)
            ->setParameter('date', $date)
            ->setParameter('available', DailyInventoryStatusEnum::AVAILABLE)
            ->setParameter('reserved', DailyInventoryStatusEnum::RESERVED)
            ->setParameter('sold', DailyInventoryStatusEnum::SOLD)
            ->setParameter('pending', DailyInventoryStatusEnum::PENDING)
            ->getQuery()
            ->getSingleResult();

        // 更新统计数据
        $summary->setTotalRooms((int)$inventoryStats['totalCount']);
        $summary->setAvailableRooms((int)$inventoryStats['availableCount']);
        $summary->setReservedRooms((int)$inventoryStats['reservedCount']);
        $summary->setSoldRooms((int)$inventoryStats['soldCount']);
        $summary->setPendingRooms((int)$inventoryStats['pendingCount']);

        // 设置最低价格和对应的合同
        if ($inventoryStats['lowestPrice'] !== null) {
            $summary->setLowestPrice((string)$inventoryStats['lowestPrice']);

            // 查找最低价格对应的合同
            $lowestPriceData = $this->findLowestPriceForDate($hotel, $roomType, $date);
            if ($lowestPriceData !== null) {
                $summary->setLowestContract($lowestPriceData['contract']);
            }
        } else {
            $summary->setLowestPrice(null);
            $summary->setLowestContract(null);
        }

        $this->entityManager->flush();
    }

    /**
     * 查找指定日期的最低价格及对应合同
     */
    private function findLowestPriceForDate(Hotel $hotel, RoomType $roomType, \DateTimeInterface $date): ?array
    {
        $result = $this->inventoryRepository
            ->createQueryBuilder('di')
            ->leftJoin('di.contract', 'c')
            ->addSelect('c')
            ->where('di.hotel = :hotel')
            ->andWhere('di.roomType = :roomType')
            ->andWhere('di.date = :date')
            ->andWhere('di.status = :status')
            ->andWhere('di.costPrice > 0')
            ->setParameter('hotel', $hotel)
            ->setParameter('roomType', $roomType)
            ->setParameter('date', $date)
            ->setParameter('status', DailyInventoryStatusEnum::AVAILABLE)
            ->orderBy('di.costPrice', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($result === null) {
            return null;
        }

        return [
            'price' => $result->getCostPrice(),
            'contract' => $result->getContract(),
        ];
    }

    /**
     * 批量更新库存统计状态
     */
    public function updateInventorySummaryStatus(int $warningThreshold = 10): array
    {
        $summaries = $this->inventorySummaryRepository->findAll();
        $updatedCount = 0;

        foreach ($summaries as $summary) {
            $oldStatus = $summary->getStatus();

            // 重新计算状态（内部方法会自动调用）
            $totalRooms = $summary->getTotalRooms();
            $availableRooms = $summary->getAvailableRooms();

            if ($totalRooms <= 0 || $availableRooms <= 0) {
                $newStatus = InventorySummaryStatusEnum::SOLD_OUT;
            } elseif (($availableRooms / $totalRooms) * 100 <= $warningThreshold) {
                $newStatus = InventorySummaryStatusEnum::WARNING;
            } else {
                $newStatus = InventorySummaryStatusEnum::NORMAL;
            }

            if ($oldStatus !== $newStatus) {
                $summary->setStatus($newStatus);
                $updatedCount++;
            }
        }

        $this->entityManager->flush();

        return [
            'success' => true,
            'updated_count' => $updatedCount,
            'message' => sprintf('成功更新 %d 条库存统计记录的状态', $updatedCount),
        ];
    }
}
