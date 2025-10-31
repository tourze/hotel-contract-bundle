<?php

declare(strict_types=1);

namespace Tourze\HotelContractBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Tourze\HotelContractBundle\Entity\DailyInventory;
use Tourze\HotelContractBundle\Enum\DailyInventoryStatusEnum;
use Tourze\HotelContractBundle\Repository\DailyInventoryRepository;

#[Autoconfigure(public: true)]
readonly class InventoryUpdateService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DailyInventoryRepository $inventoryRepository,
        private InventoryStatusUpdater $statusUpdater,
        private InventoryPriceUpdater $priceUpdater,
    ) {
    }

    /**
     * 批量调整库存状态
     * 不考虑并发
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function batchUpdateInventoryStatus(array $params): array
    {
        return $this->statusUpdater->batchUpdateInventoryStatus($params);
    }

    /**
     * 批量调整库存价格
     * 不考虑并发
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function batchUpdateInventoryPrice(array $params): array
    {
        return $this->priceUpdater->batchUpdateInventoryPrice($params);
    }

    /**
     * 清除日库存与合同的关联
     * 不考虑并发
     *
     * @param DailyInventory $inventory 日库存记录
     */
    public function clearInventoryContractAssociation(DailyInventory $inventory): void
    {
        $inventory->setContract(null);
        $inventory->setStatus(DailyInventoryStatusEnum::AVAILABLE);
        $this->entityManager->persist($inventory);
    }

    /**
     * 批量清除日期范围内的合同关联
     *
     * @param int                $hotelId    酒店ID
     * @param int|null           $roomTypeId 房型ID (可选)
     * @param \DateTimeInterface $startDate  开始日期
     * @param \DateTimeInterface $endDate    结束日期
     * @param int|null           $contractId 合同ID (可选，指定后只清除该合同关联)
     *
     * @return int 处理的记录数
     */
    public function batchClearContractAssociation(
        int $hotelId,
        ?\DateTimeInterface $startDate,
        ?\DateTimeInterface $endDate,
        ?int $roomTypeId = null,
        ?int $contractId = null,
    ): int {
        // 查找该酒店在日期范围内的所有库存记录
        $qb = $this->inventoryRepository->createQueryBuilder('di')
            ->where('di.hotel = :hotelId')
            ->setParameter('hotelId', $hotelId)
        ;

        if (null !== $startDate) {
            $qb->andWhere('di.date >= :startDate')
                ->setParameter('startDate', $startDate)
            ;
        }

        if (null !== $endDate) {
            $qb->andWhere('di.date <= :endDate')
                ->setParameter('endDate', $endDate)
            ;
        }

        if (null !== $roomTypeId) {
            $qb->andWhere('di.roomType = :roomTypeId')
                ->setParameter('roomTypeId', $roomTypeId)
            ;
        }

        if (null !== $contractId) {
            $qb->andWhere('di.contract = :contractId')
                ->setParameter('contractId', $contractId)
            ;
        }

        /** @var DailyInventory[] $inventories */
        $inventories = $qb->getQuery()->getResult();

        $processedCount = 0;
        foreach ($inventories as $inventory) {
            // 检查是否已被预订
            if (DailyInventoryStatusEnum::SOLD === $inventory->getStatus()) {
                // 已被预订的库存记录保持不变
                continue;
            }

            $this->clearInventoryContractAssociation($inventory);
            ++$processedCount;
        }

        if ($processedCount > 0) {
            $this->entityManager->flush();
        }

        return $processedCount;
    }
}
