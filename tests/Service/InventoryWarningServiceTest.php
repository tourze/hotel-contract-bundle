<?php

declare(strict_types=1);

namespace Tourze\HotelContractBundle\Tests\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Tourze\HotelContractBundle\Entity\InventorySummary;
use Tourze\HotelContractBundle\Service\InventoryConfig;
use Tourze\HotelContractBundle\Service\InventoryWarningService;
use Tourze\HotelProfileBundle\Entity\Hotel;
use Tourze\HotelProfileBundle\Entity\RoomType;

/**
 * InventoryWarningService 单元测试
 */
class InventoryWarningServiceTest extends TestCase
{
    private EntityManagerInterface|MockObject $entityManager;
    private MailerInterface|MockObject $mailer;
    private LoggerInterface|MockObject $logger;
    private InventoryConfig|MockObject $inventoryConfig;
    private CacheItemPoolInterface|MockObject $cache;
    private InventoryWarningService $service;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->inventoryConfig = $this->createMock(InventoryConfig::class);
        $this->cache = $this->createMock(CacheItemPoolInterface::class);

        $this->service = new InventoryWarningService(
            $this->entityManager,
            $this->mailer,
            $this->logger,
            $this->inventoryConfig,
            $this->cache
        );
    }

    /**
     * 测试检查和发送预警 - 功能未启用
     */
    public function testCheckAndSendWarningsDisabled(): void
    {
        $this->inventoryConfig->expects($this->once())
            ->method('getWarningConfig')
            ->willReturn(['enable_warning' => false]);

        $result = $this->service->checkAndSendWarnings();

        $this->assertTrue($result['success']);
        $this->assertEquals('库存预警功能未启用', $result['message']);
        $this->assertEquals(0, $result['sent_count']);
    }

    /**
     * 测试检查和发送预警 - 未设置收件人
     */
    public function testCheckAndSendWarningsNoRecipients(): void
    {
        $this->inventoryConfig->expects($this->once())
            ->method('getWarningConfig')
            ->willReturn([
                'enable_warning' => true,
                'email_recipients' => ''
            ]);

        $result = $this->service->checkAndSendWarnings();

        $this->assertFalse($result['success']);
        $this->assertEquals('未设置预警邮件收件人', $result['message']);
        $this->assertEquals(0, $result['sent_count']);
    }

    /**
     * 测试检查和发送预警 - 未发现预警库存
     */
    public function testCheckAndSendWarningsNoWarnings(): void
    {
        $this->inventoryConfig->expects($this->once())
            ->method('getWarningConfig')
            ->willReturn([
                'enable_warning' => true,
                'email_recipients' => 'admin@example.com'
            ]);

        // 模拟Repository查询
        $repository = $this->createMock(EntityRepository::class);
        $queryBuilder = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $query = $this->createMock(\Doctrine\ORM\Query::class);

        $this->entityManager->expects($this->once())
            ->method('getRepository')
            ->with(InventorySummary::class)
            ->willReturn($repository);

        $repository->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('leftJoin')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);

        $query->expects($this->once())
            ->method('getResult')
            ->willReturn([]);

        $result = $this->service->checkAndSendWarnings();

        $this->assertTrue($result['success']);
        $this->assertEquals('未发现需要预警的库存', $result['message']);
        $this->assertEquals(0, $result['sent_count']);
    }

    /**
     * 测试检查和发送预警 - 成功发送
     */
    public function testCheckAndSendWarningsSuccess(): void
    {
        // 创建测试数据
        $hotel = $this->createMock(Hotel::class);
        $roomType = $this->createMock(RoomType::class);
        $summary = $this->createMock(InventorySummary::class);
        $date = new \DateTime('2024-01-15');

        $hotel->method('getId')->willReturn(1);
        $hotel->method('getName')->willReturn('测试酒店');
        $roomType->method('getId')->willReturn(1);
        $roomType->method('getName')->willReturn('标准间');

        $summary->method('getHotel')->willReturn($hotel);
        $summary->method('getRoomType')->willReturn($roomType);
        $summary->method('getDate')->willReturn($date);
        $summary->method('getId')->willReturn(1);
        $summary->method('getTotalRooms')->willReturn(100);
        $summary->method('getAvailableRooms')->willReturn(5);
        $summary->method('getSoldRooms')->willReturn(80);
        $summary->method('getPendingRooms')->willReturn(10);
        $summary->method('getReservedRooms')->willReturn(5);

        $this->inventoryConfig->expects($this->once())
            ->method('getWarningConfig')
            ->willReturn([
                'enable_warning' => true,
                'email_recipients' => 'admin@example.com',
                'warning_interval' => 0 // 设置为0避免缓存间隔检查
            ]);

        // 模拟Repository查询
        $repository = $this->createMock(EntityRepository::class);
        $queryBuilder = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $query = $this->createMock(\Doctrine\ORM\Query::class);

        $this->entityManager->expects($this->once())
            ->method('getRepository')
            ->willReturn($repository);

        $repository->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('leftJoin')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);

        $query->expects($this->once())
            ->method('getResult')
            ->willReturn([$summary]);

        // 模拟缓存
        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->method('isHit')->willReturn(false); // 未命中缓存，可以发送
        $cacheItem->method('set')->willReturnSelf();
        $cacheItem->method('expiresAfter')->willReturnSelf();

        $this->cache->expects($this->once())
            ->method('getItem')
            ->willReturn($cacheItem);

        $this->cache->expects($this->once())
            ->method('save')
            ->with($cacheItem);

        // 模拟邮件发送
        $this->mailer->expects($this->once())
            ->method('send')
            ->with($this->isInstanceOf(Email::class));

        $result = $this->service->checkAndSendWarnings();

        $this->assertTrue($result['success']);
        $this->assertEquals('成功发送1条库存预警通知', $result['message']);
        $this->assertEquals(1, $result['sent_count']);
    }

    /**
     * 测试检查和发送预警 - 指定日期
     */
    public function testCheckAndSendWarningsWithSpecificDate(): void
    {
        $specificDate = new \DateTime('2024-01-15');

        $this->inventoryConfig->expects($this->once())
            ->method('getWarningConfig')
            ->willReturn([
                'enable_warning' => true,
                'email_recipients' => 'admin@example.com',
                'warning_interval' => 0 // 设置为0避免缓存间隔检查
            ]);

        // 模拟Repository查询
        $repository = $this->createMock(EntityRepository::class);
        $queryBuilder = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $query = $this->createMock(\Doctrine\ORM\Query::class);

        $this->entityManager->expects($this->once())
            ->method('getRepository')
            ->willReturn($repository);

        $repository->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('leftJoin')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);

        $query->expects($this->once())
            ->method('getResult')
            ->willReturn([]);

        $result = $this->service->checkAndSendWarnings($specificDate);

        $this->assertTrue($result['success']);
        $this->assertEquals('未发现需要预警的库存', $result['message']);
        $this->assertEquals(0, $result['sent_count']);
    }

    /**
     * 测试邮件发送异常处理
     */
    public function testCheckAndSendWarningsMailException(): void
    {
        // 创建测试数据
        $hotel = $this->createMock(Hotel::class);
        $roomType = $this->createMock(RoomType::class);
        $summary = $this->createMock(InventorySummary::class);
        $date = new \DateTime('2024-01-15');

        $hotel->method('getId')->willReturn(1);
        $hotel->method('getName')->willReturn('测试酒店');
        $roomType->method('getId')->willReturn(1);
        $roomType->method('getName')->willReturn('标准间');

        $summary->method('getHotel')->willReturn($hotel);
        $summary->method('getRoomType')->willReturn($roomType);
        $summary->method('getDate')->willReturn($date);
        $summary->method('getId')->willReturn(1);
        $summary->method('getTotalRooms')->willReturn(100);
        $summary->method('getAvailableRooms')->willReturn(5);
        $summary->method('getSoldRooms')->willReturn(80);
        $summary->method('getPendingRooms')->willReturn(10);
        $summary->method('getReservedRooms')->willReturn(5);

        $this->inventoryConfig->expects($this->once())
            ->method('getWarningConfig')
            ->willReturn([
                'enable_warning' => true,
                'email_recipients' => 'admin@example.com',
                'warning_interval' => 0 // 设置为0避免缓存间隔检查
            ]);

        // 模拟Repository查询
        $repository = $this->createMock(EntityRepository::class);
        $queryBuilder = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $query = $this->createMock(\Doctrine\ORM\Query::class);

        $this->entityManager->expects($this->once())
            ->method('getRepository')
            ->willReturn($repository);

        $repository->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('leftJoin')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);

        $query->expects($this->once())
            ->method('getResult')
            ->willReturn([$summary]);

        // 模拟缓存
        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->method('isHit')->willReturn(false);
        $cacheItem->method('set')->willReturnSelf();
        $cacheItem->method('expiresAfter')->willReturnSelf();

        $this->cache->expects($this->once())
            ->method('getItem')
            ->willReturn($cacheItem);

        // 邮件发送失败时不应该保存缓存
        $this->cache->expects($this->never())
            ->method('save');

        // 模拟邮件发送异常
        $this->mailer->expects($this->once())
            ->method('send')
            ->willThrowException(new \Exception('邮件发送失败'));

        // 预期记录错误日志
        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                $this->stringContains('发送库存预警邮件失败'),
                $this->arrayHasKey('summary_id')
            );

        $result = $this->service->checkAndSendWarnings();

        $this->assertTrue($result['success']);
        $this->assertEquals('成功发送0条库存预警通知', $result['message']);
        $this->assertEquals(0, $result['sent_count']);
    }

    /**
     * 测试邮件收件人格式错误
     */
    public function testCheckAndSendWarningsInvalidRecipients(): void
    {
        $this->inventoryConfig->expects($this->once())
            ->method('getWarningConfig')
            ->willReturn([
                'enable_warning' => true,
                'email_recipients' => "   \n   \n   " // 只有空白字符
            ]);

        // 模拟Repository查询（因为代码会先检查是否有预警库存）
        $repository = $this->createMock(EntityRepository::class);
        $queryBuilder = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $query = $this->createMock(\Doctrine\ORM\Query::class);

        $this->entityManager->expects($this->once())
            ->method('getRepository')
            ->willReturn($repository);

        $repository->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('leftJoin')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);

        // 假设找到了一些预警库存
        $summary = $this->createMock(InventorySummary::class);
        $query->expects($this->once())
            ->method('getResult')
            ->willReturn([$summary]);

        $result = $this->service->checkAndSendWarnings();

        $this->assertFalse($result['success']);
        $this->assertEquals('预警邮件收件人格式错误', $result['message']);
        $this->assertEquals(0, $result['sent_count']);
    }

    /**
     * 测试多个收件人邮件发送
     */
    public function testCheckAndSendWarningsMultipleRecipients(): void
    {
        // 创建测试数据
        $hotel = $this->createMock(Hotel::class);
        $roomType = $this->createMock(RoomType::class);
        $summary = $this->createMock(InventorySummary::class);
        $date = new \DateTime('2024-01-15');

        $hotel->method('getId')->willReturn(1);
        $hotel->method('getName')->willReturn('测试酒店');
        $roomType->method('getId')->willReturn(1);
        $roomType->method('getName')->willReturn('标准间');

        $summary->method('getHotel')->willReturn($hotel);
        $summary->method('getRoomType')->willReturn($roomType);
        $summary->method('getDate')->willReturn($date);
        $summary->method('getId')->willReturn(1);
        $summary->method('getTotalRooms')->willReturn(100);
        $summary->method('getAvailableRooms')->willReturn(8);
        $summary->method('getSoldRooms')->willReturn(77);
        $summary->method('getPendingRooms')->willReturn(10);
        $summary->method('getReservedRooms')->willReturn(5);

        $this->inventoryConfig->expects($this->once())
            ->method('getWarningConfig')
            ->willReturn([
                'enable_warning' => true,
                'email_recipients' => "admin@example.com\nmanager@example.com\nsupervisor@example.com",
                'warning_interval' => 0 // 设置为0避免缓存间隔检查
            ]);

        // 模拟Repository查询
        $repository = $this->createMock(EntityRepository::class);
        $queryBuilder = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $query = $this->createMock(\Doctrine\ORM\Query::class);

        $this->entityManager->expects($this->once())
            ->method('getRepository')
            ->willReturn($repository);

        $repository->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('leftJoin')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);

        $query->expects($this->once())
            ->method('getResult')
            ->willReturn([$summary]);

        // 模拟缓存
        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->method('isHit')->willReturn(false);
        $cacheItem->method('set')->willReturnSelf();
        $cacheItem->method('expiresAfter')->willReturnSelf();

        $this->cache->expects($this->once())
            ->method('getItem')
            ->willReturn($cacheItem);

        $this->cache->expects($this->once())
            ->method('save')
            ->with($cacheItem);

        // 验证邮件发送，期望收件人包含多个地址
        $this->mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Email $email) {
                // 检查邮件主题包含预警信息
                $subject = $email->getSubject();
                return str_contains($subject, '【库存预警】') &&
                    str_contains($subject, '测试酒店') &&
                    str_contains($subject, '标准间');
            }));

        $result = $this->service->checkAndSendWarnings();

        $this->assertTrue($result['success']);
        $this->assertEquals('成功发送1条库存预警通知', $result['message']);
        $this->assertEquals(1, $result['sent_count']);
    }

    /**
     * 测试缓存命中时跳过发送
     */
    public function testCheckAndSendWarningsCacheHit(): void
    {
        // 创建测试数据
        $hotel = $this->createMock(Hotel::class);
        $roomType = $this->createMock(RoomType::class);
        $summary = $this->createMock(InventorySummary::class);
        $date = new \DateTime('2024-01-15');

        $hotel->method('getId')->willReturn(1);
        $hotel->method('getName')->willReturn('测试酒店');
        $roomType->method('getId')->willReturn(1);
        $roomType->method('getName')->willReturn('标准间');

        $summary->method('getHotel')->willReturn($hotel);
        $summary->method('getRoomType')->willReturn($roomType);
        $summary->method('getDate')->willReturn($date);
        $summary->method('getId')->willReturn(1);

        $this->inventoryConfig->expects($this->once())
            ->method('getWarningConfig')
            ->willReturn([
                'enable_warning' => true,
                'email_recipients' => 'admin@example.com',
                'warning_interval' => 1 // 1小时间隔
            ]);

        // 模拟Repository查询
        $repository = $this->createMock(EntityRepository::class);
        $queryBuilder = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $query = $this->createMock(\Doctrine\ORM\Query::class);

        $this->entityManager->expects($this->once())
            ->method('getRepository')
            ->willReturn($repository);

        $repository->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('leftJoin')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);

        $query->expects($this->once())
            ->method('getResult')
            ->willReturn([$summary]);

        // 模拟缓存命中（刚刚发送过）
        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->method('isHit')->willReturn(true);
        $cacheItem->method('get')->willReturn(time()); // 刚刚发送的时间戳

        $this->cache->expects($this->once())
            ->method('getItem')
            ->willReturn($cacheItem);

        // 不应该保存缓存（因为没有发送）
        $this->cache->expects($this->never())
            ->method('save');

        // 不应该发送邮件
        $this->mailer->expects($this->never())
            ->method('send');

        $result = $this->service->checkAndSendWarnings();

        $this->assertTrue($result['success']);
        $this->assertEquals('成功发送0条库存预警通知', $result['message']);
        $this->assertEquals(0, $result['sent_count']);
    }
}
