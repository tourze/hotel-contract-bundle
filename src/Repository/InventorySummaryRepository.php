<?php

namespace Tourze\HotelContractBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Tourze\HotelContractBundle\Entity\InventorySummary;
use Tourze\HotelContractBundle\Enum\InventorySummaryStatusEnum;

/**
 * 库存统计仓库类
 *
 * @extends ServiceEntityRepository<InventorySummary>
 *
 * @method InventorySummary|null find($id, $lockMode = null, $lockVersion = null)
 * @method InventorySummary|null findOneBy(array $criteria, array $orderBy = null)
 * @method InventorySummary[]    findAll()
 * @method InventorySummary[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class InventorySummaryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InventorySummary::class);
    }

    /**
     * 保存库存统计实体
     */
    public function save(InventorySummary $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * 删除库存统计实体
     */
    public function remove(InventorySummary $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * 根据酒店ID、房型ID和日期查找库存统计
     */
    public function findByHotelRoomTypeAndDate(int $hotelId, int $roomTypeId, \DateTimeInterface $date): ?InventorySummary
    {
        return $this->createQueryBuilder('is')
            ->andWhere('is.hotel = :hotelId')
            ->andWhere('is.roomType = :roomTypeId')
            ->andWhere('is.date = :date')
            ->setParameter('hotelId', $hotelId)
            ->setParameter('roomTypeId', $roomTypeId)
            ->setParameter('date', $date)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * 根据日期范围查找库存统计
     */
    public function findByDateRange(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        return $this->createQueryBuilder('is')
            ->andWhere('is.date >= :startDate')
            ->andWhere('is.date <= :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('is.date', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * 根据酒店ID查找库存统计
     */
    public function findByHotelId(int $hotelId): array
    {
        return $this->createQueryBuilder('is')
            ->andWhere('is.hotel = :hotelId')
            ->setParameter('hotelId', $hotelId)
            ->orderBy('is.date', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * 根据房型ID查找库存统计
     */
    public function findByRoomTypeId(int $roomTypeId): array
    {
        return $this->createQueryBuilder('is')
            ->andWhere('is.roomType = :roomTypeId')
            ->setParameter('roomTypeId', $roomTypeId)
            ->orderBy('is.date', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * 查找指定日期的库存统计
     */
    public function findByDate(\DateTimeInterface $date): array
    {
        return $this->createQueryBuilder('is')
            ->andWhere('is.date = :date')
            ->setParameter('date', $date)
            ->getQuery()
            ->getResult();
    }

    /**
     * 根据状态查找库存统计
     */
    public function findByStatus(InventorySummaryStatusEnum $status): array
    {
        return $this->createQueryBuilder('is')
            ->andWhere('is.status = :status')
            ->setParameter('status', $status)
            ->orderBy('is.date', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * 查找预警状态的库存记录
     */
    public function findWarningInventory(): array
    {
        return $this->createQueryBuilder('is')
            ->andWhere('is.status = :warning')
            ->setParameter('warning', InventorySummaryStatusEnum::WARNING)
            ->orderBy('is.date', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * 查找售罄状态的库存记录
     */
    public function findSoldOutInventory(): array
    {
        return $this->createQueryBuilder('is')
            ->andWhere('is.status = :soldOut')
            ->setParameter('soldOut', InventorySummaryStatusEnum::SOLD_OUT)
            ->orderBy('is.date', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
