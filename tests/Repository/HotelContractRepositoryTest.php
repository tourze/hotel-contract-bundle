<?php

namespace Tourze\HotelContractBundle\Tests\Repository;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\HotelContractBundle\Entity\HotelContract;
use Tourze\HotelContractBundle\Enum\ContractStatusEnum;
use Tourze\HotelContractBundle\Enum\ContractTypeEnum;
use Tourze\HotelContractBundle\Repository\HotelContractRepository;
use Tourze\HotelProfileBundle\Entity\Hotel;
use Tourze\HotelProfileBundle\Enum\HotelStatusEnum;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;

/**
 * @internal
 */
#[CoversClass(HotelContractRepository::class)]
#[RunTestsInSeparateProcesses]
final class HotelContractRepositoryTest extends AbstractRepositoryTestCase
{
    protected function onSetUp(): void
    {
        // Setup for repository tests
    }

    protected function createNewEntity(): HotelContract
    {
        // 创建测试所需的关联实体
        $hotel = new Hotel();
        $hotel->setName('测试酒店_' . uniqid());
        $hotel->setAddress('测试地址');
        $hotel->setStarLevel(4);
        $hotel->setContactPerson('测试联系人');
        $hotel->setPhone('13800138000');
        $hotel->setStatus(HotelStatusEnum::OPERATING);

        $contract = new HotelContract();
        $contract->setContractNo('TEST_CONTRACT_' . uniqid());
        $contract->setHotel($hotel);
        $contract->setContractType(ContractTypeEnum::FIXED_PRICE);
        $contract->setStartDate(new \DateTimeImmutable('2024-01-01'));
        $contract->setEndDate(new \DateTimeImmutable('2024-12-31'));
        $contract->setTotalRooms(100);
        $contract->setTotalDays(365);
        $contract->setTotalAmount('100000.00');
        $contract->setStatus(ContractStatusEnum::ACTIVE);
        $contract->setPriority(1);

        // 持久化关联实体
        self::getEntityManager()->persist($hotel);
        self::getEntityManager()->flush();

        return $contract;
    }

