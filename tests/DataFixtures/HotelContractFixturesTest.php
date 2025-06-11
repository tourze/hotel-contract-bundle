<?php

namespace Tourze\HotelContractBundle\Tests\DataFixtures;

use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Loader;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tourze\HotelContractBundle\DataFixtures\HotelContractFixtures;
use Tourze\HotelContractBundle\Entity\HotelContract;
use Tourze\HotelContractBundle\Enum\ContractStatusEnum;
use Tourze\HotelContractBundle\Enum\ContractTypeEnum;
use Tourze\HotelProfileBundle\DataFixtures\HotelFixtures;
use Tourze\HotelProfileBundle\Entity\Hotel;

class HotelContractFixturesTest extends KernelTestCase
{
    /**
     * 测试合同Fixture数据加载
     */
    public function testLoadFixtures(): void
    {
        $kernel = self::bootKernel();
        $entityManager = $kernel->getContainer()->get('doctrine')->getManager();

        $loader = new Loader();
        $loader->addFixture(new HotelFixtures());
        $loader->addFixture(new HotelContractFixtures());

        $purger = new ORMPurger($entityManager);
        $executor = new ORMExecutor($entityManager, $purger);
        $executor->execute($loader->getFixtures());

        // 验证合同数据是否被正确创建
        $contractRepository = $entityManager->getRepository(HotelContract::class);
        $contracts = $contractRepository->findAll();

        $this->assertNotEmpty($contracts, 'Fixture应该创建至少一个合同');
        $this->assertGreaterThanOrEqual(10, count($contracts), 'Fixture应该创建多个合同');
    }

    /**
     * 测试不同状态的合同创建
     */
    public function testContractStatusVariations(): void
    {
        $kernel = self::bootKernel();
        $entityManager = $kernel->getContainer()->get('doctrine')->getManager();

        $loader = new Loader();
        $loader->addFixture(new HotelFixtures());
        $loader->addFixture(new HotelContractFixtures());

        $purger = new ORMPurger($entityManager);
        $executor = new ORMExecutor($entityManager, $purger);
        $executor->execute($loader->getFixtures());

        $contractRepository = $entityManager->getRepository(HotelContract::class);

        // 验证存在不同状态的合同
        $activeContracts = $contractRepository->findBy(['status' => ContractStatusEnum::ACTIVE]);
        $pendingContracts = $contractRepository->findBy(['status' => ContractStatusEnum::PENDING]);
        $terminatedContracts = $contractRepository->findBy(['status' => ContractStatusEnum::TERMINATED]);

        $this->assertNotEmpty($activeContracts, '应该存在生效状态的合同');
        $this->assertNotEmpty($pendingContracts, '应该存在待生效状态的合同');
        $this->assertNotEmpty($terminatedContracts, '应该存在终止状态的合同');
    }

    /**
     * 测试不同类型的合同创建
     */
    public function testContractTypeVariations(): void
    {
        $kernel = self::bootKernel();
        $entityManager = $kernel->getContainer()->get('doctrine')->getManager();

        $loader = new Loader();
        $loader->addFixture(new HotelFixtures());
        $loader->addFixture(new HotelContractFixtures());

        $purger = new ORMPurger($entityManager);
        $executor = new ORMExecutor($entityManager, $purger);
        $executor->execute($loader->getFixtures());

        $contractRepository = $entityManager->getRepository(HotelContract::class);

        // 验证存在不同类型的合同
        $fixedPriceContracts = $contractRepository->findBy(['contractType' => ContractTypeEnum::FIXED_PRICE]);
        $dynamicPriceContracts = $contractRepository->findBy(['contractType' => ContractTypeEnum::DYNAMIC_PRICE]);

        $this->assertNotEmpty($fixedPriceContracts, '应该存在固定价格类型的合同');
        $this->assertNotEmpty($dynamicPriceContracts, '应该存在动态价格类型的合同');
    }

    /**
     * 测试合同编号格式
     */
    public function testContractNumberFormat(): void
    {
        $kernel = self::bootKernel();
        $entityManager = $kernel->getContainer()->get('doctrine')->getManager();

        $loader = new Loader();
        $loader->addFixture(new HotelFixtures());
        $loader->addFixture(new HotelContractFixtures());

        $purger = new ORMPurger($entityManager);
        $executor = new ORMExecutor($entityManager, $purger);
        $executor->execute($loader->getFixtures());

        $contractRepository = $entityManager->getRepository(HotelContract::class);
        $contracts = $contractRepository->findAll();

        foreach ($contracts as $contract) {
            $contractNo = $contract->getContractNo();
            $this->assertStringStartsWith('HT', $contractNo, '合同编号应该以HT开头');
            $this->assertGreaterThanOrEqual(8, strlen($contractNo), '合同编号长度应该合理');
        }
    }

