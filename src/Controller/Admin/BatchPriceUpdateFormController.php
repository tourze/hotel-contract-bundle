<?php

namespace Tourze\HotelContractBundle\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Tourze\HotelProfileBundle\Repository\RoomTypeRepository;

/**
 * 批量更新价格表单控制器
 */
class BatchPriceUpdateFormController extends AbstractController
{
    public function __construct(
        private readonly RoomTypeRepository $roomTypeRepository,
    ) {}

    #[Route('/admin/room-type-inventory/batch-price-update', name: 'admin_room_type_inventory_batch_price_update')]
    public function __invoke(Request $request): Response
    {
        // 获取所有房型
        $roomTypes = $this->roomTypeRepository
            ->createQueryBuilder('rt')
            ->leftJoin('rt.hotel', 'h')
            ->addSelect('h')
            ->orderBy('h.name', 'ASC')
            ->addOrderBy('rt.name', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->render('@HotelContract/admin/room_type_inventory/batch_price_update.html.twig', [
            'roomTypes' => $roomTypes,
        ]);
    }
}
