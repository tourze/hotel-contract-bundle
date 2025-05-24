<?php

namespace Tourze\HotelContractBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Tourze\HotelContractBundle\Entity\DailyInventory;
use Tourze\HotelContractBundle\Enum\DailyInventoryStatusEnum;

/**
 * 日库存仓库类
 *
 * @extends ServiceEntityRepository<DailyInventory>
 *
 * @method DailyInventory|null find($id, $lockMode = null, $lockVersion = null)
 * @method DailyInventory|null findOneBy(array $criteria, array $orderBy = null)
 * @method DailyInventory[]    findAll()
 * @method DailyInventory[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DailyInventoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DailyInventory::class);
    }

    /**
     * 保存日库存实体
     */
    public function save(DailyInventory $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * 删除日库存实体
     */
    public function remove(DailyInventory $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * 根据房间ID和日期查找库存
     */
    public function findByRoomAndDate(int $roomId, \DateTimeInterface $date): ?DailyInventory
    {
        return $this->createQueryBuilder('di')
            ->andWhere('di.room = :roomId')
            ->andWhere('di.date = :date')
            ->setParameter('roomId', $roomId)
            ->setParameter('date', $date)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * 根据日期范围查找可用库存
     */
    public function findAvailableByDateRange(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        return $this->createQueryBuilder('di')
            ->andWhere('di.date >= :startDate')
            ->andWhere('di.date <= :endDate')
            ->andWhere('di.status = :status')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->setParameter('status', DailyInventoryStatusEnum::AVAILABLE)
            ->setParameter('isAvailable', true)
            ->orderBy('di.date', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * 根据合同ID查找库存
     */
    public function findByContractId(int $contractId): array
    {
        return $this->createQueryBuilder('di')
            ->andWhere('di.contract = :contractId')
            ->setParameter('contractId', $contractId)
            ->orderBy('di.date', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * 查找指定日期的库存
     */
    public function findByDate(\DateTimeInterface $date): array
    {
        return $this->createQueryBuilder('di')
            ->andWhere('di.date = :date')
            ->setParameter('date', $date)
            ->getQuery()
            ->getResult();
    }

    /**
     * 根据房间ID查找库存
     */
    public function findByRoomId(int $roomId): array
    {
        return $this->createQueryBuilder('di')
            ->andWhere('di.room = :roomId')
            ->setParameter('roomId', $roomId)
            ->orderBy('di.date', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * 根据状态查找库存
     */
    public function findByStatus(DailyInventoryStatusEnum $status): array
    {
        return $this->createQueryBuilder('di')
            ->andWhere('di.status = :status')
            ->setParameter('status', $status)
            ->orderBy('di.date', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * 查找特定合同的房型列表
     */
    public function findDistinctRoomTypesByContract(int $contractId): array
    {
        $result = $this->createQueryBuilder('di')
            ->select('DISTINCT rt.id')
            ->join('di.roomType', 'rt')
            ->andWhere('di.contract = :contractId')
            ->setParameter('contractId', $contractId)
            ->getQuery()
            ->getArrayResult();

        return array_column($result, 'id');
    }

    /**
     * 根据合同和日期范围查询价格数据
     */
    public function findPriceDataByContractAndDateRange(
        int                $contractId,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate
    ): array
    {
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
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('rt.id', 'ASC')
            ->addOrderBy('di.code', 'ASC')
            ->addOrderBy('di.date', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * 根据日期范围和条件查询库存
     */
    public function findByDateRangeAndCriteria(
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        array              $criteria = []
    ): array
    {
        $qb = $this->createQueryBuilder('di')
            ->join('di.roomType', 'rt')
            ->join('di.hotel', 'h')
            ->andWhere('di.date >= :startDate')
            ->andWhere('di.date <= :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate);

        // 添加自定义条件
        foreach ($criteria as $field => $value) {
            $paramName = str_replace('.', '_', $field);

            // 修正字段映射
            if ($field === 'room.hotel') {
                $field = 'di.hotel';
            } elseif ($field === 'room.roomType') {
                $field = 'di.roomType';
            }

            $qb->andWhere("$field = :$paramName")
                ->setParameter($paramName, $value);
        }

        $qb->orderBy('rt.id', 'ASC')
            ->addOrderBy('di.code', 'ASC')
            ->addOrderBy('di.date', 'ASC');

        return $qb->getQuery()->getResult();
    }

    /**
     * 根据日期范围和星期几查询库存
     *
     * @param \DateTimeInterface $startDate 开始日期
     * @param \DateTimeInterface $endDate 结束日期
     * @param array $criteria 查询条件
     * @param string $dayFilter 日期筛选类型(weekend/weekday/custom)
     * @param array $days 自定义星期几(0-6，0表示周日)
     *
     * @return array 库存列表
     */
    public function findByDateRangeAndWeekdays(
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        array $criteria = [],
        ?string $dayFilter = null,
        array $days = []
    ): array
    {
        $qb = $this->createQueryBuilder('di')
            ->join('di.roomType', 'rt')
            ->join('di.hotel', 'h')
            ->andWhere('di.date >= :startDate')
            ->andWhere('di.date <= :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate);

        // 添加自定义条件
        foreach ($criteria as $field => $value) {
            $paramName = str_replace('.', '_', $field);

            // 修正字段映射
            if ($field === 'r.hotel') {
                $field = 'di.hotel';
            } elseif ($field === 'r.roomType') {
                $field = 'di.roomType';
            }

            $qb->andWhere("$field = :$paramName")
                ->setParameter($paramName, $value);
        }

        // 根据日期筛选
        if ($dayFilter === 'weekend') {
            // 星期六和星期日（SQL中日期函数返回1-7，1为周日，7为周六）
            $qb->andWhere('DAYOFWEEK(di.date) IN (1, 7)');
        } elseif ($dayFilter === 'weekday') {
            // 星期一到星期五
            $qb->andWhere('DAYOFWEEK(di.date) BETWEEN 2 AND 6');
        } elseif ($dayFilter === 'custom' && !empty($days)) {
            // 自定义星期几，需要转换PHP的0-6（0为周日）到MySQL的1-7
            $mysqlDays = [];
            foreach ($days as $day) {
                // 0(周日) => 1, 1-6(周一到周六) => 2-7
                $mysqlDays[] = $day == 0 ? 1 : $day + 1;
            }
            $qb->andWhere('DAYOFWEEK(di.date) IN (:days)')
                ->setParameter('days', $mysqlDays);
        }

        $qb->orderBy('rt.id', 'ASC')
            ->addOrderBy('di.code', 'ASC')
            ->addOrderBy('di.date', 'ASC');

        return $qb->getQuery()->getResult();
    }
}
