<?php

namespace Tourze\HotelContractBundle\Tests\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\KernelInterface;
use Tourze\HotelContractBundle\Entity\HotelContract;
use Tourze\HotelContractBundle\Enum\ContractStatusEnum;
use Tourze\HotelContractBundle\Enum\ContractTypeEnum;
use Tourze\HotelContractBundle\HotelContractBundle;
use Tourze\HotelContractBundle\Repository\HotelContractRepository;
use Tourze\HotelProfileBundle\Entity\Hotel;
use Tourze\HotelProfileBundle\Enum\HotelStatusEnum;
use Tourze\HotelProfileBundle\HotelProfileBundle;
use Tourze\IntegrationTestKernel\IntegrationTestKernel;

class HotelContractRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private HotelContractRepository $repository;

    protected static function createKernel(array $options = []): KernelInterface
    {
        $env = $options['environment'] ?? $_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? 'test';
        $debug = $options['debug'] ?? $_ENV['APP_DEBUG'] ?? $_SERVER['APP_DEBUG'] ?? true;

        return new IntegrationTestKernel($env, $debug, [
            HotelContractBundle::class => ['all' => true],
            HotelProfileBundle::class => ['all' => true],
        ]);
    }

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        /** @var HotelContractRepository $repository */
        $repository = static::getContainer()->get(HotelContractRepository::class);
        $this->repository = $repository;
        $this->cleanDatabase();
    }

    protected function tearDown(): void
    {
        $this->cleanDatabase();
        self::ensureKernelShutdown();
        parent::tearDown();
    }

    private function cleanDatabase(): void
    {
        $connection = $this->entityManager->getConnection();
        $connection->executeStatement('DELETE FROM hotel_contract');
        $connection->executeStatement('DELETE FROM ims_hotel_profile');
    }

    private function createTestHotel(): Hotel
    {
        $hotel = new Hotel();
        $hotel->setName('测试酒店');
        $hotel->setAddress('测试地址');
        $hotel->setStarLevel(4);
        $hotel->setContactPerson('测试联系人');
        $hotel->setPhone('13800138000');
        $hotel->setStatus(HotelStatusEnum::OPERATING);

        return $hotel;
    }

    private function createTestContract(Hotel $hotel, string $contractNo = 'CONTRACT-001'): HotelContract
    {
        $contract = new HotelContract();
        $contract->setContractNo($contractNo);
        $contract->setHotel($hotel);
        $contract->setContractType(ContractTypeEnum::FIXED_PRICE);
        $contract->setStartDate(new \DateTimeImmutable('2024-01-01'));
        $contract->setEndDate(new \DateTimeImmutable('2024-12-31'));
        $contract->setTotalRooms(100);
        $contract->setTotalDays(365);
        $contract->setTotalAmount('100000.00');
        $contract->setStatus(ContractStatusEnum::ACTIVE);
        $contract->setPriority(1);

        return $contract;
    }

    public function test_save_withValidContract_persistsToDatabase(): void
    {
        // Arrange
        $hotel = $this->createTestHotel();
        $this->entityManager->persist($hotel);
        $this->entityManager->flush();

        $contract = $this->createTestContract($hotel);

        // Act
        $this->repository->save($contract, true);

        // Assert
        $this->assertNotNull($contract->getId());
        $this->entityManager->refresh($contract);
        $this->assertEquals('CONTRACT-001', $contract->getContractNo());
        $this->assertEquals($hotel->getId(), $contract->getHotel()->getId());
    }

    public function test_save_withoutFlush_doesNotPersistImmediately(): void
    {
        // Arrange
        $hotel = $this->createTestHotel();
        $this->entityManager->persist($hotel);
        $this->entityManager->flush();

        $contract = $this->createTestContract($hotel);

        // Act
        $this->repository->save($contract, false);

        // Assert
        $this->assertNull($contract->getId());

        // Flush and verify
        $this->entityManager->flush();
        $this->assertNotNull($contract->getId());
    }

    public function test_remove_withValidContract_deletesFromDatabase(): void
    {
        // Arrange
        $hotel = $this->createTestHotel();
        $contract = $this->createTestContract($hotel);
        $this->entityManager->persist($hotel);
        $this->entityManager->persist($contract);
        $this->entityManager->flush();

        $contractId = $contract->getId();

        // Act
        $this->repository->remove($contract, true);

        // Assert
        $deletedContract = $this->repository->find($contractId);
        $this->assertNull($deletedContract);
    }

    public function test_findByHotelId_withExistingHotel_returnsContracts(): void
    {
        // Arrange
        $hotel = $this->createTestHotel();
        $contract1 = $this->createTestContract($hotel, 'CONTRACT-001');
        $contract1->setPriority(2);
        $contract2 = $this->createTestContract($hotel, 'CONTRACT-002');
        $contract2->setPriority(1);

        $this->entityManager->persist($hotel);
        $this->entityManager->persist($contract1);
        $this->entityManager->persist($contract2);
        $this->entityManager->flush();

        // Act
        $contracts = $this->repository->findByHotelId($hotel->getId());

        // Assert
        $this->assertCount(2, $contracts);
        // 按优先级降序排列
        $this->assertEquals('CONTRACT-001', $contracts[0]->getContractNo());
        $this->assertEquals('CONTRACT-002', $contracts[1]->getContractNo());
    }

    public function test_findByHotelId_withNonExistentHotel_returnsEmptyArray(): void
    {
        // Act
        $contracts = $this->repository->findByHotelId(99999);

        // Assert
        $this->assertEmpty($contracts);
    }

    public function test_findActiveContracts_returnsOnlyActiveContracts(): void
    {
        // Arrange
        $hotel = $this->createTestHotel();
        $this->entityManager->persist($hotel);

        $activeContract = $this->createTestContract($hotel, 'ACTIVE-001');
        $activeContract->setStatus(ContractStatusEnum::ACTIVE);

        $pendingContract = $this->createTestContract($hotel, 'PENDING-001');
        $pendingContract->setStatus(ContractStatusEnum::PENDING);

        $terminatedContract = $this->createTestContract($hotel, 'TERMINATED-001');
        $terminatedContract->setStatus(ContractStatusEnum::TERMINATED);

        $this->entityManager->persist($activeContract);
        $this->entityManager->persist($pendingContract);
        $this->entityManager->persist($terminatedContract);
        $this->entityManager->flush();

        // Act
        $activeContracts = $this->repository->findActiveContracts();

        // Assert
        $this->assertCount(1, $activeContracts);
        $this->assertEquals('ACTIVE-001', $activeContracts[0]->getContractNo());
        $this->assertEquals(ContractStatusEnum::ACTIVE, $activeContracts[0]->getStatus());
    }

    public function test_findByContractNo_withExistingContract_returnsContract(): void
    {
        // Arrange
        $hotel = $this->createTestHotel();
        $contract = $this->createTestContract($hotel, 'UNIQUE-CONTRACT-001');
        $this->entityManager->persist($hotel);
        $this->entityManager->persist($contract);
        $this->entityManager->flush();

        // Act
        $foundContract = $this->repository->findByContractNo('UNIQUE-CONTRACT-001');

        // Assert
        $this->assertNotNull($foundContract);
        $this->assertEquals('UNIQUE-CONTRACT-001', $foundContract->getContractNo());
    }

    public function test_findByContractNo_withNonExistentContract_returnsNull(): void
    {
        // Act
        $foundContract = $this->repository->findByContractNo('NON-EXISTENT');

        // Assert
        $this->assertNull($foundContract);
    }

    public function test_findContractsInDateRange_withOverlappingContracts_returnsMatches(): void
    {
        // Arrange
        $hotel = $this->createTestHotel();
        $this->entityManager->persist($hotel);

        // 2024年全年合同
        $yearContract = $this->createTestContract($hotel, 'YEAR-2024');
        $yearContract->setStartDate(new \DateTimeImmutable('2024-01-01'));
        $yearContract->setEndDate(new \DateTimeImmutable('2024-12-31'));
        $yearContract->setStatus(ContractStatusEnum::ACTIVE);
        $yearContract->setPriority(2);

        // 2024年Q1合同
        $q1Contract = $this->createTestContract($hotel, 'Q1-2024');
        $q1Contract->setStartDate(new \DateTimeImmutable('2024-01-01'));
        $q1Contract->setEndDate(new \DateTimeImmutable('2024-03-31'));
        $q1Contract->setStatus(ContractStatusEnum::ACTIVE);
        $q1Contract->setPriority(1);

        // 2023年合同（不在范围内）
        $oldContract = $this->createTestContract($hotel, 'YEAR-2023');
        $oldContract->setStartDate(new \DateTimeImmutable('2023-01-01'));
        $oldContract->setEndDate(new \DateTimeImmutable('2023-12-31'));
        $oldContract->setStatus(ContractStatusEnum::ACTIVE);

        // 待审批状态合同（不应包含）
        $pendingContract = $this->createTestContract($hotel, 'PENDING-2024');
        $pendingContract->setStartDate(new \DateTimeImmutable('2024-01-01'));
        $pendingContract->setEndDate(new \DateTimeImmutable('2024-12-31'));
        $pendingContract->setStatus(ContractStatusEnum::PENDING);

        $this->entityManager->persist($yearContract);
        $this->entityManager->persist($q1Contract);
        $this->entityManager->persist($oldContract);
        $this->entityManager->persist($pendingContract);
        $this->entityManager->flush();

        // Act - 查询2024年Q2范围
        $startDate = new \DateTimeImmutable('2024-04-01');
        $endDate = new \DateTimeImmutable('2024-06-30');
        $contracts = $this->repository->findContractsInDateRange($startDate, $endDate);

        // Assert
        $this->assertCount(1, $contracts);
        $this->assertEquals('YEAR-2024', $contracts[0]->getContractNo());
    }

    public function test_findContractsInDateRange_withMultipleMatches_returnsSortedByPriority(): void
    {
        // Arrange
        $hotel = $this->createTestHotel();
        $this->entityManager->persist($hotel);

        $lowPriorityContract = $this->createTestContract($hotel, 'LOW-PRIORITY');
        $lowPriorityContract->setStartDate(new \DateTimeImmutable('2024-01-01'));
        $lowPriorityContract->setEndDate(new \DateTimeImmutable('2024-12-31'));
        $lowPriorityContract->setStatus(ContractStatusEnum::ACTIVE);
        $lowPriorityContract->setPriority(1);

        $highPriorityContract = $this->createTestContract($hotel, 'HIGH-PRIORITY');
        $highPriorityContract->setStartDate(new \DateTimeImmutable('2024-01-01'));
        $highPriorityContract->setEndDate(new \DateTimeImmutable('2024-12-31'));
        $highPriorityContract->setStatus(ContractStatusEnum::ACTIVE);
        $highPriorityContract->setPriority(5);

        $this->entityManager->persist($lowPriorityContract);
        $this->entityManager->persist($highPriorityContract);
        $this->entityManager->flush();

        // Act
        $startDate = new \DateTimeImmutable('2024-06-01');
        $endDate = new \DateTimeImmutable('2024-06-30');
        $contracts = $this->repository->findContractsInDateRange($startDate, $endDate);

        // Assert
        $this->assertCount(2, $contracts);
        // 按优先级降序排列
        $this->assertEquals('HIGH-PRIORITY', $contracts[0]->getContractNo());
        $this->assertEquals('LOW-PRIORITY', $contracts[1]->getContractNo());
    }

    public function test_findContractsInDateRange_withNoMatches_returnsEmptyArray(): void
    {
        // Arrange
        $hotel = $this->createTestHotel();
        $contract = $this->createTestContract($hotel);
        $contract->setStartDate(new \DateTimeImmutable('2023-01-01'));
        $contract->setEndDate(new \DateTimeImmutable('2023-12-31'));
        $this->entityManager->persist($hotel);
        $this->entityManager->persist($contract);
        $this->entityManager->flush();

        // Act - 查询2024年
        $startDate = new \DateTimeImmutable('2024-01-01');
        $endDate = new \DateTimeImmutable('2024-12-31');
        $contracts = $this->repository->findContractsInDateRange($startDate, $endDate);

        // Assert
        $this->assertEmpty($contracts);
    }
}
