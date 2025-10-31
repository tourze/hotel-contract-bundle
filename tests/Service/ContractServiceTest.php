<?php

namespace Tourze\HotelContractBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\HotelContractBundle\Entity\HotelContract;
use Tourze\HotelContractBundle\Enum\ContractStatusEnum;
use Tourze\HotelContractBundle\Enum\ContractTypeEnum;
use Tourze\HotelContractBundle\Service\ContractService;
use Tourze\HotelProfileBundle\Entity\Hotel;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(ContractService::class)]
#[RunTestsInSeparateProcesses]
final class ContractServiceTest extends AbstractIntegrationTestCase
{
    protected function onSetUp(): void
    {
        // 设置邮件环境变量
        putenv('MAILER_DSN=smtp://localhost:1025');
    }

    public function testGenerateContractNumberReturnsValidFormat(): void
    {
        $contractService = self::getService(ContractService::class);

        // 获取当前日期前缀
        $today = new \DateTimeImmutable();
        $prefix = 'HT' . $today->format('ymd');

        // 执行测试
        $contractNumber = $contractService->generateContractNumber();

        // 验证结果格式
        $this->assertStringStartsWith($prefix, $contractNumber);
        $this->assertEquals(11, strlen($contractNumber)); // HT + 6位日期 + 3位序号
        $this->assertMatchesRegularExpression('/^HT\d{6}\d{3}$/', $contractNumber);
    }

    public function testApproveContractChangesStatus(): void
    {
        $contractService = self::getService(ContractService::class);

        // 准备测试数据
        $hotel = new Hotel();
        $hotel->setName('测试酒店');

        $contract = new HotelContract();
        $contract->setContractNo('HT20241201001');
        $contract->setHotel($hotel);
        $contract->setStatus(ContractStatusEnum::PENDING);
        $contract->setContractType(ContractTypeEnum::FIXED_PRICE);
        $contract->setStartDate(new \DateTimeImmutable('2024-01-01'));
        $contract->setEndDate(new \DateTimeImmutable('2024-12-31'));
        $contract->setTotalAmount('100000.00');

        // 执行测试
        $contractService->approveContract($contract);

        // 验证结果
        $this->assertEquals(ContractStatusEnum::ACTIVE, $contract->getStatus());
    }

    public function testTerminateContractChangesStatusAndSetsReason(): void
    {
        $contractService = self::getService(ContractService::class);

        // 准备测试数据
        $hotel = new Hotel();
        $hotel->setName('测试酒店');

        $contract = new HotelContract();
        $contract->setContractNo('HT20241201001');
        $contract->setHotel($hotel);
        $contract->setStatus(ContractStatusEnum::ACTIVE);
        $contract->setContractType(ContractTypeEnum::FIXED_PRICE);
        $contract->setStartDate(new \DateTimeImmutable('2024-01-01'));
        $contract->setEndDate(new \DateTimeImmutable('2024-12-31'));
        $contract->setTotalAmount('100000.00');

        $terminationReason = '酒店违约';

        // 执行测试
        $contractService->terminateContract($contract, $terminationReason);

        // 验证结果
        $this->assertEquals(ContractStatusEnum::TERMINATED, $contract->getStatus());
        $this->assertEquals($terminationReason, $contract->getTerminationReason());
    }

    public function testAdjustPriorityUpdatesPriority(): void
    {
        $contractService = self::getService(ContractService::class);

        // 准备测试数据
        $hotel = new Hotel();
        $hotel->setName('测试酒店');

        $contract = new HotelContract();
        $contract->setContractNo('HT20241201001');
        $contract->setHotel($hotel);
        $contract->setPriority(5);
        $contract->setStatus(ContractStatusEnum::ACTIVE);
        $contract->setContractType(ContractTypeEnum::FIXED_PRICE);
        $contract->setStartDate(new \DateTimeImmutable('2024-01-01'));
        $contract->setEndDate(new \DateTimeImmutable('2024-12-31'));
        $contract->setTotalAmount('100000.00');

        $newPriority = 10;

        // 执行测试
        $contractService->adjustPriority($contract, $newPriority);

        // 验证结果
        $this->assertEquals($newPriority, $contract->getPriority());
    }

    public function testSendContractCreatedNotificationExecutesWithoutError(): void
    {
        $contractService = self::getService(ContractService::class);

        // 准备测试数据
        $hotel = new Hotel();
        $hotel->setName('测试酒店');

        $contract = new HotelContract();
        $contract->setContractNo('HT20241201001');
        $contract->setHotel($hotel);
        $contract->setContractType(ContractTypeEnum::FIXED_PRICE);
        $contract->setStartDate(new \DateTimeImmutable('2024-01-01'));
        $contract->setEndDate(new \DateTimeImmutable('2024-12-31'));
        $contract->setTotalAmount('100000.00');
        $contract->setTotalRooms(100);
        $contract->setCreateTime(new \DateTimeImmutable('2024-01-01 10:00:00'));

        // 执行测试 - 验证方法不抛出异常
        $this->expectNotToPerformAssertions();
        $contractService->sendContractCreatedNotification($contract);
    }

    public function testSendContractUpdatedNotificationExecutesWithoutError(): void
    {
        $contractService = self::getService(ContractService::class);

        // 准备测试数据
        $hotel = new Hotel();
        $hotel->setName('测试酒店');

        $contract = new HotelContract();
        $contract->setContractNo('HT20241201001');
        $contract->setHotel($hotel);
        $contract->setContractType(ContractTypeEnum::FIXED_PRICE);
        $contract->setStatus(ContractStatusEnum::ACTIVE);
        $contract->setStartDate(new \DateTimeImmutable('2024-01-01'));
        $contract->setEndDate(new \DateTimeImmutable('2024-12-31'));
        $contract->setTotalAmount('100000.00');

        // 执行测试 - 验证方法不抛出异常
        $this->expectNotToPerformAssertions();
        $contractService->sendContractUpdatedNotification($contract);
    }
}
