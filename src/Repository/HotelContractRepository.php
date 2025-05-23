<?php

namespace Tourze\HotelContractBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Tourze\HotelContractBundle\Entity\HotelContract;

/**
 * 酒店合同仓库类
 *
 * @extends ServiceEntityRepository<HotelContract>
 *
 * @method HotelContract|null find($id, $lockMode = null, $lockVersion = null)
 * @method HotelContract|null findOneBy(array $criteria, array $orderBy = null)
 * @method HotelContract[]    findAll()
 * @method HotelContract[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class HotelContractRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HotelContract::class);
    }

    /**
     * 保存酒店合同实体
     */
    public function save(HotelContract $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * 删除酒店合同实体
     */
    public function remove(HotelContract $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * 查找某酒店的所有合同
     */
    public function findByHotelId(int $hotelId): array
    {
        return $this->createQueryBuilder('hc')
            ->andWhere('hc.hotel = :hotelId')
            ->setParameter('hotelId', $hotelId)
            ->orderBy('hc.priority', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * 查找生效中的合同
     */
    public function findActiveContracts(): array
    {
        return $this->createQueryBuilder('hc')
            ->andWhere('hc.status = :status')
            ->setParameter('status', 'active')
            ->orderBy('hc.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * 根据合同编号查找合同
     */
    public function findByContractNo(string $contractNo): ?HotelContract
    {
        return $this->createQueryBuilder('hc')
            ->andWhere('hc.contractNo = :contractNo')
            ->setParameter('contractNo', $contractNo)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * 查找时间范围内有效的合同
     */
    public function findContractsInDateRange(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        return $this->createQueryBuilder('hc')
            ->andWhere('hc.status = :status')
            ->andWhere('hc.startDate <= :endDate')
            ->andWhere('hc.endDate >= :startDate')
            ->setParameter('status', 'active')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('hc.priority', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
