<?php

namespace Tourze\HotelContractBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Tourze\HotelContractBundle\Entity\DailyInventory;
use Tourze\HotelContractBundle\Exception\InvalidEntityException;
use Tourze\HotelContractBundle\Repository\DailyInventoryRepository;
use Tourze\HotelContractBundle\Repository\HotelContractRepository;
use Tourze\HotelProfileBundle\Entity\RoomType;
use Tourze\HotelProfileBundle\Service\HotelService;
use Tourze\HotelProfileBundle\Service\RoomTypeService;

/**
 * 价格管理服务
 */
#[Autoconfigure(public: true)]
#[WithMonologChannel(channel: 'hotel_contract')]
readonly class PriceManagementService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private InventoryUpdateService $updateService,
        private HotelService $hotelService,
        private RoomTypeService $roomTypeService,
        private HotelContractRepository $contractRepository,
        private DailyInventoryRepository $dailyInventoryRepository,
        private LoggerInterface $logger,
        private CalendarDataOrganizer $calendarOrganizer,
        private InventoryPriceCalculator $priceCalculator,
    ) {
    }

    /**
     * 获取合同价格日历数据
     */
    /**
     * @return array<string, mixed>
     */
    public function getContractPriceCalendarData(?int $contractId, string $month): array
    {
        $contract = null;
        if (null !== $contractId) {
            $contract = $this->contractRepository->find($contractId);
        }

        // 获取所有合同供选择
        $contracts = $this->contractRepository->findBy([], ['priority' => 'ASC', 'id' => 'DESC']);

        // 解析年月
        [$year, $monthNum] = explode('-', $month);
        $startDate = new \DateTimeImmutable("{$year}-{$monthNum}-01");
        $endDate = clone $startDate;
        $endDate = $endDate->modify('last day of this month');

        $calendarData = [];
        $roomTypes = [];

        if (null !== $contract) {
            $contractId = $contract->getId();
            if (null === $contractId) {
                throw new InvalidEntityException('合同ID不能为空');
            }

            // 获取该合同关联的房型
            $roomTypeIds = $this->dailyInventoryRepository->findDistinctRoomTypesByContract($contractId);
            $roomTypes = $this->roomTypeService->findRoomTypesByIds($roomTypeIds);

            // 获取日期范围内的价格数据
            $priceData = $this->dailyInventoryRepository->findPriceDataByContractAndDateRange(
                $contractId,
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
            'currentMonth' => $startDate->format('Y年m月'),
        ];
    }

    /**
     * 更新合同价格
     */
    /**
     * @return array<string, mixed>
     */
    public function updateContractPrice(int $inventoryId, string $costPrice): array
    {
        try {
            // 查找并更新价格
            $inventory = $this->dailyInventoryRepository->find($inventoryId);
            if (null === $inventory) {
                return ['success' => false, 'message' => '库存记录不存在'];
            }

            $inventory->setCostPrice($costPrice);
            $this->entityManager->flush();

            $this->logger->info('合同价格更新成功', [
                'inventory_id' => $inventoryId,
                'cost_price' => $costPrice,
            ]);

            return ['success' => true, 'message' => '价格更新成功'];
        } catch (\Throwable $e) {
            $this->logger->error('合同价格更新失败', [
                'inventory_id' => $inventoryId,
                'cost_price' => $costPrice,
                'exception' => $e,
            ]);

            return ['success' => false, 'message' => '价格更新失败'];
        }
    }

    /**
     * 获取销售价格数据
     */
    /**
     * @return array<string, mixed>
     */
    public function getSellingPriceData(?int $hotelId, ?int $roomTypeId, string $month): array
    {
        $hotel = null;
        $roomType = null;

        if (null !== $hotelId) {
            $hotel = $this->hotelService->findHotelById($hotelId);
        }

        if (null !== $roomTypeId) {
            $roomType = $this->roomTypeService->findRoomTypeById($roomTypeId);
        }

        // 获取所有酒店和房型供选择
        $hotels = $this->hotelService->findAllHotels();
        $roomTypes = [];

        if (null !== $hotel) {
            $hotelId = $hotel->getId();
            if (null === $hotelId) {
                throw new InvalidEntityException('酒店ID不能为空');
            }
            $roomTypes = $this->roomTypeService->findRoomTypesByHotel($hotelId);
        }

        // 解析年月
        [$year, $monthNum] = explode('-', $month);
        $startDate = new \DateTimeImmutable("{$year}-{$monthNum}-01");
        $endDate = clone $startDate;
        $endDate = $endDate->modify('last day of this month');

        $calendarData = [];

        if (null !== $hotel) {
            // 查询条件
            $criteria = ['room.hotel' => $hotel];

            if (null !== $roomType) {
                $criteria['room.roomType'] = $roomType;
            }

            // 获取日期范围内的价格数据
            $priceData = $this->dailyInventoryRepository->findByDateRangeAndCriteria(
                $startDate,
                $endDate,
                $criteria
            );

            // 如果选择了特定房型，只显示该房型
            $displayRoomTypes = null !== $roomType ? [$roomType] : $roomTypes;

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
            'currentMonth' => $startDate->format('Y年m月'),
        ];
    }

    /**
     * 更新销售价格
     */
    /**
     * @return array<string, mixed>
     */
    public function updateSellingPrice(int $inventoryId, string $sellingPrice): array
    {
        try {
            // 查找并更新价格
            $inventory = $this->dailyInventoryRepository->find($inventoryId);
            if (null === $inventory) {
                return ['success' => false, 'message' => '库存记录不存在'];
            }

            $inventory->setSellingPrice($sellingPrice);
            $this->entityManager->flush();

            $this->logger->info('销售价格更新成功', [
                'inventory_id' => $inventoryId,
                'selling_price' => $sellingPrice,
            ]);

            return ['success' => true, 'message' => '销售价格更新成功'];
        } catch (\Throwable $e) {
            $this->logger->error('销售价格更新失败', [
                'inventory_id' => $inventoryId,
                'selling_price' => $sellingPrice,
                'exception' => $e,
            ]);

            return ['success' => false, 'message' => '销售价格更新失败'];
        }
    }

    /**
     * 获取批量调价页面数据
     */
    /**
     * @return array<string, mixed>
     */
    public function getBatchAdjustmentData(): array
    {
        $hotels = $this->hotelService->findAllHotels();
        $roomTypes = $this->roomTypeService->findAllRoomTypes();

        return [
            'hotels' => $hotels,
            'room_types' => $roomTypes,
        ];
    }

    /**
     * 批量调价处理
     */
    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function processBatchPriceAdjustment(array $params): array
    {
        try {
            // 处理简化的单日期调价参数
            if (isset($params['date'], $params['room_type_id'])) {
                return $this->processSingleDatePriceAdjustment($params);
            }

            $validationResult = $this->validateBatchAdjustmentParams($params);
            $isValid = $validationResult['success'] ?? false;
            if (!is_bool($isValid) || !$isValid) {
                return $validationResult;
            }

            $adjustmentParams = $this->prepareBatchAdjustmentParams($params, $validationResult['hotel'], $validationResult['roomType']);
            $result = $this->updateService->batchUpdateInventoryPrice($adjustmentParams);

            $this->logger->info('批量调价执行', [
                'params' => $adjustmentParams,
                'result' => $result,
            ]);

            return $result;
        } catch (\Throwable $e) {
            $this->logger->error('批量调价失败', [
                'params' => $params,
                'exception' => $e,
            ]);

            return ['success' => false, 'message' => '调价失败：' . $e->getMessage()];
        }
    }

    /**
     * 处理单日期价格调整
     */
    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function processSingleDatePriceAdjustment(array $params): array
    {
        try {
            $validationResult = $this->validateSingleDateParams($params);
            $isValid = $validationResult['success'] ?? false;
            if (!is_bool($isValid) || !$isValid) {
                return $validationResult;
            }

            $adjustmentData = $this->prepareSingleDateAdjustmentData($params);
            $inventories = $this->findInventoriesForSingleDateAdjustment($adjustmentData);

            if ([] === $inventories) {
                return ['success' => false, 'message' => '未找到相关库存数据'];
            }

            $updateCount = $this->applySingleDatePriceAdjustment($inventories, $adjustmentData);

            $this->entityManager->flush();

            $this->logSingleDateAdjustmentSuccess($params, $updateCount, $adjustmentData);

            return [
                'success' => true,
                'message' => "成功调整 {$updateCount} 个库存的价格",
            ];
        } catch (\Throwable $e) {
            $this->logger->error('单日期价格调整失败', [
                'params' => $params,
                'exception' => $e,
            ]);

            return ['success' => false, 'message' => '调价失败：' . $e->getMessage()];
        }
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function validateSingleDateParams(array $params): array
    {
        $roomTypeId = $params['room_type_id'] ?? null;
        if (!is_int($roomTypeId)) {
            return ['success' => false, 'message' => '房型ID无效'];
        }

        $roomType = $this->roomTypeService->findRoomTypeById($roomTypeId);
        if (null === $roomType) {
            return ['success' => false, 'message' => '房型不存在'];
        }

        return ['success' => true, 'roomType' => $roomType];
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function prepareSingleDateAdjustmentData(array $params): array
    {
        $roomTypeId = $params['room_type_id'] ?? null;
        if (!is_int($roomTypeId)) {
            throw new InvalidEntityException('房型ID必须是整数');
        }

        $dateString = $params['date'] ?? null;
        if (!is_string($dateString)) {
            throw new InvalidEntityException('日期必须是字符串');
        }

        $adjustMethod = $params['adjust_method'] ?? null;
        if (!is_string($adjustMethod)) {
            throw new InvalidEntityException('调整方法必须是字符串');
        }

        return [
            'roomType' => $this->roomTypeService->findRoomTypeById($roomTypeId),
            'date' => new \DateTimeImmutable($dateString),
            'priceType' => $params['price_type'] ?? 'cost_price',
            'adjustMethod' => $adjustMethod,
            'params' => $params,
        ];
    }

    /**
     * @param array<string, mixed> $adjustmentData
     * @return array<DailyInventory>
     */
    private function findInventoriesForSingleDateAdjustment(array $adjustmentData): array
    {
        return $this->dailyInventoryRepository->findBy([
            'roomType' => $adjustmentData['roomType'],
            'date' => $adjustmentData['date'],
        ]);
    }

    /**
     * @param array<DailyInventory> $inventories
     * @param array<string, mixed> $adjustmentData
     */
    private function applySingleDatePriceAdjustment(array $inventories, array $adjustmentData): int
    {
        $priceType = $adjustmentData['priceType'] ?? 'cost_price';
        if (!is_string($priceType)) {
            throw new InvalidEntityException('价格类型必须是字符串');
        }

        $updateCount = 0;
        foreach ($inventories as $inventory) {
            $newPrice = $this->priceCalculator->calculateNewPrice($inventory, $adjustmentData);
            $this->priceCalculator->updateInventoryPrice($inventory, $priceType, $newPrice);
            ++$updateCount;
        }

        return $updateCount;
    }

    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $adjustmentData
     */
    private function logSingleDateAdjustmentSuccess(array $params, int $updateCount, array $adjustmentData): void
    {
        $this->logger->info('单日期价格调整成功', [
            'room_type_id' => $params['room_type_id'],
            'date' => $params['date'],
            'price_type' => $adjustmentData['priceType'],
            'adjust_method' => $adjustmentData['adjustMethod'],
            'update_count' => $updateCount,
        ]);
    }

    /**
     * 组织合同价格日历数据
     */
    /**
     * @param array<RoomType> $roomTypes
     * @param array<array<string, mixed>> $priceData
     * @return array<string, mixed>
     */
    private function organizeCalendarData(\DateTimeInterface $startDate, \DateTimeInterface $endDate, array $roomTypes, array $priceData): array
    {
        $dates = $this->calendarOrganizer->generateCalendarDates($startDate, $endDate);
        $roomTypesData = $this->organizeRoomTypePriceData($roomTypes, $dates, $priceData);

        return [
            'dates' => $dates,
            'roomTypes' => $roomTypesData,
        ];
    }

    /**
     * @param array<RoomType> $roomTypes
     * @param array<array<string, mixed>> $dates
     * @param array<array<string, mixed>> $priceData
     * @return array<array<string, mixed>>
     */
    private function organizeRoomTypePriceData(array $roomTypes, array $dates, array $priceData): array
    {
        $roomTypesData = [];

        foreach ($roomTypes as $roomType) {
            $roomTypesData[] = [
                'roomType' => $roomType,
                'prices' => $this->calendarOrganizer->generateRoomTypePrices($roomType, $dates, $priceData),
            ];
        }

        return $roomTypesData;
    }

    /**
     * 组织销售价格日历数据
     */
    /**
     * @param array<RoomType> $roomTypes
     * @param array<DailyInventory> $inventories
     * @return array<string, mixed>
     */
    private function organizeSellingPriceData(\DateTimeInterface $startDate, \DateTimeInterface $endDate, array $roomTypes, array $inventories): array
    {
        $dates = $this->calendarOrganizer->generateCalendarDates($startDate, $endDate);
        $roomTypesData = $this->organizeRoomTypeInventoryData($roomTypes, $dates, $inventories);

        return [
            'dates' => $dates,
            'roomTypes' => $roomTypesData,
        ];
    }

    /**
     * 不考虑并发 - 数据组织是只读操作，不涉及状态变更
     */
    /**
     * @param array<RoomType> $roomTypes
     * @param array<array<string, mixed>> $dates
     * @param array<DailyInventory> $inventories
     * @return array<array<string, mixed>>
     */
    private function organizeRoomTypeInventoryData(array $roomTypes, array $dates, array $inventories): array
    {
        $roomTypesData = [];

        foreach ($roomTypes as $roomType) {
            $roomTypesData[] = [
                'roomType' => $roomType,
                'prices' => $this->calendarOrganizer->generateRoomTypeInventoryPrices($roomType, $dates, $inventories),
            ];
        }

        return $roomTypesData;
    }

    /**
     * 验证批量调价参数
     */
    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function validateBatchAdjustmentParams(array $params): array
    {
        $hotelId = $params['hotel_id'] ?? null;
        if (!is_int($hotelId)) {
            return ['success' => false, 'message' => '酒店ID无效'];
        }

        $hotel = $this->hotelService->findHotelById($hotelId);
        if (null === $hotel) {
            return ['success' => false, 'message' => '酒店不存在'];
        }

        $roomType = $this->extractRoomTypeFromParams($params);

        return [
            'success' => true,
            'hotel' => $hotel,
            'roomType' => $roomType,
        ];
    }

    /**
     * 从参数中提取房型
     */
    /**
     * @param array<string, mixed> $params
     */
    private function extractRoomTypeFromParams(array $params): ?object
    {
        $roomTypeId = $params['room_type_id'] ?? null;

        if (null === $roomTypeId || '' === $roomTypeId || !is_numeric($roomTypeId)) {
            return null;
        }

        return $this->roomTypeService->findRoomTypeById((int) $roomTypeId);
    }

    /**
     * 准备批量调价参数
     * @param mixed $hotel
     * @param mixed $roomType
     */
    /**
     * @param array<string, mixed> $params
     * @param mixed $hotel
     * @param mixed $roomType
     * @return array<string, mixed>
     */
    private function prepareBatchAdjustmentParams(array $params, $hotel, $roomType): array
    {
        $startDate = $params['start_date'] ?? null;
        if (!is_string($startDate)) {
            throw new InvalidEntityException('开始日期必须是字符串');
        }

        $endDate = $params['end_date'] ?? null;
        if (!is_string($endDate)) {
            throw new InvalidEntityException('结束日期必须是字符串');
        }

        $adjustmentParams = [
            'hotel' => $hotel,
            'room_type' => $roomType,
            'start_date' => new \DateTimeImmutable($startDate),
            'end_date' => new \DateTimeImmutable($endDate),
            'price_type' => $params['price_type'],
            'adjust_method' => $params['adjust_method'],
            'day_filter' => $params['day_filter'],
            'days' => $params['days'] ?? [],
            'reason' => $params['reason'],
        ];

        return $this->addPriceValuesToParams($adjustmentParams, $params);
    }

    /**
     * 根据调整方式添加价格值到参数中
     */
    /**
     * @param array<string, mixed> $adjustmentParams
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function addPriceValuesToParams(array $adjustmentParams, array $params): array
    {
        if ('fixed' === $params['adjust_method']) {
            $adjustmentParams['cost_price'] = $params['price_value'];
            $adjustmentParams['selling_price'] = $params['price_value'];
        } else {
            $adjustmentParams['adjust_value'] = $params['adjust_value'];
        }

        return $adjustmentParams;
    }
}
