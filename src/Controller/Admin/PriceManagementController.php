<?php

namespace Tourze\HotelContractBundle\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Tourze\HotelContractBundle\Repository\DailyInventoryRepository;
use Tourze\HotelContractBundle\Repository\HotelContractRepository;
use Tourze\HotelContractBundle\Service\InventoryUpdateService;
use Tourze\HotelProfileBundle\Repository\HotelRepository;
use Tourze\HotelProfileBundle\Repository\RoomTypeRepository;

#[Route('/admin/price')]
class PriceManagementController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly InventoryUpdateService $updateService,
        private readonly HotelRepository $hotelRepository,
        private readonly RoomTypeRepository $roomTypeRepository,
        private readonly HotelContractRepository $contractRepository,
        private readonly DailyInventoryRepository $dailyInventoryRepository
    ) {
    }

    /**
     * 合同价格日历管理
     */
    #[Route('/contract-calendar', name: 'admin_price_contract_calendar')]
    public function contractPriceCalendar(Request $request): Response
    {
        $contractId = $request->query->get('contract');
        $month = $request->query->get('month', date('Y-m'));
        
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

        return $this->render('@HotelContract/admin/price/contract_calendar.html.twig', [
            'contracts' => $contracts,
            'selectedContract' => $contract,
            'month' => $month,
            'calendarData' => $calendarData,
            'roomTypes' => $roomTypes,
            'currentMonth' => $startDate->format('Y年m月')
        ]);
    }

    /**
     * 更新合同价格
     */
    #[Route('/update-contract-price', name: 'admin_price_update_contract_price', methods: ['POST'])]
    public function updateContractPrice(Request $request): Response
    {
        $inventoryId = $request->request->get('inventory_id');
        $costPrice = $request->request->get('cost_price');
        $redirectUrl = $request->request->get('redirect_url', $this->generateUrl('admin_price_contract_calendar'));
        
        if (!$inventoryId || $costPrice === null) {
            $this->addFlash('danger', '参数错误');
            return $this->redirect($redirectUrl);
        }
        
        // 查找并更新价格
        $inventory = $this->dailyInventoryRepository->find($inventoryId);
        if (!$inventory) {
            $this->addFlash('danger', '库存记录不存在');
            return $this->redirect($redirectUrl);
        }
        
        $inventory->setCostPrice((string)$costPrice);
        $this->entityManager->flush();
        
        $this->addFlash('success', '价格更新成功');
        return $this->redirect($redirectUrl);
    }

    /**
     * 销售价格管理
     */
    #[Route('/selling-price', name: 'admin_price_selling_price')]
    public function sellingPrice(Request $request): Response
    {
        $hotelId = $request->query->get('hotel');
        $roomTypeId = $request->query->get('room_type');
        $month = $request->query->get('month', date('Y-m'));
        
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

        return $this->render('@HotelContract/admin/price/selling_price.html.twig', [
            'hotels' => $hotels,
            'selectedHotel' => $hotel,
            'roomTypes' => $roomTypes,
            'selectedRoomType' => $roomType,
            'month' => $month,
            'calendarData' => $calendarData,
            'currentMonth' => $startDate->format('Y年m月')
        ]);
    }

    /**
     * 更新销售价格
     */
    #[Route('/update-selling-price', name: 'admin_price_update_selling_price', methods: ['POST'])]
    public function updateSellingPrice(Request $request): Response
    {
        $inventoryId = $request->request->get('inventory_id');
        $sellingPrice = $request->request->get('selling_price');
        $redirectUrl = $request->request->get('redirect_url', $this->generateUrl('admin_price_selling_price'));
        
        if (!$inventoryId || $sellingPrice === null) {
            $this->addFlash('danger', '参数错误');
            return $this->redirect($redirectUrl);
        }
        
        // 查找并更新价格
        $inventory = $this->dailyInventoryRepository->find($inventoryId);
        if (!$inventory) {
            $this->addFlash('danger', '库存记录不存在');
            return $this->redirect($redirectUrl);
        }
        
        $inventory->setSellingPrice((string)$sellingPrice);
        $this->entityManager->flush();
        
        $this->addFlash('success', '销售价格更新成功');
        return $this->redirect($redirectUrl);
    }

    /**
     * 批量调价页面
     */
    #[Route('/batch-adjustment', name: 'admin_price_batch_adjustment')]
    public function batchPriceAdjustment(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $hotelId = $request->request->get('hotel');
            $roomTypeId = $request->request->get('room_type');
            $startDate = $request->request->get('start_date');
            $endDate = $request->request->get('end_date');
            $priceType = $request->request->get('price_type');
            $adjustMethod = $request->request->get('adjust_method');
            $dayFilter = $request->request->get('day_filter');
            $days = $request->request->get('days', []);
            $reason = $request->request->get('reason');
            
            if (!$hotelId || !$startDate || !$endDate) {
                $this->addFlash('danger', '请填写必要的参数');
                return $this->redirectToRoute('admin_price_batch_adjustment');
            }
            
            $hotel = $this->hotelRepository->find($hotelId);
            $roomType = $roomTypeId ? $this->roomTypeRepository->find($roomTypeId) : null;
            
            if (!$hotel) {
                $this->addFlash('danger', '酒店不存在');
                return $this->redirectToRoute('admin_price_batch_adjustment');
            }
            
            // 准备调价参数
            $params = [
                'hotel' => $hotel,
                'room_type' => $roomType,
                'start_date' => new \DateTime($startDate),
                'end_date' => new \DateTime($endDate),
                'price_type' => $priceType,
                'adjust_method' => $adjustMethod,
                'day_filter' => $dayFilter,
                'days' => $days,
                'reason' => $reason,
            ];
            
            // 根据调整方式设置价格值
            if ($adjustMethod === 'fixed') {
                $params['cost_price'] = $request->request->get('price_value');
                $params['selling_price'] = $request->request->get('price_value');
            } else {
                $params['adjust_value'] = $request->request->get('adjust_value');
            }
            
            // 调用批量调价服务
            $result = $this->updateService->batchUpdateInventoryPrice($params);
            
            if ($result && $result['success']) {
                $this->addFlash('success', $result['message']);
            } else {
                $this->addFlash('danger', $result ? $result['message'] : '调价失败');
            }
            
            return $this->redirectToRoute('admin_price_batch_adjustment');
        }

        // 获取所有酒店和房型供选择
        $hotels = $this->hotelRepository->findAll();
        $roomTypes = $this->roomTypeRepository->findAll();

        return $this->render('@HotelContract/admin/price/batch_adjustment.html.twig', [
            'hotels' => $hotels,
            'room_types' => $roomTypes,
        ]);
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
