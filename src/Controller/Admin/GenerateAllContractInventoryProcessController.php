<?php

namespace Tourze\HotelContractBundle\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Tourze\HotelContractBundle\Entity\DailyInventory;
use Tourze\HotelContractBundle\Entity\HotelContract;
use Tourze\HotelContractBundle\Enum\ContractStatusEnum;
use Tourze\HotelContractBundle\Enum\DailyInventoryStatusEnum;
use Tourze\HotelContractBundle\Repository\DailyInventoryRepository;
use Tourze\HotelContractBundle\Repository\HotelContractRepository;
use Tourze\HotelProfileBundle\Entity\RoomType;
use Tourze\HotelProfileBundle\Service\RoomTypeService;

/**
 * 为所有生效合同生成库存处理控制器
 */
final class GenerateAllContractInventoryProcessController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly HotelContractRepository $hotelContractRepository,
        private readonly RoomTypeService $roomTypeService,
        private readonly DailyInventoryRepository $dailyInventoryRepository,
    ) {
    }

    #[Route(path: '/admin/room-type-inventory/generate-all-contract-process', name: 'admin_room_type_inventory_generate_all_contract_process', methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        $days = $request->request->getInt('days', 30);
        $startDate = new \DateTimeImmutable();
        $endDate = (new \DateTimeImmutable())->modify('+' . $days . ' days');

        $this->entityManager->getConnection()->beginTransaction();
        try {
            $activeContracts = $this->getActiveContracts($startDate, $endDate);

            if ([] === $activeContracts) {
                $this->addFlash('warning', '未找到符合条件的生效合同');

                return $this->redirectToRoute('admin_room_type_inventory_generate_all_contract');
            }

            $stats = $this->processContracts($activeContracts, $startDate, $endDate);

            $this->entityManager->flush();
            $this->entityManager->getConnection()->commit();

            $this->addFlash('success', sprintf(
                '成功处理 %d 个合同，生成 %d 条库存记录，跳过 %d 条已存在记录，失败 %d 条',
                $stats['processedContracts'],
                $stats['totalGenerated'],
                $stats['totalSkipped'],
                $stats['totalFailed']
            ));
        } catch (\Exception $e) {
            $this->entityManager->getConnection()->rollBack();
            $this->addFlash('danger', '生成库存时发生错误: ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin');
    }

    /**
     * @return array<HotelContract>
     */
    private function getActiveContracts(\DateTimeImmutable $startDate, \DateTimeImmutable $endDate): array
    {
        /** @var array<HotelContract> */
        return $this->hotelContractRepository
            ->createQueryBuilder('c')
            ->where('c.status = :status')
            ->andWhere('c.startDate <= :endDate')
            ->andWhere('c.endDate >= :startDate')
            ->setParameter('status', ContractStatusEnum::ACTIVE)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @param array<HotelContract> $activeContracts
     * @return array<string, int>
     */
    private function processContracts(array $activeContracts, \DateTimeImmutable $startDate, \DateTimeImmutable $endDate): array
    {
        $stats = [
            'totalGenerated' => 0,
            'totalSkipped' => 0,
            'totalFailed' => 0,
            'processedContracts' => 0,
        ];

        foreach ($activeContracts as $contract) {
            ++$stats['processedContracts'];

            /** @var RoomType[] $roomTypes */
            $roomTypes = $this->getRoomTypesByContract($contract);

            if ([] === $roomTypes) {
                continue;
            }

            $stats = $this->processRoomTypesForContract($contract, $roomTypes, $startDate, $endDate, $stats);
        }

        return $stats;
    }

    /**
     * @param array<RoomType> $roomTypes
     * @param array<string, int> $stats
     * @return array<string, int>
     */
    private function processRoomTypesForContract(HotelContract $contract, array $roomTypes, \DateTimeImmutable $startDate, \DateTimeImmutable $endDate, array $stats): array
    {
        foreach ($roomTypes as $roomType) {
            $stats = $this->processRoomTypeInventory($roomType, $contract, $startDate, $endDate, $stats);
        }

        return $stats;
    }

    /**
     * @param array<string, int> $stats
     * @return array<string, int>
     */
    private function processRoomTypeInventory(RoomType $roomType, HotelContract $contract, \DateTimeImmutable $startDate, \DateTimeImmutable $endDate, array $stats): array
    {
        $currentDate = clone $startDate;

        while ($currentDate <= $endDate) {
            if (!$this->isDateInContractRange($currentDate, $contract)) {
                $currentDate = $currentDate->modify('+1 day');
                continue;
            }

            if ($this->inventoryExists($roomType, $currentDate, $contract)) {
                ++$stats['totalSkipped'];
            } elseif ($this->createInventory($roomType, $currentDate, $contract)) {
                ++$stats['totalGenerated'];
            } else {
                ++$stats['totalFailed'];
            }

            $currentDate = $currentDate->modify('+1 day');
        }

        return $stats;
    }

    private function isDateInContractRange(\DateTimeImmutable $date, HotelContract $contract): bool
    {
        return $date >= $contract->getStartDate() && $date <= $contract->getEndDate();
    }

    private function inventoryExists(RoomType $roomType, \DateTimeImmutable $date, HotelContract $contract): bool
    {
        return null !== $this->dailyInventoryRepository->findOneBy([
            'roomType' => $roomType,
            'date' => $date,
            'contract' => $contract,
        ]);
    }

    private function createInventory(RoomType $roomType, \DateTimeImmutable $date, HotelContract $contract): bool
    {
        try {
            $inventory = new DailyInventory();
            $inventory->setRoomType($roomType);
            $inventory->setDate($date);
            $inventory->setContract($contract);
            $inventory->setStatus(DailyInventoryStatusEnum::AVAILABLE);
            $inventory->setCostPrice('0');
            $inventory->setSellingPrice('0');
            $inventory->setCode(sprintf('%s-%s-%s', $roomType->getId(), $contract->getId(), $date->format('Ymd')));
            $inventory->setHotel($roomType->getHotel());

            $this->entityManager->persist($inventory);

            return true;
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * 获取合同关联的房型
     *
     * @return array<RoomType>
     */
    private function getRoomTypesByContract(HotelContract $contract): array
    {
        $hotel = $contract->getHotel();
        if (null === $hotel) {
            return [];
        }

        $hotelId = $hotel->getId();
        if (null === $hotelId) {
            return [];
        }

        return $this->roomTypeService->findRoomTypesByHotel($hotelId);
    }
}
