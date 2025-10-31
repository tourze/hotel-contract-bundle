<?php

namespace Tourze\HotelContractBundle\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Tourze\HotelContractBundle\Entity\DailyInventory;
use Tourze\HotelContractBundle\Repository\DailyInventoryRepository;
use Tourze\HotelProfileBundle\Entity\RoomType;
use Tourze\HotelProfileBundle\Service\RoomTypeService;

/**
 * 批量更新价格处理控制器
 */
final class BatchPriceUpdateProcessController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RoomTypeService $roomTypeService,
        private readonly DailyInventoryRepository $dailyInventoryRepository,
    ) {
    }

    #[Route(path: '/admin/room-type-inventory/batch-price-update-process', name: 'admin_room_type_inventory_batch_price_update_process', methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        $formData = $this->extractFormData($request);

        $validationResult = $this->validateFormData($formData);
        if (null !== $validationResult) {
            return $validationResult;
        }

        [$startDate, $endDate] = $this->parseDates($formData);
        if (null === $startDate || null === $endDate) {
            $this->addFlash('danger', '日期格式不正确');

            return $this->redirectToRoute('admin_room_type_inventory_batch_price_update');
        }

        $roomTypeId = $formData['roomTypeId'];
        if (!\is_int($roomTypeId)) {
            $this->addFlash('danger', '房型ID无效');

            return $this->redirectToRoute('admin_room_type_inventory_batch_price_update');
        }

        $roomType = $this->roomTypeService->findRoomTypeById($roomTypeId);
        if (null === $roomType) {
            $this->addFlash('danger', '房型不存在');

            return $this->redirectToRoute('admin_room_type_inventory_batch_price_update');
        }

        $updateCount = $this->performBatchUpdate($roomType, $startDate, $endDate, $formData);

        return $this->redirectToRoute('admin');
    }

    /**
     * @return array<string, mixed>
     */
    private function extractFormData(Request $request): array
    {
        return [
            'roomTypeId' => $request->request->getInt('room_type_id'),
            'startDateStr' => $request->request->get('start_date'),
            'endDateStr' => $request->request->get('end_date'),
            'basePrice' => $request->request->get('base_price'),
            'sellPrice' => $request->request->get('sell_price'),
            'updateType' => $request->request->get('update_type', 'both'),
        ];
    }

    /**
     * @param array<string, mixed> $formData
     */
    private function validateFormData(array $formData): ?Response
    {
        if (0 === $formData['roomTypeId'] || '' === $formData['startDateStr'] || '' === $formData['endDateStr'] || null === $formData['startDateStr'] || null === $formData['endDateStr']) {
            $this->addFlash('danger', '请填写所有必填字段');

            return $this->redirectToRoute('admin_room_type_inventory_batch_price_update');
        }

        return null;
    }

    /**
     * @param array<string, mixed> $formData
     * @return array{0: \DateTimeImmutable|null, 1: \DateTimeImmutable|null}
     */
    private function parseDates(array $formData): array
    {
        try {
            $startDateStr = $formData['startDateStr'];
            $endDateStr = $formData['endDateStr'];

            if (!\is_string($startDateStr) || !\is_string($endDateStr)) {
                return [null, null];
            }

            $startDate = new \DateTimeImmutable($startDateStr);
            $endDate = new \DateTimeImmutable($endDateStr);

            return [$startDate, $endDate];
        } catch (\Exception $e) {
            return [null, null];
        }
    }

    /**
     * @param array<string, mixed> $formData
     */
    private function performBatchUpdate(RoomType $roomType, \DateTimeImmutable $startDate, \DateTimeImmutable $endDate, array $formData): int
    {
        $this->entityManager->getConnection()->beginTransaction();
        try {
            $inventories = $this->findInventoriesToUpdate($roomType, $startDate, $endDate);
            $updateCount = $this->updateInventoryPrices($inventories, $formData);

            $this->entityManager->flush();
            $this->entityManager->getConnection()->commit();

            $this->addFlash('success', sprintf('成功更新 %d 条库存记录的价格', $updateCount));

            return $updateCount;
        } catch (\Exception $e) {
            $this->entityManager->getConnection()->rollBack();
            $this->addFlash('danger', '更新价格时发生错误: ' . $e->getMessage());

            return 0;
        }
    }

    /**
     * @return array<DailyInventory>
     */
    private function findInventoriesToUpdate(RoomType $roomType, \DateTimeImmutable $startDate, \DateTimeImmutable $endDate): array
    {
        /** @var array<DailyInventory> */
        return $this->dailyInventoryRepository
            ->createQueryBuilder('di')
            ->where('di.roomType = :roomType')
            ->andWhere('di.date >= :startDate')
            ->andWhere('di.date <= :endDate')
            ->setParameter('roomType', $roomType)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @param array<DailyInventory> $inventories
     * @param array<string, mixed> $formData
     */
    private function updateInventoryPrices(array $inventories, array $formData): int
    {
        $updateCount = 0;
        foreach ($inventories as $inventory) {
            $updated = $this->updateInventoryPrice($inventory, $formData);
            if ($updated) {
                ++$updateCount;
            }
        }

        return $updateCount;
    }

    /**
     * @param array<string, mixed> $formData
     */
    private function updateInventoryPrice(DailyInventory $inventory, array $formData): bool
    {
        $updated = false;

        if ($this->shouldUpdateBasePrice($formData)) {
            $basePrice = $formData['basePrice'];
            $basePriceStr = \is_string($basePrice) ? $basePrice : '';
            if ('' !== $basePriceStr) {
                $inventory->setCostPrice($basePriceStr);
                $updated = true;
            }
        }

        if ($this->shouldUpdateSellPrice($formData)) {
            $sellPrice = $formData['sellPrice'];
            $sellPriceStr = \is_string($sellPrice) ? $sellPrice : '';
            if ('' !== $sellPriceStr) {
                $inventory->setSellingPrice($sellPriceStr);
                $updated = true;
            }
        }

        return $updated;
    }

    /**
     * @param array<string, mixed> $formData
     */
    private function shouldUpdateBasePrice(array $formData): bool
    {
        $updateType = $formData['updateType'];
        $basePrice = $formData['basePrice'];

        return ('both' === $updateType || 'base_only' === $updateType) && null !== $basePrice && '' !== $basePrice;
    }

    /**
     * @param array<string, mixed> $formData
     */
    private function shouldUpdateSellPrice(array $formData): bool
    {
        $updateType = $formData['updateType'];
        $sellPrice = $formData['sellPrice'];

        return ('both' === $updateType || 'sell_only' === $updateType) && null !== $sellPrice && '' !== $sellPrice;
    }
}
