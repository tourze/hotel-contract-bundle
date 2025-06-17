<?php

namespace Tourze\HotelContractBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Tourze\HotelContractBundle\Entity\DailyInventory;
use Tourze\HotelContractBundle\Entity\InventorySummary;
use Tourze\HotelProfileBundle\Entity\RoomType;

/**
 * 库存查询服务
 */
class InventoryQueryService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {}

    /**
     * 获取库存信息
     */
    public function getInventoryData(
        int $roomTypeId,
        string $checkInDate,
        string $checkOutDate,
        int $roomCount
    ): array {
        // 查找房型
        $roomType = $this->entityManager->getRepository(RoomType::class)->find($roomTypeId);
        if (!$roomType) {
            throw new \Exception('房型不存在');
        }

        // 获取日期范围内的库存信息
        $startDate = new \DateTimeImmutable($checkInDate);
        $endDate = new \DateTimeImmutable($checkOutDate);

        $inventoryData = [];
        $currentDate = clone $startDate;

        while ($currentDate < $endDate) {
            $dayData = $this->getDayInventoryData($roomType, $currentDate, $roomCount);
            $inventoryData[] = $dayData;
            $currentDate->modify('+1 day');
        }

        // 检查是否所有日期都有足够库存且有价格
        $canBookAll = array_reduce($inventoryData, function ($carry, $item) {
            return $carry && $item['can_book'] && !empty($item['daily_inventories']);
        }, true);

        return [
            'room_type' => $this->formatRoomTypeData($roomType),
            'inventory' => $inventoryData,
            'total_nights' => count($inventoryData),
            'can_book_all' => $canBookAll,
        ];
    }

    /**
     * 获取单日库存数据
     */
    private function getDayInventoryData(RoomType $roomType, \DateTime $date, int $roomCount): array
    {
        $dateStr = $date->format('Y-m-d');
        $conn = $this->entityManager->getConnection();

        // 获取库存汇总信息（数量）
        $inventorySummary = $this->entityManager->getRepository(InventorySummary::class)
            ->findOneBy([
                'roomType' => $roomType,
                'date' => $date
            ]);

        // 获取该日期所有可用的DailyInventory记录
        $sql = "SELECT * FROM daily_inventory 
                WHERE room_type_id = :roomTypeId 
                AND date = :date 
                AND status = :status 
                ORDER BY profit_rate DESC, cost_price ASC";

        $stmt = $conn->executeQuery($sql, [
            'roomTypeId' => $roomType->getId(),
            'date' => $dateStr,
            'status' => 'available'
        ]);

        $results = $stmt->fetchAllAssociative();

        // 将结果转换为实体对象
        $dailyInventories = [];
        foreach ($results as $row) {
            $dailyInventory = $this->entityManager->find(DailyInventory::class, $row['id']);
            if ($dailyInventory) {
                $dailyInventories[] = $dailyInventory;
            }
        }

        // 计算实际可用房间数量
        $actualAvailableRooms = count($dailyInventories);

        // 判断是否可以预订：必须有足够的可用库存
        $canBook = $actualAvailableRooms >= $roomCount;

        // 如果库存不足，获取所有库存（包括已占用的）用于展示
        $allDailyInventories = [];
        if (!$canBook) {
            $sql2 = "SELECT * FROM daily_inventory
                     WHERE room_type_id = :roomTypeId
                     AND date = :date
                     ORDER BY profit_rate DESC, cost_price ASC";

            $stmt2 = $conn->executeQuery($sql2, [
                'roomTypeId' => $roomType->getId(),
                'date' => $dateStr
            ]);

            $results2 = $stmt2->fetchAllAssociative();

            // 将结果转换为实体对象
            foreach ($results2 as $row) {
                $dailyInventory = $this->entityManager->find(DailyInventory::class, $row['id']);
                if ($dailyInventory) {
                    $allDailyInventories[] = $dailyInventory;
                }
            }
        }

        $dayData = [
            'date' => $dateStr,
            'date_display' => $date->format('m月d日'),
            'day_of_week' => $this->getDayOfWeekName($date->format('w')),
            'total_rooms' => $inventorySummary ? $inventorySummary->getTotalRooms() : $actualAvailableRooms,
            'available_rooms' => $actualAvailableRooms,
            'booked_rooms' => $inventorySummary ? ($inventorySummary->getSoldRooms() + $inventorySummary->getPendingRooms()) : 0,
            'can_book' => $canBook,
            'requested_rooms' => $roomCount,
            'shortage' => max(0, $roomCount - $actualAvailableRooms), // 缺少的房间数
            'daily_inventories' => []
        ];

        // 选择要展示的库存：如果可以预订，展示可用的；否则展示所有的
        $inventoriesToShow = $canBook ? $dailyInventories : $allDailyInventories;

        // 添加每个DailyInventory的详细信息
        foreach ($inventoriesToShow as $dailyInventory) {
            $inventoryData = $this->formatDailyInventoryData($dailyInventory);
            $dayData['daily_inventories'][] = $inventoryData;
        }

        // 如果可以预订，标记默认选择的库存
        if ($canBook) {
            $this->markDefaultInventory($dayData['daily_inventories'], $roomCount);
        }

        return $dayData;
    }

    /**
     * 格式化DailyInventory数据
     */
    private function formatDailyInventoryData(DailyInventory $dailyInventory): array
    {
        $sellingPrice = (float)$dailyInventory->getSellingPrice();
        $costPrice = (float)$dailyInventory->getCostPrice();
        $profit = $sellingPrice - $costPrice;

        return [
            'id' => $dailyInventory->getId(),
            'code' => $dailyInventory->getCode(),
            'cost_price' => $dailyInventory->getCostPrice(),
            'selling_price' => $dailyInventory->getSellingPrice(),
            'profit_rate' => $dailyInventory->getProfitRate(),
            'profit_amount' => $profit,
            'contract_no' => $dailyInventory->getContract() ? $dailyInventory->getContract()->getContractNo() : '无合同',
            'status' => $dailyInventory->getStatus()->value,
            'status_label' => $dailyInventory->getStatus()->getLabel(),
        ];
    }

    /**
     * 标记默认库存（按利润率排序，选择指定数量）
     */
    private function markDefaultInventory(array &$dailyInventories, int $roomCount = 1): void
    {
        if (empty($dailyInventories)) {
            return;
        }

        // 按利润率从高到低排序
        usort($dailyInventories, function ($a, $b) {
            $profitRateA = (float)$a['profit_rate'];
            $profitRateB = (float)$b['profit_rate'];
            return $profitRateB <=> $profitRateA;
        });

        // 标记前N个为默认选择（N为房间数量）
        $selectCount = min($roomCount, count($dailyInventories));

        foreach ($dailyInventories as $index => &$inventory) {
            $inventory['is_default'] = ($index < $selectCount);
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
            'hotel_name' => $roomType->getHotel()->getName(),
            'bed_type' => $roomType->getBedType(),
            'max_guests' => $roomType->getMaxGuests(),
        ];
    }

    /**
     * 获取星期名称
     */
    private function getDayOfWeekName(string $dayOfWeek): string
    {
        $days = ['日', '一', '二', '三', '四', '五', '六'];
        return '周' . $days[(int)$dayOfWeek];
    }
}
