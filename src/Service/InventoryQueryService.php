<?php

namespace Tourze\HotelContractBundle\Service;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Tourze\HotelContractBundle\Entity\DailyInventory;
use Tourze\HotelContractBundle\Enum\InventorySummaryStatusEnum;
use Tourze\HotelContractBundle\Exception\InvalidEntityException;
use Tourze\HotelContractBundle\Repository\DailyInventoryRepository;
use Tourze\HotelContractBundle\Repository\InventorySummaryRepository;
use Tourze\HotelProfileBundle\Entity\RoomType;
use Tourze\HotelProfileBundle\Service\RoomTypeService;

/**
 * 库存查询服务
 */
#[Autoconfigure(public: true)]
readonly final class InventoryQueryService
{
    public function __construct(
        private RoomTypeService $roomTypeService,
        private InventorySummaryRepository $inventorySummaryRepository,
        private DailyInventoryRepository $dailyInventoryRepository,
    ) {
    }

    /**
     * 获取指定房型在特定日期范围内的库存信息
     *
     * 不考虑并发：此方法为只读查询，不涉及数据修改
     */
    /**
     * @return array<string, mixed>
     */
    public function getInventoryData(
        int $roomTypeId,
        string $checkInDate,
        string $checkOutDate,
        int $roomCount,
    ): array {
        $roomType = $this->roomTypeService->findRoomTypeById($roomTypeId);
        if (null === $roomType) {
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
        $dailyInventories = $this->markDefaultInventory($dailyInventories, $roomCount);

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
     *
     * 不考虑并发：此方法为只读查询，不涉及数据修改
     */
    /**
     * @return array<string, mixed>
     */
    private function getDayInventoryData(RoomType $roomType, \DateTimeInterface $date, int $roomCount): array
    {
        // 获取库存汇总信息
        $hotel = $roomType->getHotel();
        if (null === $hotel) {
            throw new InvalidEntityException('房型必须关联一个酒店');
        }

        $hotelId = $hotel->getId();
        $roomTypeId = $roomType->getId();

        if (null === $hotelId || null === $roomTypeId) {
            throw new InvalidEntityException('酒店ID或房型ID不能为空');
        }

        $summary = $this->inventorySummaryRepository
            ->findByHotelRoomTypeAndDate(
                $hotelId,
                $roomTypeId,
                $date
            )
        ;

        // 获取该日具体的库存记录
        $inventories = $this->dailyInventoryRepository
            ->findAvailableByRoomTypeAndDate($roomTypeId, $date)
        ;

        // 格式化库存数据
        $inventoryData = [];
        foreach ($inventories as $inventory) {
            $inventoryData[] = $this->formatDailyInventoryData($inventory);
        }

        // 获取最低价格和可售数量
        $availableCount = count($inventories);
        $lowestPrice = null;
        $highestPrice = null;

        if ([] !== $inventories) {
            $prices = array_map(function (DailyInventory $inv) {
                return (float) $inv->getSellingPrice();
            }, $inventories);

            if ([] !== $prices) {
                $lowestPrice = min($prices);
                $highestPrice = max($prices);
            }
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
     *
     * 不考虑并发：此方法为纯数据格式化，不涉及数据修改
     */
    /**
     * @return array<string, mixed>
     */
    private function formatDailyInventoryData(DailyInventory $dailyInventory): array
    {
        return [
            'id' => $dailyInventory->getId(),
            'code' => $dailyInventory->getCode(),
            'costPrice' => (float) $dailyInventory->getCostPrice(),
            'sellingPrice' => (float) $dailyInventory->getSellingPrice(),
            'profitRate' => (float) $dailyInventory->getProfitRate(),
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
     *
     * 不考虑并发：此方法仅处理内存中的数据，不涉及数据库写入
     */
    /**
     * @param array<array<string, mixed>> $dailyInventories
     * @return array<array<string, mixed>>
     */
    private function markDefaultInventory(array $dailyInventories, int $roomCount = 1): array
    {
        foreach ($dailyInventories as $key => $dayData) {
            if (false === ($dayData['isAvailable'] ?? false)) {
                continue;
            }

            $inventories = $dayData['inventories'] ?? [];
            if (!is_array($inventories)) {
                continue;
            }

            /** @var array<array<string, mixed>> $inventories */
            $result = $this->selectLowestPriceInventories($inventories, $roomCount);
            $dailyInventories[$key]['inventories'] = $result['inventories'];
            $dailyInventories[$key]['isDefault'] = $result['selectedCount'] === $roomCount;
        }

        return $dailyInventories;
    }

    /**
     * @param array<array<string, mixed>> $inventories
     * @return array{inventories: array<array<string, mixed>>, selectedCount: int}
     */
    private function selectLowestPriceInventories(array $inventories, int $roomCount): array
    {
        $selectedCount = 0;
        foreach ($inventories as $key => $inventory) {
            if ($selectedCount >= $roomCount) {
                break;
            }
            $inventories[$key]['isSelected'] = true;
            ++$selectedCount;
        }

        return [
            'inventories' => $inventories,
            'selectedCount' => $selectedCount,
        ];
    }

    /**
     * 格式化房型数据
     */
    /**
     * @return array<string, mixed>
     */
    private function formatRoomTypeData(RoomType $roomType): array
    {
        $hotel = $roomType->getHotel();
        if (null === $hotel) {
            throw new InvalidEntityException('房型必须关联一个酒店');
        }

        return [
            'id' => $roomType->getId(),
            'name' => $roomType->getName(),
            'hotel' => [
                'id' => $hotel->getId(),
                'name' => $hotel->getName(),
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
