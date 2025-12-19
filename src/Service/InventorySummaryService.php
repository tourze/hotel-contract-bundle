<?php

namespace Tourze\HotelContractBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Tourze\HotelContractBundle\Entity\DailyInventory;
use Tourze\HotelContractBundle\Entity\HotelContract;
use Tourze\HotelContractBundle\Entity\InventorySummary;
use Tourze\HotelContractBundle\Enum\DailyInventoryStatusEnum;
use Tourze\HotelContractBundle\Enum\InventorySummaryStatusEnum;
use Tourze\HotelContractBundle\Exception\InvalidEntityException;
use Tourze\HotelContractBundle\Repository\DailyInventoryRepository;
use Tourze\HotelContractBundle\Repository\InventorySummaryRepository;
use Tourze\HotelProfileBundle\Entity\Hotel;
use Tourze\HotelProfileBundle\Entity\RoomType;
use Tourze\HotelProfileBundle\Service\HotelService;
use Tourze\HotelProfileBundle\Service\RoomTypeService;

#[Autoconfigure(public: true)]
readonly final class InventorySummaryService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DailyInventoryRepository $inventoryRepository,
        private InventorySummaryRepository $inventorySummaryRepository,
        private HotelService $hotelService,
        private RoomTypeService $roomTypeService,
    ) {
    }

    /**
     * 同步库存统计数据
     *
     * @param \DateTimeInterface|null $date 指定日期，null表示处理未来一个月
     */
    /**
     * @return array<string, mixed>
     */
    public function syncInventorySummary(?\DateTimeInterface $date = null): array
    {
        try {
            $this->entityManager->getConnection()->beginTransaction();

            $processedCount = 0;
            $summaryCount = 0;

            if (null !== $date) {
                // 处理指定日期
                $processed = $this->syncSingleDate($date);
                $processedCount = is_int($processed['processed_count'] ?? null) ? $processed['processed_count'] : 0;
                $summaryCount = is_int($processed['summary_count'] ?? null) ? $processed['summary_count'] : 0;
            } else {
                // 处理未来一个月的数据
                $startDate = new \DateTimeImmutable();
                $endDate = $startDate->modify('+1 month');

                $result = $this->syncDateRange($startDate, $endDate);
                $processedCount = is_int($result['processed_count'] ?? null) ? $result['processed_count'] : 0;
                $summaryCount = is_int($result['summary_count'] ?? null) ? $result['summary_count'] : 0;
            }

            $this->entityManager->getConnection()->commit();

            return [
                'success' => true,
                'processed_count' => $processedCount,
                'summary_count' => $summaryCount,
                'message' => sprintf('成功同步 %d 天的库存统计数据，生成 %d 条统计记录', $processedCount, $summaryCount),
            ];
        } catch (\Exception $e) {
            $this->entityManager->getConnection()->rollBack();

            return [
                'success' => false,
                'processed_count' => 0,
                'summary_count' => 0,
                'message' => '同步库存统计数据失败: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * 同步指定日期范围的库存统计
     *
     * @return array{processed_count: int, summary_count: int}
     */
    private function syncDateRange(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        $hotels = $this->hotelService->findAllHotels();
        $roomTypes = $this->roomTypeService->findAllRoomTypes();

        $processedCount = 0;
        $summaryCount = 0;
        $currentDate = clone $startDate;

        while ($currentDate <= $endDate) {
            foreach ($hotels as $hotel) {
                $hotelRoomTypes = array_filter($roomTypes, function (RoomType $roomType) use ($hotel) {
                    $roomTypeHotel = $roomType->getHotel();

                    return null !== $roomTypeHotel && $roomTypeHotel->getId() === $hotel->getId();
                });

                foreach ($hotelRoomTypes as $roomType) {
                    $this->updateDailyInventorySummary($hotel, $roomType, $currentDate);
                    ++$summaryCount;
                }
            }

            ++$processedCount;
            $currentDate = new \DateTimeImmutable($currentDate->format('Y-m-d H:i:s'));
            $currentDate = $currentDate->modify('+1 day');
        }

        return [
            'processed_count' => $processedCount,
            'summary_count' => $summaryCount,
        ];
    }

    /**
     * 同步单个日期的库存统计
     *
     * @return array{processed_count: int, summary_count: int}
     */
    private function syncSingleDate(\DateTimeInterface $date): array
    {
        $hotelsAndRoomTypes = $this->getHotelsAndRoomTypesForDate($date);
        $summaryCount = $this->updateAllDailyInventorySummaries($hotelsAndRoomTypes, $date);

        return [
            'processed_count' => 1,
            'summary_count' => $summaryCount,
        ];
    }

    /**
     * 获取指定日期的酒店和房型组合
     *
     * @return array<string, array{hotel: Hotel, roomType: RoomType}>
     */
    private function getHotelsAndRoomTypesForDate(\DateTimeInterface $date): array
    {
        /** @var array<InventorySummary> $existingSummaries */
        $existingSummaries = $this->inventorySummaryRepository
            ->createQueryBuilder('is')
            ->leftJoin('is.hotel', 'h')
            ->leftJoin('is.roomType', 'rt')
            ->addSelect('h', 'rt')
            ->where('is.date = :date')
            ->setParameter('date', $date)
            ->getQuery()
            ->getResult()
        ;

        $hotelsAndRoomTypes = $this->extractHotelsAndRoomTypesFromSummaries($existingSummaries);

        if ([] === $hotelsAndRoomTypes) {
            $hotelsAndRoomTypes = $this->extractHotelsAndRoomTypesFromInventories($date);
        }

        return $hotelsAndRoomTypes;
    }

    /**
     * 从库存统计中提取酒店和房型组合
     *
     * @param array<InventorySummary> $summaries
     *
     * @return array<string, array{hotel: Hotel, roomType: RoomType}>
     */
    private function extractHotelsAndRoomTypesFromSummaries(array $summaries): array
    {
        $hotelsAndRoomTypes = [];
        foreach ($summaries as $summary) {
            if (!($summary instanceof InventorySummary)) {
                continue;
            }
            $combination = $this->extractHotelAndRoomType($summary->getHotel(), $summary->getRoomType());
            if (null !== $combination) {
                $hotelsAndRoomTypes[$combination['key']] = $combination['data'];
            }
        }

        return $hotelsAndRoomTypes;
    }

    /**
     * 从库存记录中提取酒店和房型组合
     *
     * @return array<string, array{hotel: Hotel, roomType: RoomType}>
     */
    private function extractHotelsAndRoomTypesFromInventories(\DateTimeInterface $date): array
    {
        /** @var array<DailyInventory> $inventories */
        $inventories = $this->inventoryRepository
            ->createQueryBuilder('di')
            ->leftJoin('di.hotel', 'h')
            ->leftJoin('di.roomType', 'rt')
            ->addSelect('h', 'rt')
            ->where('di.date = :date')
            ->setParameter('date', $date)
            ->getQuery()
            ->getResult()
        ;

        $hotelsAndRoomTypes = [];
        foreach ($inventories as $inventory) {
            if (!($inventory instanceof DailyInventory)) {
                continue;
            }
            $combination = $this->extractHotelAndRoomType($inventory->getHotel(), $inventory->getRoomType());
            if (null !== $combination) {
                $hotelsAndRoomTypes[$combination['key']] = $combination['data'];
            }
        }

        return $hotelsAndRoomTypes;
    }

    /**
     * 提取酒店和房型信息
     *
     * @return array{key: string, data: array{hotel: Hotel, roomType: RoomType}}|null
     */
    private function extractHotelAndRoomType(?Hotel $hotel, ?RoomType $roomType): ?array
    {
        if (null === $hotel || null === $roomType) {
            return null;
        }
        $hotelId = $hotel->getId();
        $roomTypeId = $roomType->getId();
        if (null === $hotelId || null === $roomTypeId) {
            return null;
        }

        return [
            'key' => $hotelId . '_' . $roomTypeId,
            'data' => [
                'hotel' => $hotel,
                'roomType' => $roomType,
            ],
        ];
    }

    /**
     * 更新所有酒店+房型组合的库存统计
     *
     * @param array<string, array{hotel: Hotel, roomType: RoomType}> $hotelsAndRoomTypes
     */
    private function updateAllDailyInventorySummaries(array $hotelsAndRoomTypes, \DateTimeInterface $date): int
    {
        $summaryCount = 0;
        foreach ($hotelsAndRoomTypes as $combination) {
            if (!isset($combination['hotel'], $combination['roomType'])) {
                continue;
            }
            if (!($combination['hotel'] instanceof Hotel) || !($combination['roomType'] instanceof RoomType)) {
                continue;
            }
            $this->updateDailyInventorySummary(
                $combination['hotel'],
                $combination['roomType'],
                $date
            );
            ++$summaryCount;
        }

        return $summaryCount;
    }

    /**
     * 更新库存统计数据（指定酒店、房型和日期范围）
     * 不考虑并发
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
     * 不考虑并发
     */
    public function updateDailyInventorySummary(Hotel $hotel, RoomType $roomType, \DateTimeInterface $date): void
    {
        // 查找现有的库存统计记录
        $hotelId = $hotel->getId();
        $roomTypeId = $roomType->getId();

        if (null === $hotelId || null === $roomTypeId) {
            throw new InvalidEntityException('酒店ID或房型ID不能为空');
        }

        $summary = $this->inventorySummaryRepository
            ->findByHotelRoomTypeAndDate($hotelId, $roomTypeId, $date)
        ;

        if (null === $summary) {
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
            ->getSingleResult()
        ;

        // 单独查询最低价格
        $lowestPriceResult = $this->inventoryRepository
            ->createQueryBuilder('di')
            ->select('MIN(di.costPrice) as lowestPrice')
            ->where('di.hotel = :hotel')
            ->andWhere('di.roomType = :roomType')
            ->andWhere('di.date = :date')
            ->andWhere('di.status = :available')
            ->andWhere('di.costPrice > 0')
            ->setParameter('hotel', $hotel)
            ->setParameter('roomType', $roomType)
            ->setParameter('date', $date)
            ->setParameter('available', DailyInventoryStatusEnum::AVAILABLE)
            ->getQuery()
            ->getSingleScalarResult()
        ;

        /** @var array{totalCount: int|string, availableCount: int|string, reservedCount: int|string, soldCount: int|string, pendingCount: int|string, lowestPrice: string|int|null} $inventoryStats */
        $inventoryStats['lowestPrice'] = $lowestPriceResult;

        // 更新统计数据
        $summary->setTotalRooms((int) $inventoryStats['totalCount']);
        $summary->setAvailableRooms((int) $inventoryStats['availableCount']);
        $summary->setReservedRooms((int) $inventoryStats['reservedCount']);
        $summary->setSoldRooms((int) $inventoryStats['soldCount']);
        $summary->setPendingRooms((int) $inventoryStats['pendingCount']);

        // 设置最低价格和对应的合同
        if (null !== $inventoryStats['lowestPrice']) {
            $summary->setLowestPrice((string) $inventoryStats['lowestPrice']);

            // 查找最低价格对应的合同
            $lowestPriceData = $this->findLowestPriceForDate($hotel, $roomType, $date);
            if (null !== $lowestPriceData && isset($lowestPriceData['contract'])) {
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
     *
     * @return array{price: string, contract: HotelContract|null}|null
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
            ->getOneOrNullResult()
        ;

        if (null === $result || !($result instanceof DailyInventory)) {
            return null;
        }

        return [
            'price' => $result->getCostPrice(),
            'contract' => $result->getContract(),
        ];
    }

    /**
     * 批量更新库存统计状态
     * 不考虑并发
     */
    /**
     * @return array<string, mixed>
     */
    public function updateInventorySummaryStatus(int $warningThreshold = 10): array
    {
        $summaries = $this->inventorySummaryRepository->findAll();
        $updatedCount = 0;
        $warningCount = 0;

        foreach ($summaries as $summary) {
            if (!($summary instanceof InventorySummary)) {
                continue;
            }
            $oldStatus = $summary->getStatus();

            // 重新计算状态（内部方法会自动调用）
            $totalRooms = $summary->getTotalRooms();
            $availableRooms = $summary->getAvailableRooms();

            if ($totalRooms <= 0 || $availableRooms <= 0) {
                $newStatus = InventorySummaryStatusEnum::SOLD_OUT;
            } elseif (0.0 !== (float) $totalRooms && (($availableRooms / $totalRooms) * 100 <= $warningThreshold)) {
                $newStatus = InventorySummaryStatusEnum::WARNING;
                ++$warningCount;
            } else {
                $newStatus = InventorySummaryStatusEnum::NORMAL;
            }

            if ($oldStatus !== $newStatus) {
                $summary->setStatus($newStatus);
                ++$updatedCount;
            }
        }

        $this->entityManager->flush();

        return [
            'success' => true,
            'processed_count' => count($summaries),
            'warning_count' => $warningCount,
            'updated_count' => $updatedCount,
            'message' => sprintf('成功更新 %d 条库存统计记录的状态，其中 %d 条为警告状态', $updatedCount, $warningCount),
        ];
    }
}
