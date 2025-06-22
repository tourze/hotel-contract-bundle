<?php

namespace Tourze\HotelContractBundle\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * 为所有生效合同生成库存表单控制器
 */
class GenerateAllContractInventoryFormController extends AbstractController
{
    #[Route('/admin/room-type-inventory/generate-all-contract', name: 'admin_room_type_inventory_generate_all_contract')]
    public function __invoke(): Response
    {
        return $this->render('@HotelContract/admin/room_type_inventory/generate_all_contract.html.twig', [
            'days' => 30, // 默认生成30天的库存
        ]);
    }
}