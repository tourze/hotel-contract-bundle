<?php

namespace Tourze\HotelContractBundle\Controller\Admin\API;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Tourze\HotelContractBundle\Entity\HotelContract;

/**
 * 合同API控制器
 */
class HotelContractsController extends AbstractController
{
    /**
     * 获取合同详情
     */
    #[Route('/admin/api/hotel-contracts/{id}', name: 'admin_api_hotel_contract_detail', methods: ['GET'])]
    public function getContractDetail(int $id, EntityManagerInterface $entityManager): JsonResponse
    {
        $contract = $entityManager->getRepository(HotelContract::class)->find($id);

        if ($contract === null) {
            return $this->json(['error' => 'Contract not found'], 404);
        }

        return $this->json([
            'id' => $contract->getId(),
            'contractNo' => $contract->getContractNo(),
            'hotelId' => $contract->getHotel()->getId(),
            'hotelName' => $contract->getHotel()->getName(),
            'startDate' => $contract->getStartDate()->format('Y-m-d'),
            'endDate' => $contract->getEndDate()->format('Y-m-d'),
            'totalRooms' => $contract->getTotalRooms(),
            'totalDays' => $contract->getTotalDays(),
            'totalAmount' => $contract->getTotalAmount(),
        ]);
    }
}
