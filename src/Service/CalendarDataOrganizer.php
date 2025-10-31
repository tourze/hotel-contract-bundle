<?php

declare(strict_types=1);

namespace Tourze\HotelContractBundle\Service;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Tourze\HotelContractBundle\Entity\DailyInventory;
use Tourze\HotelProfileBundle\Entity\RoomType;

#[Autoconfigure(public: true)]
readonly class CalendarDataOrganizer
{
    /**
     * @param array<array<string, mixed>> $dates
     * @param array<array<string, mixed>> $priceData
     * @return array<array<string, mixed>>
     */
    public function generateRoomTypePrices(RoomType $roomType, array $dates, array $priceData): array
    {
        $prices = [];

        foreach ($dates as $dateInfo) {
            $dateValue = $dateInfo['date'] ?? null;
            if (!$dateValue instanceof \DateTimeInterface) {
                continue;
            }

            $date = $dateValue->format('Y-m-d');
            $prices[] = [
                'date' => $date,
                'inventories' => $this->findInventoriesForDate($roomType, $date, $priceData),
            ];
        }

        return $prices;
    }

    /**
     * @param array<array<string, mixed>> $priceData
     * @return array<array<string, mixed>>
     */
    private function findInventoriesForDate(RoomType $roomType, string $date, array $priceData): array
    {
        $inventories = [];

        foreach ($priceData as $item) {
            if (!is_array($item)) {
                continue;
            }

            $itemDate = $item['date'] ?? null;
            if (!$itemDate instanceof \DateTimeInterface) {
                continue;
            }

            if ($item['roomTypeId'] === $roomType->getId() && $itemDate->format('Y-m-d') === $date) {
                $inventories[] = [
                    'id' => $item['id'],
                    'costPrice' => $item['costPrice'],
                    'sellingPrice' => $item['sellingPrice'],
                    'code' => $item['inventoryCode'],
                ];
            }
        }

        return $inventories;
    }

    /**
     * @param array<array<string, mixed>> $dates
     * @param array<DailyInventory> $inventories
     * @return array<array<string, mixed>>
     */
    public function generateRoomTypeInventoryPrices(RoomType $roomType, array $dates, array $inventories): array
    {
        $prices = [];

        foreach ($dates as $dateInfo) {
            $dateValue = $dateInfo['date'] ?? null;
            if (!$dateValue instanceof \DateTimeInterface) {
                continue;
            }

            $date = $dateValue->format('Y-m-d');
            $prices[] = [
                'date' => $date,
                'inventories' => $this->findInventoryEntitiesForDate($roomType, $date, $inventories),
            ];
        }

        return $prices;
    }

    /**
     * @param array<DailyInventory> $inventories
     * @return array<array<string, mixed>>
     */
    private function findInventoryEntitiesForDate(RoomType $roomType, string $date, array $inventories): array
    {
        $inventoryData = [];

        foreach ($inventories as $inventory) {
            $inventoryRoomType = $inventory->getRoomType();
            $inventoryDate = $inventory->getDate();

            if (null !== $inventoryRoomType && null !== $inventoryDate
                && $inventoryRoomType->getId() === $roomType->getId()
                && $inventoryDate->format('Y-m-d') === $date) {
                $inventoryData[] = [
                    'id' => $inventory->getId(),
                    'costPrice' => $inventory->getCostPrice(),
                    'sellingPrice' => $inventory->getSellingPrice(),
                    'code' => $inventory->getCode(),
                ];
            }
        }

        return $inventoryData;
    }

    /**
     * @return array<array<string, mixed>>
     */
    public function generateCalendarDates(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        $dates = [];
        $currentDate = $startDate instanceof \DateTime ? clone $startDate : new \DateTime($startDate->format('Y-m-d H:i:s'));

        while ($currentDate <= $endDate) {
            $dates[] = [
                'date' => clone $currentDate,
                'day' => $currentDate->format('j'),
                'weekday' => $this->getWeekdayName($currentDate->format('N')),
                'is_weekend' => in_array($currentDate->format('N'), ['6', '7'], true),
            ];
            $currentDate->modify('+1 day');
        }

        return $dates;
    }

    private function getWeekdayName(string $weekdayNumber): string
    {
        $weekdays = [
            '1' => '周一',
            '2' => '周二',
            '3' => '周三',
            '4' => '周四',
            '5' => '周五',
            '6' => '周六',
            '7' => '周日',
        ];

        return $weekdays[$weekdayNumber] ?? '';
    }
}