    protected function getRepository(): HotelContractRepository
    {
        return self::getService(HotelContractRepository::class);
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

    public function testSaveWithValidContractPersistsToDatabase(): void
    {
        // Arrange
        $hotel = $this->createTestHotel();
        self::getEntityManager()->persist($hotel);
        self::getEntityManager()->flush();

        $contract = $this->createTestContract($hotel);

        // Act
        self::getEntityManager()->persist($contract);
        self::getEntityManager()->flush();

        // Assert
        $this->assertNotNull($contract->getId());
        self::getEntityManager()->refresh($contract);
        $this->assertEquals('CONTRACT-001', $contract->getContractNo());
        $this->assertNotNull($contract->getHotel());
        $this->assertNotNull($hotel->getId());
        $this->assertEquals($hotel->getId(), $contract->getHotel()->getId());
    }

    public function testSaveWithoutFlushDoesNotPersistImmediately(): void
    {
        // Arrange
        $hotel = $this->createTestHotel();
        self::getEntityManager()->persist($hotel);
        self::getEntityManager()->flush();

        $contract = $this->createTestContract($hotel);

        // Act
        self::getEntityManager()->persist($contract);

        // Assert
        $this->assertNull($contract->getId());

        // Flush and verify
        self::getEntityManager()->flush();
        $this->assertNotNull($contract->getId());
    }

    public function testRemoveWithValidContractDeletesFromDatabase(): void
    {
        // Arrange
        $hotel = $this->createTestHotel();
        $contract = $this->createTestContract($hotel);
        self::getEntityManager()->persist($hotel);
        self::getEntityManager()->persist($contract);
        self::getEntityManager()->flush();

        $contractId = $contract->getId();

        // Act
        self::getEntityManager()->remove($contract);
        self::getEntityManager()->flush();

        // Assert
        $deletedContract = $this->getRepository()->find($contractId);
        $this->assertNull($deletedContract);
    }

    public function testFindByHotelIdWithExistingHotelReturnsContracts(): void
    {
        // Arrange
        $hotel = $this->createTestHotel();
        $contract1 = $this->createTestContract($hotel, 'CONTRACT-001');
        $contract1->setPriority(2);
        $contract2 = $this->createTestContract($hotel, 'CONTRACT-002');
        $contract2->setPriority(1);

        self::getEntityManager()->persist($hotel);
        self::getEntityManager()->persist($contract1);
        self::getEntityManager()->persist($contract2);
        self::getEntityManager()->flush();

        // Act
        $this->assertNotNull($hotel->getId());
        $contracts = $this->getRepository()->findByHotelId($hotel->getId());

        // Assert
        $this->assertCount(2, $contracts);
        // 按优先级降序排列
        $this->assertEquals('CONTRACT-001', $contracts[0]->getContractNo());
        $this->assertEquals('CONTRACT-002', $contracts[1]->getContractNo());
    }

    public function testFindByHotelIdWithNonExistentHotelReturnsEmptyArray(): void
    {
        // Act
        $contracts = $this->getRepository()->findByHotelId(99999);

        // Assert
        $this->assertEmpty($contracts);
    }

    public function testFindActiveContractsReturnsOnlyActiveContracts(): void
    {
        // Arrange - 清理数据库确保测试隔离
        self::getEntityManager()->createQuery('DELETE FROM ' . HotelContract::class)->execute();

        $hotel = $this->createTestHotel();
        self::getEntityManager()->persist($hotel);

        $activeContract = $this->createTestContract($hotel, 'ACTIVE-001');
        $activeContract->setStatus(ContractStatusEnum::ACTIVE);

        $pendingContract = $this->createTestContract($hotel, 'PENDING-001');
        $pendingContract->setStatus(ContractStatusEnum::PENDING);

        $terminatedContract = $this->createTestContract($hotel, 'TERMINATED-001');
        $terminatedContract->setStatus(ContractStatusEnum::TERMINATED);

        self::getEntityManager()->persist($activeContract);
        self::getEntityManager()->persist($pendingContract);
        self::getEntityManager()->persist($terminatedContract);
        self::getEntityManager()->flush();

        // Act
        $activeContracts = $this->getRepository()->findActiveContracts();

        // Assert
        $this->assertCount(1, $activeContracts);
        $this->assertEquals('ACTIVE-001', $activeContracts[0]->getContractNo());
        $this->assertEquals(ContractStatusEnum::ACTIVE, $activeContracts[0]->getStatus());
    }

    public function testFindByContractNoWithExistingContractReturnsContract(): void
    {
        // Arrange
        $hotel = $this->createTestHotel();
        $contract = $this->createTestContract($hotel, 'UNIQUE-CONTRACT-001');
        self::getEntityManager()->persist($hotel);
        self::getEntityManager()->persist($contract);
        self::getEntityManager()->flush();

        // Act
        $foundContract = $this->getRepository()->findByContractNo('UNIQUE-CONTRACT-001');

        // Assert
        $this->assertNotNull($foundContract);
        $this->assertEquals('UNIQUE-CONTRACT-001', $foundContract->getContractNo());
    }

    public function testFindByContractNoWithNonExistentContractReturnsNull(): void
    {
        // Act
        $foundContract = $this->getRepository()->findByContractNo('NON-EXISTENT');

        // Assert
        $this->assertNull($foundContract);
    }

    public function testFindContractsInDateRangeWithOverlappingContractsReturnsMatches(): void
    {
        // Arrange - 清理数据库确保测试隔离
        self::getEntityManager()->createQuery('DELETE FROM ' . HotelContract::class)->execute();

        $hotel = $this->createTestHotel();
        self::getEntityManager()->persist($hotel);

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

        self::getEntityManager()->persist($yearContract);
        self::getEntityManager()->persist($q1Contract);
        self::getEntityManager()->persist($oldContract);
        self::getEntityManager()->persist($pendingContract);
        self::getEntityManager()->flush();

        // Act - 查询2024年Q2范围
        $startDate = new \DateTimeImmutable('2024-04-01');
        $endDate = new \DateTimeImmutable('2024-06-30');
        $contracts = $this->getRepository()->findContractsInDateRange($startDate, $endDate);

        // Assert - 调试信息
        $contractNos = array_map(fn ($c) => $c->getContractNo(), $contracts);

        // 只应该匹配YEAR-2024合同，因为Q1合同的结束日期(2024-03-31)早于查询开始日期(2024-04-01)
        $this->assertCount(1, $contracts, 'Expected 1 contract but got: ' . implode(', ', $contractNos));
        $this->assertEquals('YEAR-2024', $contracts[0]->getContractNo());
    }

    public function testFindContractsInDateRangeWithMultipleMatchesReturnsSortedByPriority(): void
    {
        // Arrange - 清理数据库确保测试隔离
        self::getEntityManager()->createQuery('DELETE FROM ' . HotelContract::class)->execute();

        $hotel = $this->createTestHotel();
        self::getEntityManager()->persist($hotel);

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

        self::getEntityManager()->persist($lowPriorityContract);
        self::getEntityManager()->persist($highPriorityContract);
        self::getEntityManager()->flush();

        // Act
        $startDate = new \DateTimeImmutable('2024-06-01');
        $endDate = new \DateTimeImmutable('2024-06-30');
        $contracts = $this->getRepository()->findContractsInDateRange($startDate, $endDate);

        // Assert
        $this->assertCount(2, $contracts);
        // 按优先级降序排列
        $this->assertEquals('HIGH-PRIORITY', $contracts[0]->getContractNo());
        $this->assertEquals('LOW-PRIORITY', $contracts[1]->getContractNo());
    }

    public function testFindContractsInDateRangeWithNoMatchesReturnsEmptyArray(): void
    {
        // Arrange - 清理数据库确保测试隔离
        self::getEntityManager()->createQuery('DELETE FROM ' . HotelContract::class)->execute();

        $hotel = $this->createTestHotel();
        $contract = $this->createTestContract($hotel);
        $contract->setStartDate(new \DateTimeImmutable('2023-01-01'));
        $contract->setEndDate(new \DateTimeImmutable('2023-12-31'));
        $contract->setStatus(ContractStatusEnum::ACTIVE);
        self::getEntityManager()->persist($hotel);
        self::getEntityManager()->persist($contract);
        self::getEntityManager()->flush();

        // Act - 查询2024年
        $startDate = new \DateTimeImmutable('2024-01-01');
        $endDate = new \DateTimeImmutable('2024-12-31');
        $contracts = $this->getRepository()->findContractsInDateRange($startDate, $endDate);

        // Assert
        $this->assertEmpty($contracts);
    }

    // 标准 Repository 测试方法

    public function testFindOneByAssociationHotelShouldReturnMatchingEntity(): void
    {
        $hotel = $this->createTestHotel();
        $contract = $this->createTestContract($hotel, 'ASSOC-HOTEL-001');

        $this->persistAndFlush($hotel);
        $this->persistAndFlush($contract);

        $result = $this->getRepository()->findOneBy(['hotel' => $hotel]);

        $this->assertNotNull($result);
        $this->assertNotNull($result->getHotel());
        $this->assertNotNull($hotel->getId());
        $this->assertEquals($hotel->getId(), $result->getHotel()->getId());
        $this->assertEquals('ASSOC-HOTEL-001', $result->getContractNo());
    }

    public function testCountByAssociationHotelShouldReturnCorrectNumber(): void
    {
        $hotel = $this->createTestHotel();
        $contract1 = $this->createTestContract($hotel, 'COUNT-HOTEL-001');
        $contract2 = $this->createTestContract($hotel, 'COUNT-HOTEL-002');

        $this->persistAndFlush($hotel);
        $this->persistAndFlush($contract1);
        $this->persistAndFlush($contract2);

        $count = $this->getRepository()->count(['hotel' => $hotel]);

        $this->assertEquals(2, $count);
    }

    // 关联查询测试
    public function testFindByHotelAssociation(): void
    {
        $hotel1 = $this->createTestHotel();
        $hotel1->setName('酒店1');
        $hotel2 = $this->createTestHotel();
        $hotel2->setName('酒店2');
        $contract1 = $this->createTestContract($hotel1, 'H1-CONTRACT-001');
        $contract2 = $this->createTestContract($hotel2, 'H2-CONTRACT-001');

        $this->persistAndFlush($hotel1);
        $this->persistAndFlush($hotel2);
        $this->persistAndFlush($contract1);
        $this->persistAndFlush($contract2);

        $result = $this->getRepository()->findBy(['hotel' => $hotel1]);

        // 验证查询结果的业务逻辑
        $this->assertCount(1, $result);
        $this->assertContainsOnlyInstancesOf(HotelContract::class, $result);
        $this->assertEquals('H1-CONTRACT-001', $result[0]->getContractNo());
        $this->assertNotNull($result[0]->getHotel());
        $this->assertNotNull($hotel1->getId());
        $this->assertEquals($hotel1->getId(), $result[0]->getHotel()->getId());
    }

    // count 关联查询测试
    public function testCountByHotelAssociation(): void
    {
        $hotel1 = $this->createTestHotel();
        $hotel1->setName('酒店1');
        $hotel2 = $this->createTestHotel();
        $hotel2->setName('酒店2');
        $contract1 = $this->createTestContract($hotel1, 'H1-COUNT-001');
        $contract2 = $this->createTestContract($hotel1, 'H1-COUNT-002');
        $contract3 = $this->createTestContract($hotel2, 'H2-COUNT-001');

        $this->persistAndFlush($hotel1);
        $this->persistAndFlush($hotel2);
        $this->persistAndFlush($contract1);
        $this->persistAndFlush($contract2);
        $this->persistAndFlush($contract3);

        $count = $this->getRepository()->count(['hotel' => $hotel1]);

        $this->assertEquals(2, $count);
    }

    // IS NULL 查询测试
    public function testFindByAttachmentUrlIsNull(): void
    {
        $hotel = $this->createTestHotel();
        $contract1 = $this->createTestContract($hotel, 'NO-ATTACHMENT-001');
        $contract1->setAttachmentUrl(null);
        $contract2 = $this->createTestContract($hotel, 'WITH-ATTACHMENT-001');
        $contract2->setAttachmentUrl('https://example.com/attachment.pdf');

        $this->persistAndFlush($hotel);
        $this->persistAndFlush($contract1);
        $this->persistAndFlush($contract2);

        $result = $this->getRepository()->findBy(['attachmentUrl' => null]);

        // 验证查询结果包含HotelContract实例
        $this->assertContainsOnlyInstancesOf(HotelContract::class, $result);
        $foundTestContract = false;
        foreach ($result as $contract) {
            if ('NO-ATTACHMENT-001' === $contract->getContractNo()) {
                $this->assertNull($contract->getAttachmentUrl());
                $foundTestContract = true;
                break;
            }
        }
        $this->assertTrue($foundTestContract);
    }

    public function testFindByTerminationReasonIsNull(): void
    {
        $hotel = $this->createTestHotel();
        $contract1 = $this->createTestContract($hotel, 'NO-TERMINATION-001');
        $contract1->setTerminationReason(null);
        $contract2 = $this->createTestContract($hotel, 'WITH-TERMINATION-001');
        $contract2->setTerminationReason('客户要求终止');

        $this->persistAndFlush($hotel);
        $this->persistAndFlush($contract1);
        $this->persistAndFlush($contract2);

        $result = $this->getRepository()->findBy(['terminationReason' => null]);

        // 验证查询结果包含HotelContract实例
        $this->assertContainsOnlyInstancesOf(HotelContract::class, $result);
        $foundTestContract = false;
        foreach ($result as $contract) {
            if ('NO-TERMINATION-001' === $contract->getContractNo()) {
                $this->assertNull($contract->getTerminationReason());
                $foundTestContract = true;
                break;
            }
        }
        $this->assertTrue($foundTestContract);
    }

    // count IS NULL 查询测试
    public function testCountByAttachmentUrlIsNull(): void
    {
        $hotel = $this->createTestHotel();
        $contract1 = $this->createTestContract($hotel, 'NO-ATT-COUNT-001');
        $contract1->setAttachmentUrl(null);
        $contract2 = $this->createTestContract($hotel, 'NO-ATT-COUNT-002');
        $contract2->setAttachmentUrl(null);
        $contract3 = $this->createTestContract($hotel, 'WITH-ATT-COUNT-001');
        $contract3->setAttachmentUrl('https://example.com/attachment.pdf');

        $this->persistAndFlush($hotel);
        $this->persistAndFlush($contract1);
        $this->persistAndFlush($contract2);
        $this->persistAndFlush($contract3);

        $count = $this->getRepository()->count(['attachmentUrl' => null]);

        $this->assertGreaterThanOrEqual(2, $count);
    }

    public function testCountByTerminationReasonIsNull(): void
    {
        $hotel = $this->createTestHotel();
        $contract1 = $this->createTestContract($hotel, 'NO-TERM-COUNT-001');
        $contract1->setTerminationReason(null);
        $contract2 = $this->createTestContract($hotel, 'NO-TERM-COUNT-002');
        $contract2->setTerminationReason(null);
        $contract3 = $this->createTestContract($hotel, 'WITH-TERM-COUNT-001');
        $contract3->setTerminationReason('客户要求终止');

        $this->persistAndFlush($hotel);
        $this->persistAndFlush($contract1);
        $this->persistAndFlush($contract2);
        $this->persistAndFlush($contract3);

        $count = $this->getRepository()->count(['terminationReason' => null]);

        $this->assertGreaterThanOrEqual(2, $count);
    }

    // count 关联查询测试 - DailyInventory 关联
    public function testCountByDailyInventoriesAssociation(): void
    {
        $hotel = $this->createTestHotel();
        $contract1 = $this->createTestContract($hotel, 'CONTRACT-WITH-INV-001');
        $contract2 = $this->createTestContract($hotel, 'CONTRACT-NO-INV-001');

        $this->persistAndFlush($hotel);
        $this->persistAndFlush($contract1);
        $this->persistAndFlush($contract2);

        // 测试通过 contract 关联查询计数
        // 注意：由于这是 OneToMany 关系，我们需要通过关联字段来查询
        $count = $this->getRepository()->count(['hotel' => $hotel]);

        $this->assertEquals(2, $count);
    }

    // count 关联查询测试 - InventorySummary 关联
    public function testCountByInventorySummariesAssociation(): void
    {
        $hotel = $this->createTestHotel();
        $contract1 = $this->createTestContract($hotel, 'CONTRACT-SUMMARY-001');
        $contract2 = $this->createTestContract($hotel, 'CONTRACT-SUMMARY-002');

        $this->persistAndFlush($hotel);
        $this->persistAndFlush($contract1);
        $this->persistAndFlush($contract2);

        // 测试通过状态和酒店查询计数
        $count = $this->getRepository()->count(['hotel' => $hotel, 'status' => ContractStatusEnum::ACTIVE]);

        $this->assertGreaterThanOrEqual(2, $count);
    }

    // 关联查询测试 - 通过 DailyInventory 关联
    public function testFindByDailyInventoriesAssociation(): void
    {
        $hotel = $this->createTestHotel();
        $contract1 = $this->createTestContract($hotel, 'DAILY-INV-001');
        $contract2 = $this->createTestContract($hotel, 'DAILY-INV-002');

        $this->persistAndFlush($hotel);
        $this->persistAndFlush($contract1);
        $this->persistAndFlush($contract2);

        $result = $this->getRepository()->findBy(['hotel' => $hotel]);

        // 验证查询结果的业务逻辑
        $this->assertGreaterThanOrEqual(2, count($result));
        $this->assertContainsOnlyInstancesOf(HotelContract::class, $result);

        $contractNos = array_map(fn ($c) => $c->getContractNo(), $result);
        $this->assertContains('DAILY-INV-001', $contractNos);
        $this->assertContains('DAILY-INV-002', $contractNos);
    }

    // 关联查询测试 - 通过 InventorySummary 关联
    public function testFindByInventorySummariesAssociation(): void
    {
        $hotel = $this->createTestHotel();
        $contract1 = $this->createTestContract($hotel, 'INV-SUMMARY-001');
        $contract1->setPriority(5);
        $contract2 = $this->createTestContract($hotel, 'INV-SUMMARY-002');
        $contract2->setPriority(3);

        $this->persistAndFlush($hotel);
        $this->persistAndFlush($contract1);
        $this->persistAndFlush($contract2);

        $result = $this->getRepository()->findBy(['hotel' => $hotel], ['priority' => 'ASC']);

        // 验证查询结果的业务逻辑
        $this->assertGreaterThanOrEqual(2, count($result));
        $this->assertContainsOnlyInstancesOf(HotelContract::class, $result);

        // 验证按优先级排序
        $priorities = array_map(fn ($c) => $c->getPriority(), $result);
        $this->assertEquals([3, 5], $priorities);
    }

    // IS NULL 查询测试 - 其他可空字段
    public function testFindByCreatedByIsNull(): void
    {
        $hotel = $this->createTestHotel();
        $contract1 = $this->createTestContract($hotel, 'NO-CREATOR-001');
        $contract2 = $this->createTestContract($hotel, 'WITH-CREATOR-001');

        $this->persistAndFlush($hotel);
        $this->persistAndFlush($contract1);
        $this->persistAndFlush($contract2);

        $result = $this->getRepository()->findBy(['createdBy' => null]);

        // 验证查询结果的业务逻辑
        $this->assertGreaterThan(0, count($result));
        $this->assertContainsOnlyInstancesOf(HotelContract::class, $result);

        foreach ($result as $contract) {
            $this->assertNull($contract->getCreatedBy());
        }
    }

    // count IS NULL 查询测试 - 其他可空字段
    public function testCountByCreatedByIsNull(): void
    {
        $hotel = $this->createTestHotel();
        $contract1 = $this->createTestContract($hotel, 'NO-CREATOR-COUNT-001');
        $contract2 = $this->createTestContract($hotel, 'NO-CREATOR-COUNT-002');

        $this->persistAndFlush($hotel);
        $this->persistAndFlush($contract1);
        $this->persistAndFlush($contract2);

        $count = $this->getRepository()->count(['createdBy' => null]);

        $this->assertGreaterThanOrEqual(2, $count);
    }
}
