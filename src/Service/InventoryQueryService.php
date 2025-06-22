<?php

namespace Tourze\HotelContractBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Tourze\HotelContractBundle\Entity\DailyInventory;
use Tourze\HotelContractBundle\Enum\DailyInventoryStatusEnum;
use Tourze\HotelContractBundle\Enum\InventorySummaryStatusEnum;
use Tourze\HotelContractBundle\Repository\InventorySummaryRepository;
use Tourze\HotelProfileBundle\Entity\RoomType;
use Tourze\HotelProfileBundle\Repository\RoomTypeRepository;

/**
 * 库存查询服务
 */
class InventoryQueryService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RoomTypeRepository $roomTypeRepository,
        private readonly InventorySummaryRepository $inventorySummaryRepository,
    ) {}

    /**
     * 获取指定房型在特定日期范围内的库存信息
     */
    public function getInventoryData(
        int $roomTypeId,
        string $checkInDate,
        string $checkOutDate,
        int $roomCount
    ): array {
        $roomType = $this->roomTypeRepository->find($roomTypeId);
        if ($roomType === null) {
            return [
                'success' => false,
                'message' => '房型不存在',
                'data' => [],
            ];
        }

        try {
            $startDate = new \DateTimeImmutable($checkInDate);
            $endDate = new \DateTimeImmutable($checkOutDate);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => '日期格式错误',
                'data' => [],
            ];
        }

        // 获取每日库存信息
        $dailyInventories = [];
        $currentDate = clone $startDate;

        while ($currentDate < $endDate) {
            $dayData = $this->getDayInventoryData($roomType, $currentDate, $roomCount);
            $dailyInventories[] = $dayData;
            $currentDate = $currentDate->modify('+1 day');
        }

        // 标记默认选择的库存
        $this->markDefaultInventory($dailyInventories, $roomCount);

        return [
            'success' => true,
            'message' => '查询成功',
            'data' => [
                'roomType' => $this->formatRoomTypeData($roomType),
                'checkInDate' => $checkInDate,
                'checkOutDate' => $checkOutDate,
                'roomCount' => $roomCount,
                'dailyInventories' => $dailyInventories,
                'totalDays' => count($dailyInventories),
            ],
        ];
    }

    /**
     * 获取指定日期的库存数据
     */
    private function getDayInventoryData(RoomType $roomType, \DateTimeInterface $date, int $roomCount): array
    {
        // 获取库存汇总信息
        $summary = $this->inventorySummaryRepository
            ->findByHotelRoomTypeAndDate(
                $roomType->getHotel()->getId(),
                $roomType->getId(),
                $date
            );

        // 获取该日具体的库存记录
        $inventories = $this->entityManager->createQueryBuilder()
            ->select('di')
            ->from(DailyInventory::class, 'di')
            ->where('di.roomType = :roomType')
            ->andWhere('di.date = :date')
            ->andWhere('di.status = :status')
            ->setParameter('roomType', $roomType)
            ->setParameter('date', $date)
            ->setParameter('status', DailyInventoryStatusEnum::AVAILABLE)
            ->orderBy('di.costPrice', 'ASC')
            ->getQuery()
            ->getResult();

        // 格式化库存数据
        $inventoryData = [];
        foreach ($inventories as $inventory) {
            $inventoryData[] = $this->formatDailyInventoryData($inventory);
        }

        // 获取最低价格和可售数量
        $availableCount = count($inventories);
        $lowestPrice = null;
        $highestPrice = null;

        if (!empty($inventories)) {
            $prices = array_map(function (DailyInventory $inv) {
                return (float)$inv->getSellingPrice();
            }, $inventories);

            $lowestPrice = min($prices);
            $highestPrice = max($prices);
        }

        return [
            'date' => $date->format('Y-m-d'),
            'dayOfWeek' => $this->getDayOfWeekName($date->format('w')),
            'summary' => [
                'totalRooms' => $summary?->getTotalRooms() ?? 0,
                'availableRooms' => $summary?->getAvailableRooms() ?? $availableCount,
                'reservedRooms' => $summary?->getReservedRooms() ?? 0,
                'soldRooms' => $summary?->getSoldRooms() ?? 0,
                'status' => $summary?->getStatus() ?? InventorySummaryStatusEnum::NORMAL,
                'statusLabel' => $summary?->getStatus()->getLabel() ?? InventorySummaryStatusEnum::NORMAL->getLabel(),
            ],
            'availableCount' => $availableCount,
            'requestedCount' => $roomCount,
            'isAvailable' => $availableCount >= $roomCount,
            'priceRange' => [
                'lowest' => $lowestPrice,
                'highest' => $highestPrice,
                'currency' => 'CNY',
            ],
            'inventories' => $inventoryData,
            'isDefault' => false, // 由外部方法设置
        ];
    }

    /**
     * 格式化日库存数据
     */
    private function formatDailyInventoryData(DailyInventory $dailyInventory): array
    {
        return [
            'id' => $dailyInventory->getId(),
            'code' => $dailyInventory->getCode(),
            'costPrice' => (float)$dailyInventory->getCostPrice(),
            'sellingPrice' => (float)$dailyInventory->getSellingPrice(),
            'profitRate' => (float)$dailyInventory->getProfitRate(),
            'status' => $dailyInventory->getStatus(),
            'statusLabel' => $dailyInventory->getStatus()->getLabel(),
            'isReserved' => $dailyInventory->isReserved(),
            'contract' => [
                'id' => $dailyInventory->getContract()?->getId(),
                'contractNo' => $dailyInventory->getContract()?->getContractNo(),
                'priority' => $dailyInventory->getContract()?->getPriority(),
            ],
            'isSelected' => false, // 由外部设置
        ];
    }

    /**
     * 标记默认选择的库存
     */
    private function markDefaultInventory(array &$dailyInventories, int $roomCount = 1): void
    {
        foreach ($dailyInventories as &$dayData) {
            if (!$dayData['isAvailable']) {
                continue;
            }

            // 选择价格最低的库存
            $selectedCount = 0;
            foreach ($dayData['inventories'] as &$inventory) {
                if ($selectedCount < $roomCount) {
                    $inventory['isSelected'] = true;
                    $selectedCount++;
                } else {
                    break;
                }
            }

            // 标记为默认选择
            if ($selectedCount === $roomCount) {
                $dayData['isDefault'] = true;
            }
        }
    }

    /**
     * 格式化房型数据
     */
    private function formatRoomTypeData(RoomType $roomType): array
    {
        return [
            'id' => $roomType->getId(),
            'name' => $roomType->getName(),
            'hotel' => [
                'id' => $roomType->getHotel()->getId(),
                'name' => $roomType->getHotel()->getName(),
            ],
        ];
    }

    /**
     * 获取星期名称
     */
    private function getDayOfWeekName(string $dayOfWeek): string
    {
        $names = [
            '0' => '周日',
            '1' => '周一',
            '2' => '周二',
            '3' => '周三',
            '4' => '周四',
            '5' => '周五',
            '6' => '周六',
        ];

        return $names[$dayOfWeek] ?? '未知';
    }
}
