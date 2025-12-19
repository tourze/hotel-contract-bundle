<?php

namespace Tourze\HotelContractBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Tourze\HotelContractBundle\Entity\HotelContract;
use Tourze\HotelContractBundle\Enum\ContractStatusEnum;
use Tourze\PHPUnitSymfonyKernelTest\Attribute\AsRepository;

/**
 * 酒店合同仓库类
 *
 * @extends ServiceEntityRepository<HotelContract>
 */
#[AsRepository(entityClass: HotelContract::class)]
final class HotelContractRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HotelContract::class);
    }

    public function save(HotelContract $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(HotelContract $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * 查找某酒店的所有合同
     *
     * @return HotelContract[]
     */
    public function findByHotelId(int $hotelId): array
    {
        /** @var HotelContract[] */
        return $this->createQueryBuilder('hc')
            ->andWhere('hc.hotel = :hotelId')
            ->setParameter('hotelId', $hotelId)
            ->orderBy('hc.priority', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * 查找生效中的合同
     *
     * @return HotelContract[]
     */
    public function findActiveContracts(): array
    {
        /** @var HotelContract[] */
        return $this->createQueryBuilder('hc')
            ->andWhere('hc.status = :status')
            ->setParameter('status', ContractStatusEnum::ACTIVE)
            ->orderBy('hc.id', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * 根据合同编号查找合同
     */
    public function findByContractNo(string $contractNo): ?HotelContract
    {
        $result = $this->createQueryBuilder('hc')
            ->andWhere('hc.contractNo = :contractNo')
            ->setParameter('contractNo', $contractNo)
            ->getQuery()
            ->getOneOrNullResult()
        ;

        return $result instanceof HotelContract ? $result : null;
    }

    /**
     * 查找时间范围内有效的合同
     *
     * @return HotelContract[]
     */
    public function findContractsInDateRange(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        /** @var HotelContract[] */
        return $this->createQueryBuilder('hc')
            ->andWhere('hc.status = :status')
            ->andWhere('hc.startDate <= :endDate')
            ->andWhere('hc.endDate >= :startDate')
            ->setParameter('status', ContractStatusEnum::ACTIVE)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('hc.priority', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }
}
