<?php

namespace Tourze\HotelContractBundle\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Tourze\HotelContractBundle\Repository\DailyInventoryRepository;
use Tourze\HotelProfileBundle\Repository\RoomTypeRepository;

/**
 * 批量更新价格处理控制器
 */
class BatchPriceUpdateProcessController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RoomTypeRepository $roomTypeRepository,
        private readonly DailyInventoryRepository $dailyInventoryRepository,
    ) {}

    #[Route(path: '/admin/room-type-inventory/batch-price-update-process', name: 'admin_room_type_inventory_batch_price_update_process', methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        // 获取表单参数
        $roomTypeId = $request->request->getInt('room_type_id');
        $startDateStr = $request->request->get('start_date');
        $endDateStr = $request->request->get('end_date');
        $basePrice = $request->request->get('base_price');
        $sellPrice = $request->request->get('sell_price');
        $updateType = $request->request->get('update_type', 'both'); // both, base_only, sell_only

        // 验证参数
        if ($roomTypeId === 0 || !$startDateStr || !$endDateStr) {
            $this->addFlash('danger', '请填写所有必填字段');
            return $this->redirectToRoute('admin_room_type_inventory_batch_price_update');
        }

        try {
            $startDate = new \DateTimeImmutable($startDateStr);
            $endDate = new \DateTimeImmutable($endDateStr);
        } catch (\Exception $e) {
            $this->addFlash('danger', '日期格式不正确');
            return $this->redirectToRoute('admin_room_type_inventory_batch_price_update');
        }

        // 获取房型
        $roomType = $this->roomTypeRepository->find($roomTypeId);
        if ($roomType === null) {
            $this->addFlash('danger', '房型不存在');
            return $this->redirectToRoute('admin_room_type_inventory_batch_price_update');
        }

        // 开始事务
        $this->entityManager->getConnection()->beginTransaction();
        try {
            // 查询需要更新的库存记录
            $qb = $this->dailyInventoryRepository
                ->createQueryBuilder('di')
                ->where('di.roomType = :roomType')
                ->andWhere('di.date >= :startDate')
                ->andWhere('di.date <= :endDate')
                ->setParameter('roomType', $roomType)
                ->setParameter('startDate', $startDate)
                ->setParameter('endDate', $endDate);

            $inventories = $qb->getQuery()->getResult();

            $updateCount = 0;
            foreach ($inventories as $inventory) {
                $updated = false;

                // 根据更新类型更新价格
                if (($updateType === 'both' || $updateType === 'base_only') && $basePrice !== null && $basePrice !== '') {
                    $inventory->setCostPrice($basePrice);
                    $updated = true;
                }

                if (($updateType === 'both' || $updateType === 'sell_only') && $sellPrice !== null && $sellPrice !== '') {
                    $inventory->setSellingPrice($sellPrice);
                    $updated = true;
                }

                if ($updated) {
                    $updateCount++;
                }
            }

            $this->entityManager->flush();
            $this->entityManager->getConnection()->commit();

            $this->addFlash('success', sprintf('成功更新 %d 条库存记录的价格', $updateCount));
        } catch (\Exception $e) {
            $this->entityManager->getConnection()->rollBack();
            $this->addFlash('danger', '更新价格时发生错误: ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin');
    }
}
