<?php

namespace Tourze\HotelContractBundle\Tests\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Tourze\HotelContractBundle\Entity\HotelContract;
use Tourze\HotelContractBundle\Enum\ContractStatusEnum;
use Tourze\HotelContractBundle\Enum\ContractTypeEnum;
use Tourze\HotelContractBundle\Repository\HotelContractRepository;
use Tourze\HotelContractBundle\Service\ContractService;
use Tourze\HotelProfileBundle\Entity\Hotel;

class ContractServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private MailerInterface&MockObject $mailer;
    private LoggerInterface&MockObject $logger;
    private HotelContractRepository&MockObject $repository;
    private ContractService $contractService;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->repository = $this->createMock(HotelContractRepository::class);

        $this->contractService = new ContractService(
            $this->entityManager,
            $this->mailer,
            $this->logger,
            $this->repository,
            'test@example.com'
        );
    }

    public function test_generateContractNumber_returnsValidFormat(): void
    {
        // 准备mock
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $query = $this->createMock(Query::class);

        $this->repository->expects($this->once())
            ->method('createQueryBuilder')
            ->with('c')
            ->willReturn($queryBuilder);

        $queryBuilder->expects($this->once())
            ->method('select')
            ->with('COUNT(c.id)')
            ->willReturnSelf();

        $queryBuilder->expects($this->once())
            ->method('where')
            ->with('c.contractNo LIKE :prefix')
            ->willReturnSelf();

        $queryBuilder->expects($this->once())
            ->method('andWhere')
            ->with('DATE(c.createTime) = :today')
            ->willReturnSelf();

        $queryBuilder->expects($this->exactly(2))
            ->method('setParameter')
            ->willReturnSelf();

        $queryBuilder->expects($this->once())
            ->method('getQuery')
            ->willReturn($query);

        $query->expects($this->once())
            ->method('getSingleScalarResult')
            ->willReturn(5); // 假设今天已有5个合同

        // 执行测试
        $contractNumber = $this->contractService->generateContractNumber();

        // 验证结果
        $expectedPrefix = 'HT' . date('ymd');
        $this->assertStringStartsWith($expectedPrefix, $contractNumber);
        $this->assertEquals($expectedPrefix . '006', $contractNumber);
        $this->assertEquals(11, strlen($contractNumber)); // HT + 6位日期 + 3位序号
    }

    public function test_generateContractNumber_firstContractOfDay(): void
    {
        // 准备mock - 当天第一个合同
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $query = $this->createMock(Query::class);

        $this->repository->expects($this->once())
            ->method('createQueryBuilder')
            ->with('c')
            ->willReturn($queryBuilder);

        $queryBuilder->expects($this->once())
            ->method('select')
            ->with('COUNT(c.id)')
            ->willReturnSelf();

        $queryBuilder->expects($this->once())
            ->method('where')
            ->with('c.contractNo LIKE :prefix')
            ->willReturnSelf();

        $queryBuilder->expects($this->once())
            ->method('andWhere')
            ->with('DATE(c.createTime) = :today')
            ->willReturnSelf();

        $queryBuilder->expects($this->exactly(2))
            ->method('setParameter')
            ->willReturnSelf();

        $queryBuilder->expects($this->once())
            ->method('getQuery')
            ->willReturn($query);

        $query->expects($this->once())
            ->method('getSingleScalarResult')
            ->willReturn(0); // 当天第一个合同

        // 执行测试
        $contractNumber = $this->contractService->generateContractNumber();

        // 验证结果
        $expectedPrefix = 'HT' . date('ymd');
        $this->assertEquals($expectedPrefix . '001', $contractNumber);
    }

    public function test_approveContract_changesStatusAndNotifies(): void
    {
        // 准备测试数据
        $hotel = new Hotel();
        $hotel->setName('测试酒店');

        $contract = new HotelContract();
        $contract->setContractNo('HT20241201001');
        $contract->setHotel($hotel);
        $contract->setStatus(ContractStatusEnum::PENDING);

        // Mock期望
        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Email $email): bool {
                $subject = $email->getSubject();
                $htmlContent = $email->getHtmlBody();
                return $subject === '合同审批生效通知' &&
                    str_contains($htmlContent, 'HT20241201001') &&
                    str_contains($htmlContent, '测试酒店');
            }));

        $this->logger->expects($this->once())
            ->method('info');

        // 执行测试
        $this->contractService->approveContract($contract);

        // 验证结果
        $this->assertEquals(ContractStatusEnum::ACTIVE, $contract->getStatus());
    }

    public function test_terminateContract_changesStatusAndSetsReason(): void
    {
        // 准备测试数据
        $hotel = new Hotel();
        $hotel->setName('测试酒店');

        $contract = new HotelContract();
        $contract->setContractNo('HT20241201001');
        $contract->setHotel($hotel);
        $contract->setStatus(ContractStatusEnum::ACTIVE);

        $terminationReason = '酒店违约';

        // Mock期望
        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Email $email) use ($terminationReason): bool {
                $body = $email->getHtmlBody();
                return str_contains($body, $terminationReason);
            }));

        $this->logger->expects($this->once())
            ->method('info');

        // 执行测试
        $this->contractService->terminateContract($contract, $terminationReason);

        // 验证结果
        $this->assertEquals(ContractStatusEnum::TERMINATED, $contract->getStatus());
        $this->assertEquals($terminationReason, $contract->getTerminationReason());
    }

    public function test_adjustPriority_updatesPriorityAndLogs(): void
    {
        // 准备测试数据
        $hotel = new Hotel();
        $hotel->setName('测试酒店');

        $contract = new HotelContract();
        $contract->setContractNo('HT20241201001');
        $contract->setHotel($hotel);
        $contract->setPriority(5);

        $newPriority = 10;

        // Mock期望
        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->logger->expects($this->once())
            ->method('info')
            ->with('调整合同优先级', $this->callback(function (array $context) use ($newPriority): bool {
                return $context['old_priority'] === 5 &&
                    $context['new_priority'] === $newPriority;
            }));

        // 执行测试
        $this->contractService->adjustPriority($contract, $newPriority);

        // 验证结果
        $this->assertEquals($newPriority, $contract->getPriority());
    }

    public function test_sendContractCreatedNotification_sendsEmailSuccessfully(): void
    {
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

        // Mock期望
        $this->mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Email $email): bool {
                $subject = $email->getSubject();
                $body = $email->getHtmlBody();

                return $subject === '新合同创建通知' &&
                    str_contains($body, 'HT20241201001') &&
                    str_contains($body, '测试酒店') &&
                    str_contains($body, '100000.00');
            }));

        $this->logger->expects($this->once())
            ->method('info')
            ->with('合同创建通知已发送', $this->callback(function (array $context): bool {
                return isset($context['contract_no']) && $context['contract_no'] === 'HT20241201001';
            }));

        // 执行测试
        $this->contractService->sendContractCreatedNotification($contract);
    }

    public function test_sendContractCreatedNotification_handlesMailerException(): void
    {
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

        // Mock期望 - 邮件发送失败
        $this->mailer->expects($this->once())
            ->method('send')
            ->willThrowException(new \Exception('邮件发送失败'));

        $this->logger->expects($this->once())
            ->method('error')
            ->with('发送合同创建通知失败', $this->callback(function (array $context): bool {
                return $context['contract_no'] === 'HT20241201001' &&
                    $context['error'] === '邮件发送失败';
            }));

        // 执行测试 - 不应抛出异常
        $this->contractService->sendContractCreatedNotification($contract);
        
        // 验证方法执行成功（通过 expects 验证）
        $this->assertTrue(true);
    }

    public function test_sendContractUpdatedNotification_sendsEmailSuccessfully(): void
    {
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

        // Mock期望
        $this->mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Email $email): bool {
                $subject = $email->getSubject();
                $body = $email->getHtmlBody();

                return $subject === '合同更新通知' &&
                    str_contains($body, 'HT20241201001') &&
                    str_contains($body, '测试酒店') &&
                    str_contains($body, '生效');
            }));

        $this->logger->expects($this->once())
            ->method('info')
            ->with('合同更新通知已发送', $this->callback(function (array $context): bool {
                return isset($context['contract_no']) && $context['contract_no'] === 'HT20241201001';
            }));

        // 执行测试
        $this->contractService->sendContractUpdatedNotification($contract);
    }

    public function test_sendContractUpdatedNotification_handlesMailerException(): void
    {
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

        // Mock期望 - 邮件发送失败
        $this->mailer->expects($this->once())
            ->method('send')
            ->willThrowException(new \Exception('SMTP服务器连接失败'));

        $this->logger->expects($this->once())
            ->method('error')
            ->with('发送合同更新通知失败', $this->callback(function (array $context): bool {
                return $context['contract_no'] === 'HT20241201001' &&
                    $context['error'] === 'SMTP服务器连接失败';
            }));

        // 执行测试 - 不应抛出异常
        $this->contractService->sendContractUpdatedNotification($contract);
    }
}
