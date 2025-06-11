<?php

namespace Tourze\HotelContractBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Tourze\HotelContractBundle\Repository\DailyInventoryRepository;
use Tourze\HotelContractBundle\Repository\HotelContractRepository;
use Tourze\HotelProfileBundle\Repository\HotelRepository;
use Tourze\HotelProfileBundle\Repository\RoomTypeRepository;

/**
 * 价格管理服务
 */
class PriceManagementService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly InventoryUpdateService $updateService,
        private readonly HotelRepository $hotelRepository,
        private readonly RoomTypeRepository $roomTypeRepository,
        private readonly HotelContractRepository $contractRepository,
        private readonly DailyInventoryRepository $dailyInventoryRepository,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * 获取合同价格日历数据
     */
    public function getContractPriceCalendarData(?int $contractId, string $month): array
    {
        $contract = null;
        if ($contractId) {
            $contract = $this->contractRepository->find($contractId);
        }

        // 获取所有合同供选择
        $contracts = $this->contractRepository->findBy([], ['priority' => 'ASC', 'id' => 'DESC']);

        // 解析年月
        list($year, $monthNum) = explode('-', $month);
        $startDate = new \DateTime("$year-$monthNum-01");
        $endDate = clone $startDate;
        $endDate->modify('last day of this month');

        $calendarData = [];
        $roomTypes = [];

        if ($contract) {
            // 获取该合同关联的房型
            $roomTypeIds = $this->dailyInventoryRepository->findDistinctRoomTypesByContract($contract->getId());
            $roomTypes = $this->roomTypeRepository->findBy(['id' => $roomTypeIds]);

            // 获取日期范围内的价格数据
            $priceData = $this->dailyInventoryRepository->findPriceDataByContractAndDateRange(
                $contract->getId(),
                $startDate,
                $endDate
            );

            // 组织日历数据
            $calendarData = $this->organizeCalendarData($startDate, $endDate, $roomTypes, $priceData);
        }

        return [
            'contracts' => $contracts,
            'selectedContract' => $contract,
            'month' => $month,
            'calendarData' => $calendarData,
            'roomTypes' => $roomTypes,
            'currentMonth' => $startDate->format('Y年m月')
        ];
    }

    /**
     * 更新合同价格
     */
    public function updateContractPrice(int $inventoryId, string $costPrice): array
    {
        try {
            // 查找并更新价格
            $inventory = $this->dailyInventoryRepository->find($inventoryId);
            if (!$inventory) {
                return ['success' => false, 'message' => '库存记录不存在'];
            }

            $inventory->setCostPrice($costPrice);
            $this->entityManager->flush();

            $this->logger->info('合同价格更新成功', [
                'inventory_id' => $inventoryId,
                'cost_price' => $costPrice
            ]);

            return ['success' => true, 'message' => '价格更新成功'];
        } catch (\Throwable $e) {
            $this->logger->error('合同价格更新失败', [
                'inventory_id' => $inventoryId,
                'cost_price' => $costPrice,
                'exception' => $e
            ]);

            return ['success' => false, 'message' => '价格更新失败'];
        }
    }

    /**
     * 获取销售价格数据
     */
    public function getSellingPriceData(?int $hotelId, ?int $roomTypeId, string $month): array
    {
        $hotel = null;
        $roomType = null;

        if ($hotelId) {
            $hotel = $this->hotelRepository->find($hotelId);
        }

        if ($roomTypeId) {
            $roomType = $this->roomTypeRepository->find($roomTypeId);
        }

        // 获取所有酒店和房型供选择
        $hotels = $this->hotelRepository->findAll();
        $roomTypes = [];

        if ($hotel) {
            $roomTypes = $this->roomTypeRepository->findBy(['hotel' => $hotel]);
        }

        // 解析年月
        list($year, $monthNum) = explode('-', $month);
        $startDate = new \DateTime("$year-$monthNum-01");
        $endDate = clone $startDate;
        $endDate->modify('last day of this month');

        $calendarData = [];

        if ($hotel) {
            // 查询条件
            $criteria = ['room.hotel' => $hotel];

            if ($roomType) {
                $criteria['room.roomType'] = $roomType;
            }

            // 获取日期范围内的价格数据
            $priceData = $this->dailyInventoryRepository->findByDateRangeAndCriteria(
                $startDate,
                $endDate,
                $criteria
            );

            // 如果选择了特定房型，只显示该房型
            $displayRoomTypes = $roomType ? [$roomType] : $roomTypes;

            // 组织日历数据
            $calendarData = $this->organizeSellingPriceData($startDate, $endDate, $displayRoomTypes, $priceData);
        }

        return [
            'hotels' => $hotels,
            'selectedHotel' => $hotel,
            'roomTypes' => $roomTypes,
            'selectedRoomType' => $roomType,
            'month' => $month,
            'calendarData' => $calendarData,
            'currentMonth' => $startDate->format('Y年m月')
        ];
    }

    /**
     * 更新销售价格
     */
    public function updateSellingPrice(int $inventoryId, string $sellingPrice): array
    {
        try {
            // 查找并更新价格
            $inventory = $this->dailyInventoryRepository->find($inventoryId);
            if (!$inventory) {
                return ['success' => false, 'message' => '库存记录不存在'];
            }

            $inventory->setSellingPrice($sellingPrice);
            $this->entityManager->flush();

            $this->logger->info('销售价格更新成功', [
                'inventory_id' => $inventoryId,
                'selling_price' => $sellingPrice
            ]);

            return ['success' => true, 'message' => '销售价格更新成功'];
        } catch (\Throwable $e) {
            $this->logger->error('销售价格更新失败', [
                'inventory_id' => $inventoryId,
                'selling_price' => $sellingPrice,
                'exception' => $e
            ]);

            return ['success' => false, 'message' => '销售价格更新失败'];
        }
    }

    /**
     * 获取批量调价页面数据
     */
    public function getBatchAdjustmentData(): array
    {
        $hotels = $this->hotelRepository->findAll();
        $roomTypes = $this->roomTypeRepository->findAll();

        return [
            'hotels' => $hotels,
            'room_types' => $roomTypes,
        ];
    }

    /**
     * 批量调价处理
     */
    public function processBatchPriceAdjustment(array $params): array
    {
        try {
            // 处理简化的单日期调价参数
            if (isset($params['date']) && isset($params['room_type_id'])) {
                return $this->processSingleDatePriceAdjustment($params);
            }

            // 处理完整的批量调价参数
            $hotel = $this->hotelRepository->find($params['hotel_id']);
            $roomType = $params['room_type_id'] ? $this->roomTypeRepository->find($params['room_type_id']) : null;

            if (!$hotel) {
                return ['success' => false, 'message' => '酒店不存在'];
            }

            // 准备调价参数
            $adjustmentParams = [
                'hotel' => $hotel,
                'room_type' => $roomType,
                'start_date' => new \DateTime($params['start_date']),
                'end_date' => new \DateTime($params['end_date']),
                'price_type' => $params['price_type'],
                'adjust_method' => $params['adjust_method'],
                'day_filter' => $params['day_filter'],
                'days' => $params['days'] ?? [],
                'reason' => $params['reason'],
            ];

            // 根据调整方式设置价格值
            if ($params['adjust_method'] === 'fixed') {
                $adjustmentParams['cost_price'] = $params['price_value'];
                $adjustmentParams['selling_price'] = $params['price_value'];
            } else {
                $adjustmentParams['adjust_value'] = $params['adjust_value'];
            }

            // 调用批量调价服务
            $result = $this->updateService->batchUpdateInventoryPrice($adjustmentParams);

            $this->logger->info('批量调价执行', [
                'params' => $adjustmentParams,
                'result' => $result
            ]);

            return $result ?: ['success' => false, 'message' => '调价失败'];
        } catch (\Throwable $e) {
            $this->logger->error('批量调价失败', [
                'params' => $params,
                'exception' => $e
            ]);

            return ['success' => false, 'message' => '调价失败：' . $e->getMessage()];
        }
    }

    /**
     * 处理单日期价格调整
     */
    private function processSingleDatePriceAdjustment(array $params): array
    {
        try {
            $roomType = $this->roomTypeRepository->find($params['room_type_id']);
            $date = new \DateTime($params['date']);
            $priceType = $params['price_type'] ?? 'cost_price';
            $adjustMethod = $params['adjust_method'];

            if (!$roomType) {
                return ['success' => false, 'message' => '房型不存在'];
            }

            // 查找该日期该房型的所有库存
            $inventories = $this->dailyInventoryRepository->findBy([
                'roomType' => $roomType,
                'date' => $date,
            ]);

            if (empty($inventories)) {
                return ['success' => false, 'message' => '未找到相关库存数据'];
            }

            $updateCount = 0;

            foreach ($inventories as $inventory) {
                $currentPrice = $priceType === 'cost_price' ? $inventory->getCostPrice() : $inventory->getSellingPrice();
                $newPrice = 0;

                // 根据调整方式计算新价格
                switch ($adjustMethod) {
                    case 'fixed':
                        $newPrice = $params['price_value'];
                        break;
                    case 'percent':
                        $newPrice = $currentPrice * (1 + $params['adjust_value'] / 100);
                        break;
                    case 'increment':
                        $newPrice = $currentPrice + $params['adjust_value'];
                        break;
                    case 'decrement':
                        $newPrice = $currentPrice - $params['adjust_value'];
                        break;
                    case 'profit_rate':
                        if ($priceType === 'selling_price' && isset($params['profit_rate'])) {
                            $costPrice = $inventory->getCostPrice();
                            $newPrice = $costPrice * (1 + $params['profit_rate'] / 100);
                        }
                        break;
                }

                // 确保价格不为负数
                $newPrice = max(0, $newPrice);

                // 更新价格
                if ($priceType === 'cost_price') {
                    $inventory->setCostPrice($newPrice);
                } else {
                    $inventory->setSellingPrice($newPrice);
                }

                $updateCount++;
            }

            $this->entityManager->flush();

            $this->logger->info('单日期价格调整成功', [
                'room_type_id' => $params['room_type_id'],
                'date' => $params['date'],
                'price_type' => $priceType,
                'adjust_method' => $adjustMethod,
                'update_count' => $updateCount
            ]);

            return [
                'success' => true,
                'message' => "成功调整 {$updateCount} 个库存的价格"
            ];
        } catch (\Throwable $e) {
            $this->logger->error('单日期价格调整失败', [
                'params' => $params,
                'exception' => $e
            ]);

            return ['success' => false, 'message' => '调价失败：' . $e->getMessage()];
        }
    }

    /**
     * 组织合同价格日历数据
     */
    private function organizeCalendarData(\DateTime $startDate, \DateTime $endDate, array $roomTypes, array $priceData): array
    {
        $calendarData = [];
        $currentDate = clone $startDate;

        // 生成日历头部日期
        $dates = [];
        while ($currentDate <= $endDate) {
            $dates[] = [
                'date' => clone $currentDate,
                'day' => $currentDate->format('j'),
                'weekday' => $this->getWeekdayName($currentDate->format('N')),
                'is_weekend' => in_array($currentDate->format('N'), ['6', '7']),
            ];
            $currentDate->modify('+1 day');
        }

        $calendarData['dates'] = $dates;
        $calendarData['roomTypes'] = [];

        // 按房型组织价格数据
        foreach ($roomTypes as $roomType) {
            $roomTypeData = [
                'roomType' => $roomType,
                'prices' => [],
            ];

            // 为每一天填充价格
            foreach ($dates as $dateInfo) {
                $date = $dateInfo['date']->format('Y-m-d');
                $priceInfo = [
                    'date' => $date,
                    'inventories' => [],
                ];

                // 查找该日期该房型的价格数据
                foreach ($priceData as $item) {
                    if (
                        $item['roomTypeId'] == $roomType->getId() &&
                        $item['date']->format('Y-m-d') === $date
                    ) {
                        $priceInfo['inventories'][] = [
                            'id' => $item['id'],
                            'costPrice' => $item['costPrice'],
                            'sellingPrice' => $item['sellingPrice'],
                            'code' => $item['inventoryCode'],
                        ];
                    }
                }

                $roomTypeData['prices'][] = $priceInfo;
            }

            $calendarData['roomTypes'][] = $roomTypeData;
        }

        return $calendarData;
    }

    /**
     * 组织销售价格日历数据
     */
    private function organizeSellingPriceData(\DateTime $startDate, \DateTime $endDate, array $roomTypes, array $inventories): array
    {
        $calendarData = [];
        $currentDate = clone $startDate;

        // 生成日历头部日期
        $dates = [];
        while ($currentDate <= $endDate) {
            $dates[] = [
                'date' => clone $currentDate,
                'day' => $currentDate->format('j'),
                'weekday' => $this->getWeekdayName($currentDate->format('N')),
                'is_weekend' => in_array($currentDate->format('N'), ['6', '7']),
            ];
            $currentDate->modify('+1 day');
        }

        $calendarData['dates'] = $dates;
        $calendarData['roomTypes'] = [];

        // 按房型组织价格数据
        foreach ($roomTypes as $roomType) {
            $roomTypeData = [
                'roomType' => $roomType,
                'prices' => [],
            ];

            // 为每一天填充价格
            foreach ($dates as $dateInfo) {
                $date = $dateInfo['date']->format('Y-m-d');
                $priceInfo = [
                    'date' => $date,
                    'inventories' => [],
                ];

                // 查找该日期该房型的库存数据
                foreach ($inventories as $inventory) {
                    if (
                        $inventory->getRoomType()->getId() == $roomType->getId() &&
                        $inventory->getDate()->format('Y-m-d') === $date
                    ) {
                        $priceInfo['inventories'][] = [
                            'id' => $inventory->getId(),
                            'costPrice' => $inventory->getCostPrice(),
                            'sellingPrice' => $inventory->getSellingPrice(),
                            'code' => $inventory->getCode(),
                        ];
                    }
                }

                $roomTypeData['prices'][] = $priceInfo;
            }

            $calendarData['roomTypes'][] = $roomTypeData;
        }

        return $calendarData;
    }

    /**
     * 获取星期几名称
     */
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
