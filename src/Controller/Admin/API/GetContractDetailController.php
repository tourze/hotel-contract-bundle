<?php

namespace Tourze\HotelContractBundle\Controller\Admin\API;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Tourze\HotelContractBundle\Repository\HotelContractRepository;

/**
 * 获取合同详情控制器
 */
class GetContractDetailController extends AbstractController
{
    public function __construct(
        private readonly HotelContractRepository $hotelContractRepository,
    ) {}

    #[Route('/admin/api/hotel-contracts/{id}', name: 'admin_api_hotel_contract_detail', methods: ['GET'])]
    public function __invoke(int $id): JsonResponse
    {
        $contract = $this->hotelContractRepository->find($id);

        if ($contract === null) {
            return $this->json(['error' => 'Contract not found'], 404);
        }

        $hotel = $contract->getHotel();
        $startDate = $contract->getStartDate();
        $endDate = $contract->getEndDate();

        return $this->json([
            'id' => $contract->getId(),
            'contractNo' => $contract->getContractNo(),
            'hotelId' => null !== $hotel ? $hotel->getId() : null,
            'hotelName' => null !== $hotel ? $hotel->getName() : null,
            'startDate' => null !== $startDate ? $startDate->format('Y-m-d') : null,
            'endDate' => null !== $endDate ? $endDate->format('Y-m-d') : null,
            'totalRooms' => $contract->getTotalRooms(),
            'totalDays' => $contract->getTotalDays(),
            'totalAmount' => $contract->getTotalAmount(),
        ]);
    }
}
