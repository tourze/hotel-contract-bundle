<?php

namespace Tourze\HotelContractBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Tourze\HotelContractBundle\Entity\DailyInventory;
use Tourze\HotelContractBundle\Enum\DailyInventoryStatusEnum;
use Tourze\PHPUnitSymfonyKernelTest\Attribute\AsRepository;

/**
 * 日库存仓库类
 *
 * @extends ServiceEntityRepository<DailyInventory>
 */
#[AsRepository(entityClass: DailyInventory::class)]
final class DailyInventoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DailyInventory::class);
    }

    public function save(DailyInventory $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(DailyInventory $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * 根据房型ID和日期查找库存
     */
    public function findByRoomTypeAndDate(int $roomTypeId, \DateTimeInterface $date): ?DailyInventory
    {
        $result = $this->createQueryBuilder('di')
            ->andWhere('di.roomType = :roomTypeId')
            ->andWhere('di.date = :date')
            ->setParameter('roomTypeId', $roomTypeId)
            ->setParameter('date', $date->format('Y-m-d'))
            ->getQuery()
            ->getOneOrNullResult()
        ;

        return $result instanceof DailyInventory ? $result : null;
    }

    /**
     * 根据房间ID和日期查找库存
     *
     * @deprecated 使用 findByRoomTypeAndDate 替代
     */
    public function findByRoomAndDate(int $roomId, \DateTimeInterface $date): ?DailyInventory
    {
        return $this->findByRoomTypeAndDate($roomId, $date);
    }

    /**
     * 根据日期范围查找可用库存
     *
     * @return DailyInventory[]
     */
    public function findAvailableByDateRange(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        /** @var DailyInventory[] */
        return $this->createQueryBuilder('di')
            ->andWhere('di.date >= :startDate')
            ->andWhere('di.date <= :endDate')
            ->andWhere('di.status = :status')
            ->setParameter('startDate', $startDate->format('Y-m-d'))
            ->setParameter('endDate', $endDate->format('Y-m-d'))
            ->setParameter('status', DailyInventoryStatusEnum::AVAILABLE)
            ->orderBy('di.date', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * 根据合同ID查找库存
     *
     * @return DailyInventory[]
     */
    public function findByContractId(int $contractId): array
    {
        /** @var DailyInventory[] */
        return $this->createQueryBuilder('di')
            ->andWhere('di.contract = :contractId')
            ->setParameter('contractId', $contractId)
            ->orderBy('di.date', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * 查找指定日期的库存
     *
     * @return DailyInventory[]
     */
    public function findByDate(\DateTimeInterface $date): array
    {
        /** @var DailyInventory[] */
        return $this->createQueryBuilder('di')
            ->andWhere('di.date = :date')
            ->setParameter('date', $date->format('Y-m-d'))
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * 根据房型ID查找库存
     *
     * @return DailyInventory[]
     */
    public function findByRoomTypeId(int $roomTypeId): array
    {
        /** @var DailyInventory[] */
        return $this->createQueryBuilder('di')
            ->andWhere('di.roomType = :roomTypeId')
            ->setParameter('roomTypeId', $roomTypeId)
            ->orderBy('di.date', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * 根据房间ID查找库存
     *
     * @deprecated 使用 findByRoomTypeId 替代
     * @return DailyInventory[]
     */
    public function findByRoomId(int $roomId): array
    {
        return $this->findByRoomTypeId($roomId);
    }

    /**
     * 根据状态查找库存
     *
     * @return DailyInventory[]
     */
    public function findByStatus(DailyInventoryStatusEnum $status): array
    {
        /** @var DailyInventory[] */
        return $this->createQueryBuilder('di')
            ->andWhere('di.status = :status')
            ->setParameter('status', $status)
            ->orderBy('di.date', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * 查找特定合同的房型列表
     *
     * @return int[]
     */
    public function findDistinctRoomTypesByContract(int $contractId): array
    {
        $result = $this->createQueryBuilder('di')
            ->select('DISTINCT rt.id')
            ->join('di.roomType', 'rt')
            ->andWhere('di.contract = :contractId')
            ->setParameter('contractId', $contractId)
            ->getQuery()
            ->getArrayResult()
        ;

        return array_column($result, 'id');
    }

    /**
     * 根据合同和日期范围查询价格数据
     *
     * @return array<string, mixed>[]
     */
    public function findPriceDataByContractAndDateRange(
        int $contractId,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
    ): array {
        /** @var array<string, mixed>[] */
        return $this->createQueryBuilder('di')
            ->select(
                'di.id',
                'di.costPrice',
                'di.sellingPrice',
                'di.date',
                'di.code as inventoryCode',
                'rt.id as roomTypeId',
                'rt.name as roomTypeName'
            )
            ->join('di.roomType', 'rt')
            ->andWhere('di.contract = :contractId')
            ->andWhere('di.date >= :startDate')
            ->andWhere('di.date <= :endDate')
            ->setParameter('contractId', $contractId)
            ->setParameter('startDate', $startDate->format('Y-m-d'))
            ->setParameter('endDate', $endDate->format('Y-m-d'))
            ->orderBy('rt.id', 'ASC')
            ->addOrderBy('di.code', 'ASC')
            ->addOrderBy('di.date', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * 根据日期范围和条件查询库存
     *
     * @param array<string, mixed> $criteria
     * @return DailyInventory[]
     */
    public function findByDateRangeAndCriteria(
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        array $criteria = [],
    ): array {
        $qb = $this->createQueryBuilder('di')
            ->join('di.roomType', 'rt')
            ->join('di.hotel', 'h')
            ->andWhere('di.date >= :startDate')
            ->andWhere('di.date <= :endDate')
            ->setParameter('startDate', $startDate->format('Y-m-d'))
            ->setParameter('endDate', $endDate->format('Y-m-d'))
        ;

        // 添加自定义条件
        foreach ($criteria as $field => $value) {
            $paramName = str_replace('.', '_', $field);

            // 修正字段映射
            if ('room.hotel' === $field) {
                $field = 'di.hotel';
            } elseif ('room.roomType' === $field) {
                $field = 'di.roomType';
            }

            $qb->andWhere("{$field} = :{$paramName}")
                ->setParameter($paramName, $value)
            ;
        }

        $qb->orderBy('rt.id', 'ASC')
            ->addOrderBy('di.code', 'ASC')
            ->addOrderBy('di.date', 'ASC')
        ;

        /** @var DailyInventory[] */
        return $qb->getQuery()->getResult();
    }

    /**
     * 根据日期范围和星期几查询库存
     *
     * @param \DateTimeInterface $startDate 开始日期
     * @param \DateTimeInterface $endDate   结束日期
     * @param array<string, mixed> $criteria  查询条件
     * @param string             $dayFilter 日期筛选类型(weekend/weekday/custom)
     * @param int[]              $days      自定义星期几(0-6，0表示周日)
     *
     * @return DailyInventory[] 库存列表
     */
    public function findByDateRangeAndWeekdays(
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        array $criteria = [],
        ?string $dayFilter = null,
        array $days = [],
    ): array {
        $qb = $this->createQueryBuilder('di')
            ->join('di.roomType', 'rt')
            ->join('di.hotel', 'h')
            ->andWhere('di.date >= :startDate')
            ->andWhere('di.date <= :endDate')
            ->setParameter('startDate', $startDate->format('Y-m-d'))
            ->setParameter('endDate', $endDate->format('Y-m-d'))
        ;

        $this->applyCriteria($qb, $criteria);
        $this->applyDayFilter($qb, $dayFilter, $days);

        $qb->orderBy('rt.id', 'ASC')
            ->addOrderBy('di.code', 'ASC')
            ->addOrderBy('di.date', 'ASC')
        ;

        /** @var DailyInventory[] */
        return $qb->getQuery()->getResult();
    }

    /**
     * @param QueryBuilder $qb
     * @param array<string, mixed> $criteria
     */
    private function applyCriteria(QueryBuilder $qb, array $criteria): void
    {
        foreach ($criteria as $field => $value) {
            $paramName = str_replace('.', '_', $field);
            $field = $this->normalizeField($field);

            $qb->andWhere("{$field} = :{$paramName}")
                ->setParameter($paramName, $value)
            ;
        }
    }

    private function normalizeField(string $field): string
    {
        return match ($field) {
            'r.hotel' => 'di.hotel',
            'r.roomType' => 'di.roomType',
            default => $field,
        };
    }

    /**
     * @param QueryBuilder $qb
     * @param int[] $days
     */
    private function applyDayFilter(QueryBuilder $qb, ?string $dayFilter, array $days): void
    {
        if ('weekend' === $dayFilter) {
            $qb->andWhere('DAYOFWEEK(di.date) IN (1, 7)');
        } elseif ('weekday' === $dayFilter) {
            $qb->andWhere('DAYOFWEEK(di.date) BETWEEN 2 AND 6');
        } elseif ('custom' === $dayFilter && [] !== $days) {
            $mysqlDays = $this->convertToMysqlDays($days);
            $qb->andWhere('DAYOFWEEK(di.date) IN (:days)')
                ->setParameter('days', $mysqlDays)
            ;
        }
    }

    /**
     * @param int[] $days
     * @return int[]
     */
    private function convertToMysqlDays(array $days): array
    {
        $mysqlDays = [];
        foreach ($days as $day) {
            $mysqlDays[] = 0 === $day ? 1 : $day + 1;
        }

        return $mysqlDays;
    }

    /**
     * 根据房型和日期查找可用库存（按成本价升序排列）
     *
     * @return DailyInventory[]
     */
    public function findAvailableByRoomTypeAndDate(
        int $roomTypeId,
        \DateTimeInterface $date,
    ): array {
        /** @var DailyInventory[] */
        return $this->createQueryBuilder('di')
            ->where('di.roomType = :roomType')
            ->andWhere('di.date = :date')
            ->andWhere('di.status = :status')
            ->setParameter('roomType', $roomTypeId)
            ->setParameter('date', $date->format('Y-m-d'))
            ->setParameter('status', DailyInventoryStatusEnum::AVAILABLE)
            ->orderBy('di.costPrice', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }
}
