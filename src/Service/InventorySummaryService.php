<?php

namespace Tourze\HotelContractBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Tourze\HotelContractBundle\Config\InventoryConfig;
use Tourze\HotelContractBundle\Entity\DailyInventory;
use Tourze\HotelContractBundle\Entity\HotelContract;
use Tourze\HotelContractBundle\Entity\InventorySummary;
use Tourze\HotelContractBundle\Enum\DailyInventoryStatusEnum;
use Tourze\HotelContractBundle\Enum\InventorySummaryStatusEnum;
use Tourze\HotelContractBundle\Repository\DailyInventoryRepository;
use Tourze\HotelContractBundle\Repository\InventorySummaryRepository;
use Tourze\HotelProfileBundle\Entity\Hotel;
use Tourze\HotelProfileBundle\Entity\RoomType;

class InventorySummaryService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly DailyInventoryRepository $inventoryRepository,
        private readonly InventorySummaryRepository $inventorySummaryRepository,
    ) {
    }

    /**
     * 同步库存统计
     *
     * @param \DateTimeInterface|null $date 指定日期，为空则处理所有日期
     *
     * @return array 操作结果
     */
    public function syncInventorySummary(?\DateTimeInterface $date = null): array
    {
        // 构建查询条件
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('IDENTITY(di.hotel) as hotelId', 'IDENTITY(di.roomType) as roomTypeId', 'di.date as date')
            ->addSelect('COUNT(di.id) as totalRooms')
            ->addSelect('SUM(CASE WHEN di.status = :statusAvailable THEN 1 ELSE 0 END) as availableRooms')
            ->addSelect('SUM(CASE WHEN di.isReserved = true THEN 1 ELSE 0 END) as reservedRooms')
            ->addSelect('SUM(CASE WHEN di.status = :statusSold THEN 1 ELSE 0 END) as soldRooms')
            ->addSelect('SUM(CASE WHEN di.status = :statusPending THEN 1 ELSE 0 END) as pendingRooms')
            ->addSelect('MIN(di.costPrice) as lowestPrice')
            ->from(DailyInventory::class, 'di')
            ->groupBy('di.hotel', 'di.roomType', 'di.date')
            ->setParameter('statusAvailable', DailyInventoryStatusEnum::AVAILABLE->value)
            ->setParameter('statusSold', DailyInventoryStatusEnum::SOLD->value)
            ->setParameter('statusPending', DailyInventoryStatusEnum::PENDING->value);

        if ($date !== null) {
            $qb->andWhere('di.date = :date')
                ->setParameter('date', $date->format('Y-m-d'));
        }

        $results = $qb->getQuery()->getResult();
        $updatedCount = 0;
        $createdCount = 0;

        foreach ($results as $result) {
            // 查找酒店和房型实体
            $hotel = $this->entityManager->getRepository(Hotel::class)->find($result['hotelId']);
            $roomType = $this->entityManager->getRepository(RoomType::class)->find($result['roomTypeId']);

            if (!$hotel || !$roomType) {
                continue;
            }

            // 查找是否已存在汇总记录
            $summary = $this->entityManager->getRepository(InventorySummary::class)->findOneBy([
                'hotel' => $hotel,
                'roomType' => $roomType,
                'date' => $result['date']
            ]);

            if (!$summary) {
                // 创建新的汇总记录
                $summary = new InventorySummary();
                $summary->setHotel($hotel)
                    ->setRoomType($roomType)
                    ->setDate($result['date']);
                $createdCount++;
            } else {
                $updatedCount++;
            }

            // 更新统计数据
            $summary->setTotalRooms((int)$result['totalRooms'])
                ->setAvailableRooms((int)$result['availableRooms'])
                ->setReservedRooms((int)$result['reservedRooms'])
                ->setSoldRooms((int)$result['soldRooms'])
                ->setPendingRooms((int)$result['pendingRooms'])
                ->setLowestPrice($result['lowestPrice'] ?: '0.00');

            // 计算状态
            $availablePercent = $result['totalRooms'] > 0 ? ($result['availableRooms'] / $result['totalRooms'] * 100) : 0;

            // 获取预警阈值
            $warningConfig = InventoryConfig::getWarningConfig();
            $warningThreshold = $warningConfig['warning_threshold'] ?? 10;

            if ($availablePercent <= 0) {
                $summary->setStatus(InventorySummaryStatusEnum::SOLD_OUT);
            } elseif ($availablePercent <= $warningThreshold) {
                $summary->setStatus(InventorySummaryStatusEnum::WARNING);
            } else {
                $summary->setStatus(InventorySummaryStatusEnum::NORMAL);
            }

            // 查找最低价格对应的合同
            if ($result['lowestPrice']) {
                $lowestPriceInventory = $this->inventoryRepository->createQueryBuilder('di')
                    ->where('di.hotel = :hotel')
                    ->andWhere('di.roomType = :roomType')
                    ->andWhere('di.date = :date')
                    ->andWhere('di.costPrice = :price')
                    ->setParameter('hotel', $hotel)
                    ->setParameter('roomType', $roomType)
                    ->setParameter('date', $result['date'])
                    ->setParameter('price', $result['lowestPrice'])
                    ->setMaxResults(1)
                    ->getQuery()
                    ->getOneOrNullResult();

                if ($lowestPriceInventory && $lowestPriceInventory->getContract()) {
                    $summary->setLowestPrice($lowestPriceInventory->getCostPrice());
                    $contractEntity = $this->entityManager->getRepository(HotelContract::class)->find($lowestPriceInventory->getContract()->getId());
                    if ($contractEntity) {
                        $summary->setLowestContract($contractEntity);
                    }
                }
            }

            $this->entityManager->persist($summary);
        }

        $this->entityManager->flush();

        return [
            'success' => true,
            'message' => sprintf('库存统计同步完成，新建%d条记录，更新%d条记录', $createdCount, $updatedCount),
            'created_count' => $createdCount,
            'updated_count' => $updatedCount
        ];
    }

    /**
     * 更新指定日期范围内的库存统计
     *
     * @param Hotel $hotel 酒店
     * @param RoomType $roomType 房型
     * @param \DateTimeInterface $startDate 开始日期
     * @param \DateTimeInterface $endDate 结束日期
     */
    public function updateInventorySummary(Hotel $hotel, RoomType $roomType, \DateTimeInterface $startDate, \DateTimeInterface $endDate): void
    {
        $currentDate = clone $startDate;
        $endDateCopy = clone $endDate;

        while ($currentDate <= $endDateCopy) {
            $this->updateDailyInventorySummary($hotel, $roomType, clone $currentDate);
            $currentDate = new \DateTime($currentDate->format('Y-m-d'));
            $currentDate->modify('+1 day');
        }
    }

    /**
     * 更新单日库存统计
     *
     * @param Hotel $hotel 酒店
     * @param RoomType $roomType 房型
     * @param \DateTimeInterface $date 日期
     */
    public function updateDailyInventorySummary(Hotel $hotel, RoomType $roomType, \DateTimeInterface $date): void
    {
        // 查找当天该酒店+房型的库存统计记录
        $summary = $this->inventorySummaryRepository->findOneBy([
            'hotel' => $hotel,
            'roomType' => $roomType,
            'date' => $date
        ]);

        // 如果不存在则创建
        if (!$summary) {
            $summary = new InventorySummary();
            $summary->setHotel($hotel);
            $summary->setRoomType($roomType);
            $summary->setDate($date);
            $this->entityManager->persist($summary);
        }

        // 统计总房间数
        $totalRooms = $this->entityManager->getRepository(DailyInventory::class)
            ->createQueryBuilder('di')
            ->select('COUNT(di.id)')
            ->where('di.hotel = :hotel')
            ->andWhere('di.roomType = :roomType')
            ->andWhere('di.date = :date')
            ->setParameter('hotel', $hotel)
            ->setParameter('roomType', $roomType)
            ->setParameter('date', $date->format('Y-m-d'))
            ->getQuery()
            ->getSingleScalarResult();

        $summary->setTotalRooms($totalRooms);

        // 统计可用房间数
        $availableRooms = $this->entityManager->getRepository(DailyInventory::class)
            ->createQueryBuilder('di')
            ->select('COUNT(di.id)')
            ->where('di.hotel = :hotel')
            ->andWhere('di.roomType = :roomType')
            ->andWhere('di.date = :date')
            ->andWhere('di.status = :status')
            ->setParameter('hotel', $hotel)
            ->setParameter('roomType', $roomType)
            ->setParameter('date', $date->format('Y-m-d'))
            ->setParameter('status', DailyInventoryStatusEnum::AVAILABLE)
            ->getQuery()
            ->getSingleScalarResult();

        // 统计预留房间数
        $reservedRooms = $this->entityManager->getRepository(DailyInventory::class)
            ->createQueryBuilder('di')
            ->select('COUNT(di.id)')
            ->where('di.hotel = :hotel')
            ->andWhere('di.roomType = :roomType')
            ->andWhere('di.date = :date')
            ->andWhere('di.isReserved = :isReserved')
            ->setParameter('hotel', $hotel)
            ->setParameter('roomType', $roomType)
            ->setParameter('date', $date->format('Y-m-d'))
            ->setParameter('isReserved', true)
            ->getQuery()
            ->getSingleScalarResult();

        // 统计已售房间数
        $soldRooms = $this->entityManager->getRepository(DailyInventory::class)
            ->createQueryBuilder('di')
            ->select('COUNT(di.id)')
            ->where('di.hotel = :hotel')
            ->andWhere('di.roomType = :roomType')
            ->andWhere('di.date = :date')
            ->andWhere('di.status = :status')
            ->setParameter('hotel', $hotel)
            ->setParameter('roomType', $roomType)
            ->setParameter('date', $date->format('Y-m-d'))
            ->setParameter('status', DailyInventoryStatusEnum::SOLD)
            ->getQuery()
            ->getSingleScalarResult();

        // 统计待确认房间数
        $pendingRooms = $this->entityManager->getRepository(DailyInventory::class)
            ->createQueryBuilder('di')
            ->select('COUNT(di.id)')
            ->where('di.hotel = :hotel')
            ->andWhere('di.roomType = :roomType')
            ->andWhere('di.date = :date')
            ->andWhere('di.status = :status')
            ->setParameter('hotel', $hotel)
            ->setParameter('roomType', $roomType)
            ->setParameter('date', $date->format('Y-m-d'))
            ->setParameter('status', DailyInventoryStatusEnum::PENDING)
            ->getQuery()
            ->getSingleScalarResult();

        // 更新库存统计
        $summary->setTotalRooms($totalRooms);
        $summary->setAvailableRooms($availableRooms);
        $summary->setReservedRooms($reservedRooms);
        $summary->setSoldRooms($soldRooms);
        $summary->setPendingRooms($pendingRooms);

        // 获取预警阈值
        $warningConfig = InventoryConfig::getWarningConfig();
        $warningThreshold = $warningConfig['warning_threshold'] ?? 10;

        // 设置状态
        $availablePercentage = $totalRooms > 0 ? ($availableRooms / $totalRooms) * 100 : 0;
        if ($availableRooms == 0) {
            $summary->setStatus(InventorySummaryStatusEnum::SOLD_OUT);
        } elseif ($availablePercentage <= $warningThreshold) {
            $summary->setStatus(InventorySummaryStatusEnum::WARNING);
        } else {
            $summary->setStatus(InventorySummaryStatusEnum::NORMAL);
        }

        // 查找最低价合同
        $lowestPrice = $this->findLowestPriceForDate($hotel, $roomType, $date);
        if ($lowestPrice) {
            $summary->setLowestPrice($lowestPrice['price']);
            $contractEntity = $this->entityManager->getRepository(HotelContract::class)->find($lowestPrice['contractId']);
            if ($contractEntity) {
                $summary->setLowestContract($contractEntity);
            }
        }

        $this->entityManager->flush();
    }

    /**
     * 查找指定日期的最低价合同
     *
     * @param Hotel $hotel 酒店
     * @param RoomType $roomType 房型
     * @param \DateTimeInterface $date 日期
     * @return array|null 最低价格和合同ID
     */
    private function findLowestPriceForDate(Hotel $hotel, RoomType $roomType, \DateTimeInterface $date): ?array
    {
        $result = $this->entityManager->getRepository(DailyInventory::class)
            ->createQueryBuilder('di')
            ->select('MIN(di.costPrice) as price, IDENTITY(di.contract) as contractId')
            ->where('di.hotel = :hotel')
            ->andWhere('di.roomType = :roomType')
            ->andWhere('di.date = :date')
            ->setParameter('hotel', $hotel)
            ->setParameter('roomType', $roomType)
            ->setParameter('date', $date->format('Y-m-d'))
            ->groupBy('di.contract')
            ->orderBy('price', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$result || !isset($result['price']) || !isset($result['contractId'])) {
            return null;
        }

        return [
            'price' => $result['price'],
            'contractId' => $result['contractId']
        ];
    }

    /**
     * 根据预警阈值更新所有库存统计状态
     *
     * @param int $warningThreshold 预警阈值(百分比)
     * @return array 处理结果
     */
    public function updateInventorySummaryStatus(int $warningThreshold = 10): array
    {
        // 获取所有库存统计
        $inventorySummaries = $this->entityManager->getRepository(InventorySummary::class)
            ->findAll();

        if (empty($inventorySummaries)) {
            return [
                'success' => true,
                'message' => '未找到需要更新的库存统计记录',
                'updated_count' => 0
            ];
        }

        $updatedCount = 0;

        foreach ($inventorySummaries as $summary) {
            $originalStatus = $summary->getStatus();
            $totalRooms = $summary->getTotalRooms();
            $availableRooms = $summary->getAvailableRooms();

            // 计算状态
            if ($totalRooms <= 0 || $availableRooms <= 0) {
                $newStatus = InventorySummaryStatusEnum::SOLD_OUT;
            } elseif (($availableRooms / $totalRooms * 100) <= $warningThreshold) {
                $newStatus = InventorySummaryStatusEnum::WARNING;
            } else {
                $newStatus = InventorySummaryStatusEnum::NORMAL;
            }

            // 如果状态有变化，更新记录
            if ($originalStatus !== $newStatus) {
                $summary->setStatus($newStatus);
                $this->entityManager->persist($summary);
                $updatedCount++;
            }
        }

        if ($updatedCount > 0) {
            $this->entityManager->flush();
        }

        return [
            'success' => true,
            'message' => sprintf('成功更新%d条库存统计记录状态', $updatedCount),
            'updated_count' => $updatedCount
        ];
    }
}
