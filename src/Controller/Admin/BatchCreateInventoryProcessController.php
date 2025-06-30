<?php

namespace Tourze\HotelContractBundle\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Tourze\HotelContractBundle\Entity\DailyInventory;
use Tourze\HotelContractBundle\Enum\DailyInventoryStatusEnum;
use Tourze\HotelContractBundle\Repository\DailyInventoryRepository;
use Tourze\HotelContractBundle\Repository\HotelContractRepository;
use Tourze\HotelProfileBundle\Repository\RoomTypeRepository;

/**
 * 批量创建库存处理控制器
 */
class BatchCreateInventoryProcessController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RoomTypeRepository $roomTypeRepository,
        private readonly HotelContractRepository $hotelContractRepository,
        private readonly DailyInventoryRepository $dailyInventoryRepository,
    ) {}

    #[Route(path: '/admin/room-type-inventory/batch-create-process', name: 'admin_room_type_inventory_batch_create_process', methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        // 获取表单参数
        $roomTypeId = $request->request->getInt('room_type_id');
        $contractId = $request->request->getInt('contract_id');
        $startDateStr = $request->request->get('start_date');
        $endDateStr = $request->request->get('end_date');
        $quantity = $request->request->getInt('quantity', 10);
        $basePrice = $request->request->get('base_price', '0');
        $sellPrice = $request->request->get('sell_price', '0');

        // 验证参数
        if ($roomTypeId === 0 || $contractId === 0 || !$startDateStr || !$endDateStr) {
            $this->addFlash('danger', '请填写所有必填字段');
            return $this->redirectToRoute('admin_room_type_inventory_batch_create');
        }

        try {
            $startDate = new \DateTimeImmutable($startDateStr);
            $endDate = new \DateTimeImmutable($endDateStr);
        } catch (\Exception $e) {
            $this->addFlash('danger', '日期格式不正确');
            return $this->redirectToRoute('admin_room_type_inventory_batch_create');
        }

        // 获取房型和合同
        $roomType = $this->roomTypeRepository->find($roomTypeId);
        $contract = $this->hotelContractRepository->find($contractId);

        if ($roomType === null || $contract === null) {
            $this->addFlash('danger', '房型或合同不存在');
            return $this->redirectToRoute('admin_room_type_inventory_batch_create');
        }

        // 开始事务
        $this->entityManager->getConnection()->beginTransaction();
        try {
            $createdCount = 0;
            $skippedCount = 0;
            $currentDate = clone $startDate;

            while ($currentDate <= $endDate) {
                // 检查是否已存在库存记录
                $existingInventory = $this->dailyInventoryRepository
                    ->findOneBy([
                        'roomType' => $roomType,
                        'date' => $currentDate,
                        'contract' => $contract,
                    ]);

                if ($existingInventory !== null) {
                    $skippedCount++;
                } else {
                    // 创建新的库存记录
                    $inventory = new DailyInventory();
                    $inventory->setRoomType($roomType);
                    $inventory->setDate($currentDate);
                    $inventory->setContract($contract);
                    $inventory->setStatus(DailyInventoryStatusEnum::AVAILABLE);
                    $inventory->setCostPrice($basePrice);
                    $inventory->setSellingPrice($sellPrice);
                    $inventory->setCode(sprintf('%s-%s-%s', $roomType->getId(), $contract->getId(), $currentDate->format('Ymd')));
                    $inventory->setHotel($roomType->getHotel());

                    $this->entityManager->persist($inventory);
                    $createdCount++;
                }

                $currentDate = $currentDate->modify('+1 day');
            }

            $this->entityManager->flush();
            $this->entityManager->getConnection()->commit();

            $this->addFlash('success', sprintf('成功创建 %d 条库存记录，跳过 %d 条已存在记录', $createdCount, $skippedCount));
        } catch (\Exception $e) {
            $this->entityManager->getConnection()->rollBack();
            $this->addFlash('danger', '创建库存时发生错误: ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin');
    }
}
