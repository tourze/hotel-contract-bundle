<?php

namespace Tourze\HotelContractBundle\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Tourze\HotelContractBundle\Enum\ContractStatusEnum;
use Tourze\HotelContractBundle\Repository\HotelContractRepository;
use Tourze\HotelProfileBundle\Service\RoomTypeService;

/**
 * 批量创建库存表单控制器
 */
final class BatchCreateInventoryFormController extends AbstractController
{
    public function __construct(
        private readonly RoomTypeService $roomTypeService,
        private readonly HotelContractRepository $hotelContractRepository,
    ) {
    }

    #[Route(path: '/admin/room-type-inventory/batch-create', name: 'admin_room_type_inventory_batch_create')]
    public function __invoke(Request $request): Response
    {
        // 获取所有房型
        $roomTypes = $this->roomTypeService->getAllRoomTypesWithHotel();

        // 获取所有生效的合同
        $contracts = $this->hotelContractRepository
            ->createQueryBuilder('c')
            ->where('c.status = :status')
            ->setParameter('status', ContractStatusEnum::ACTIVE)
            ->orderBy('c.priority', 'DESC')
            ->getQuery()
            ->getResult()
        ;

        return $this->render('@HotelContract/admin/room_type_inventory/batch_create.html.twig', [
            'roomTypes' => $roomTypes,
            'contracts' => $contracts,
        ]);
    }
}