    /**
     * 测试合同与酒店的关联
     */
    public function testContractHotelAssociation(): void
    {
        $kernel = self::bootKernel();
        $entityManager = $kernel->getContainer()->get('doctrine')->getManager();

        $loader = new Loader();
        $loader->addFixture(new HotelFixtures());
        $loader->addFixture(new HotelContractFixtures());

        $purger = new ORMPurger($entityManager);
        $executor = new ORMExecutor($entityManager, $purger);
        $executor->execute($loader->getFixtures());

        $contractRepository = $entityManager->getRepository(HotelContract::class);
        $hotelRepository = $entityManager->getRepository(Hotel::class);

        $contracts = $contractRepository->findAll();
        $hotels = $hotelRepository->findAll();

        $this->assertNotEmpty($hotels, '应该存在酒店数据');

        foreach ($contracts as $contract) {
            $this->assertNotNull($contract->getHotel(), '每个合同都应该关联到一个酒店');
            $this->assertInstanceOf(Hotel::class, $contract->getHotel(), '关联的酒店应该是Hotel实例');
        }
    }

    /**
     * 测试合同优先级设置
     */
    public function testContractPriority(): void
    {
        $kernel = self::bootKernel();
        $entityManager = $kernel->getContainer()->get('doctrine')->getManager();

        $loader = new Loader();
        $loader->addFixture(new HotelFixtures());
        $loader->addFixture(new HotelContractFixtures());

        $purger = new ORMPurger($entityManager);
        $executor = new ORMExecutor($entityManager, $purger);
        $executor->execute($loader->getFixtures());

        $contractRepository = $entityManager->getRepository(HotelContract::class);
        $contracts = $contractRepository->findAll();

        foreach ($contracts as $contract) {
            $this->assertIsInt($contract->getPriority(), '优先级应该是整数');
            $this->assertGreaterThan(0, $contract->getPriority(), '优先级应该大于0');
        }

        // 验证存在不同优先级的合同
        $priorities = array_map(fn($contract) => $contract->getPriority(), $contracts);
        $uniquePriorities = array_unique($priorities);
        $this->assertGreaterThan(1, count($uniquePriorities), '应该存在不同优先级的合同');
    }

    /**
     * 测试终止合同的终止原因
     */
    public function testTerminatedContractReason(): void
    {
        $kernel = self::bootKernel();
        $entityManager = $kernel->getContainer()->get('doctrine')->getManager();

        $loader = new Loader();
        $loader->addFixture(new HotelFixtures());
        $loader->addFixture(new HotelContractFixtures());

        $purger = new ORMPurger($entityManager);
        $executor = new ORMExecutor($entityManager, $purger);
        $executor->execute($loader->getFixtures());

        $contractRepository = $entityManager->getRepository(HotelContract::class);
        $terminatedContracts = $contractRepository->findBy(['status' => ContractStatusEnum::TERMINATED]);

        $this->assertNotEmpty($terminatedContracts, '应该存在终止状态的合同');

        foreach ($terminatedContracts as $contract) {
            $this->assertNotNull($contract->getTerminationReason(), '终止的合同应该有终止原因');
            $this->assertNotEmpty($contract->getTerminationReason(), '终止原因不应该为空');
        }
    }

    /**
     * 测试合同金额和房间数的合理性
     */
    public function testContractAmountAndRoomsValidity(): void
    {
        $kernel = self::bootKernel();
        $entityManager = $kernel->getContainer()->get('doctrine')->getManager();

        $loader = new Loader();
        $loader->addFixture(new HotelFixtures());
        $loader->addFixture(new HotelContractFixtures());

        $purger = new ORMPurger($entityManager);
        $executor = new ORMExecutor($entityManager, $purger);
        $executor->execute($loader->getFixtures());

        $contractRepository = $entityManager->getRepository(HotelContract::class);
        $contracts = $contractRepository->findAll();

        foreach ($contracts as $contract) {
            $this->assertGreaterThan(0, $contract->getTotalRooms(), '总房间数应该大于0');
            $this->assertGreaterThan(0, $contract->getTotalDays(), '总天数应该大于0');
            $this->assertGreaterThan(0, (float)$contract->getTotalAmount(), '总金额应该大于0');

            // 验证日期逻辑
            $this->assertLessThan($contract->getEndDate(), $contract->getStartDate(), '开始日期应该早于结束日期');
        }
    }

    /**
     * 测试引用常量的使用
     */
    public function testFixtureReferences(): void
    {
        $kernel = self::bootKernel();
        $entityManager = $kernel->getContainer()->get('doctrine')->getManager();

        $loader = new Loader();
        $hotelFixture = new HotelFixtures();
        $contractFixture = new HotelContractFixtures();

        $loader->addFixture($hotelFixture);
        $loader->addFixture($contractFixture);

        $purger = new ORMPurger($entityManager);
        $executor = new ORMExecutor($entityManager, $purger);
        $executor->execute($loader->getFixtures());

        // 验证引用常量是否正确定义
        $this->assertStringStartsWith('contract-', HotelContractFixtures::CONTRACT_REFERENCE_PREFIX);

        // 验证依赖关系是否正确配置
        $dependencies = $contractFixture->getDependencies();
        $this->assertContains(HotelFixtures::class, $dependencies, 'HotelContractFixtures应该依赖HotelFixtures');
    }
}
