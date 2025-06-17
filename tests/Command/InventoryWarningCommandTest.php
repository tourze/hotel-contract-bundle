<?php

namespace Tourze\HotelContractBundle\Tests\Command;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application as ConsoleApplication;
use Symfony\Component\Console\Tester\CommandTester;
use Tourze\HotelContractBundle\Command\InventoryWarningCommand;
use Tourze\HotelContractBundle\Service\InventorySummaryService;
use Tourze\HotelContractBundle\Service\InventoryWarningService;

class InventoryWarningCommandTest extends TestCase
{
    private InventorySummaryService&MockObject $summaryService;
    private InventoryWarningService&MockObject $warningService;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->summaryService = $this->createMock(InventorySummaryService::class);
        $this->warningService = $this->createMock(InventoryWarningService::class);

        $application = new ConsoleApplication();

        $command = new InventoryWarningCommand(
            $this->summaryService,
            $this->warningService
        );

        $application->add($command);
        $this->commandTester = new CommandTester($command);
    }

    public function test_execute_basicWarningCheckSuccessWithSentNotifications(): void
    {
        // Mock服务返回成功并发送了通知
        $this->warningService->expects($this->once())
            ->method('checkAndSendWarnings')
            ->with(null)
            ->willReturn([
                'success' => true,
                'sent_count' => 3,
                'message' => '已发送3条库存预警通知'
            ]);

        $this->commandTester->execute([]);

        $this->assertEquals(0, $this->commandTester->getStatusCode());
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('库存预警检查', $output);
        $this->assertStringContainsString('已发送3条库存预警通知', $output);
    }

    public function test_execute_basicWarningCheckSuccessNoNotifications(): void
    {
        // Mock服务返回成功但没有发送通知
        $this->warningService->expects($this->once())
            ->method('checkAndSendWarnings')
            ->with(null)
            ->willReturn([
                'success' => true,
                'sent_count' => 0,
                'message' => '未发现需要预警的库存'
            ]);

        $this->commandTester->execute([]);

        $this->assertEquals(0, $this->commandTester->getStatusCode());
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('未发现需要预警的库存', $output);
    }

    public function test_execute_warningCheckFailure(): void
    {
        // Mock服务返回失败
        $this->warningService->expects($this->once())
            ->method('checkAndSendWarnings')
            ->with(null)
            ->willReturn([
                'success' => false,
                'message' => '库存预警检查失败: 数据库连接错误'
            ]);

        $this->commandTester->execute([]);

        $this->assertEquals(1, $this->commandTester->getStatusCode());
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('库存预警检查失败: 数据库连接错误', $output);
    }

    public function test_execute_withSyncOption(): void
    {
        // Mock同步服务成功
        $this->summaryService->expects($this->once())
            ->method('syncInventorySummary')
            ->with(null)
            ->willReturn([
                'success' => true,
                'message' => '库存统计数据同步完成'
            ]);

        // Mock预警服务成功
        $this->warningService->expects($this->once())
            ->method('checkAndSendWarnings')
            ->with(null)
            ->willReturn([
                'success' => true,
                'sent_count' => 1,
                'message' => '已发送1条库存预警通知'
            ]);

        $this->commandTester->execute(['--sync' => true]);

        $this->assertEquals(0, $this->commandTester->getStatusCode());
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('同步库存统计数据', $output);
        $this->assertStringContainsString('库存统计数据同步完成', $output);
    }

    public function test_execute_syncFailure(): void
    {
        // Mock同步服务失败
        $this->summaryService->expects($this->once())
            ->method('syncInventorySummary')
            ->with(null)
            ->willReturn([
                'success' => false,
                'message' => '库存统计数据同步失败'
            ]);

        // 同步失败后不应该调用预警服务
        $this->warningService->expects($this->never())
            ->method('checkAndSendWarnings');

        $this->commandTester->execute(['--sync' => true]);

        $this->assertEquals(1, $this->commandTester->getStatusCode());
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('库存统计数据同步失败', $output);
    }

    public function test_execute_withSpecificDate(): void
    {
        $specificDate = new \DateTimeImmutable('2024-12-01');

        // Mock预警服务，验证传入了特定日期
        $this->warningService->expects($this->once())
            ->method('checkAndSendWarnings')
            ->with($this->callback(function ($date) use ($specificDate) {
                return $date instanceof \DateTimeImmutable &&
                    $date->format('Y-m-d') === $specificDate->format('Y-m-d');
            }))
            ->willReturn([
                'success' => true,
                'sent_count' => 0,
                'message' => '未发现需要预警的库存'
            ]);

        $this->commandTester->execute(['--date' => '2024-12-01']);

        $this->assertEquals(0, $this->commandTester->getStatusCode());
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('检查特定日期: 2024-12-01', $output);
    }

    public function test_execute_withSyncAndSpecificDate(): void
    {
        $specificDate = new \DateTimeImmutable('2024-12-01');

        // Mock同步服务，验证传入了特定日期
        $this->summaryService->expects($this->once())
            ->method('syncInventorySummary')
            ->with($this->callback(function ($date) use ($specificDate) {
                return $date instanceof \DateTimeImmutable &&
                    $date->format('Y-m-d') === $specificDate->format('Y-m-d');
            }))
            ->willReturn([
                'success' => true,
                'message' => '库存统计数据同步完成'
            ]);

        // Mock预警服务，验证传入了特定日期
        $this->warningService->expects($this->once())
            ->method('checkAndSendWarnings')
            ->with($this->callback(function ($date) use ($specificDate) {
                return $date instanceof \DateTimeImmutable &&
                    $date->format('Y-m-d') === $specificDate->format('Y-m-d');
            }))
            ->willReturn([
                'success' => true,
                'sent_count' => 2,
                'message' => '已发送2条库存预警通知'
            ]);

        $this->commandTester->execute([
            '--sync' => true,
            '--date' => '2024-12-01'
        ]);

        $this->assertEquals(0, $this->commandTester->getStatusCode());
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('检查特定日期: 2024-12-01', $output);
        $this->assertStringContainsString('同步库存统计数据', $output);
    }

    public function test_execute_invalidDateFormat(): void
    {
        // 不应该调用任何服务
        $this->summaryService->expects($this->never())
            ->method('syncInventorySummary');

        $this->warningService->expects($this->never())
            ->method('checkAndSendWarnings');

        $this->commandTester->execute(['--date' => 'invalid-date']);

        $this->assertEquals(1, $this->commandTester->getStatusCode());
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('日期格式错误: invalid-date', $output);
    }

    public function test_execute_emptyDateOption(): void
    {
        // 空字符串的日期选项会被忽略，正常执行预警检查
        $this->warningService->expects($this->once())
            ->method('checkAndSendWarnings')
            ->with(null)
            ->willReturn([
                'success' => true,
                'sent_count' => 0,
                'message' => '未发现需要预警的库存'
            ]);

        $this->commandTester->execute(['--date' => '']);

        $this->assertEquals(0, $this->commandTester->getStatusCode());
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('未发现需要预警的库存', $output);
        $this->assertStringNotContainsString('检查特定日期:', $output);
    }

    public function test_commandConfiguration(): void
    {
        $application = new ConsoleApplication();

        $command = new InventoryWarningCommand(
            $this->summaryService,
            $this->warningService
        );

        $application->add($command);

        $this->assertEquals('app:inventory:check-warnings', $command->getName());
        $this->assertEquals('检查库存预警并发送通知邮件', $command->getDescription());
        $this->assertTrue($command->getDefinition()->hasOption('sync'));
        $this->assertTrue($command->getDefinition()->hasOption('date'));
    }
}
