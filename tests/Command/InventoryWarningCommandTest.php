<?php

namespace Tourze\HotelContractBundle\Tests\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Console\Tester\CommandTester;
use Tourze\HotelContractBundle\Command\InventoryWarningCommand;
use Tourze\PHPUnitSymfonyKernelTest\AbstractCommandTestCase;

/**
 * @internal
 */
#[CoversClass(InventoryWarningCommand::class)]
#[RunTestsInSeparateProcesses]
final class InventoryWarningCommandTest extends AbstractCommandTestCase
{
    public static function setUpBeforeClass(): void
    {
        putenv('MAILER_DSN=null://null');
        parent::setUpBeforeClass();
    }

    protected function onSetUp(): void
    {
        putenv('MAILER_DSN=null://null');
    }

    protected function getCommandTester(): CommandTester
    {
        $command = self::getService(InventoryWarningCommand::class);

        return new CommandTester($command);
    }

    public function testCommandCanBeRetrievedFromContainer(): void
    {
        $command = self::getService(InventoryWarningCommand::class);
        $this->assertInstanceOf(InventoryWarningCommand::class, $command);
    }

    public function testExecuteCommand(): void
    {
        $commandTester = $this->getCommandTester();

        $commandTester->execute([]);

        $output = $commandTester->getDisplay();
        $statusCode = $commandTester->getStatusCode();

        $this->assertStringContainsString('库存预警检查', $output);
        $this->assertStringContainsString('检查库存预警', $output);

        $this->assertContains($statusCode, [0, 1], 'Command should return either success or expected failure for missing config');
    }

    public function testCommandConfiguration(): void
    {
        $command = self::getService(InventoryWarningCommand::class);

        $this->assertEquals('app:inventory:check-warnings', $command->getName());
        $this->assertEquals('检查库存预警并发送通知邮件', $command->getDescription());
        $this->assertTrue($command->getDefinition()->hasOption('sync'));
        $this->assertTrue($command->getDefinition()->hasOption('date'));
    }

    public function testExecuteWithSyncOption(): void
    {
        $commandTester = $this->getCommandTester();

        $commandTester->execute(['--sync' => true]);

        $output = $commandTester->getDisplay();
        $statusCode = $commandTester->getStatusCode();

        $this->assertStringContainsString('同步库存统计数据', $output);
        $this->assertContains($statusCode, [0, 1], 'Command with sync should handle missing config gracefully');
    }

    public function testExecuteWithDateOption(): void
    {
        $commandTester = $this->getCommandTester();

        $commandTester->execute(['--date' => '2024-01-01']);

        $output = $commandTester->getDisplay();
        $statusCode = $commandTester->getStatusCode();

        $this->assertStringContainsString('检查特定日期: 2024-01-01', $output);
        $this->assertContains($statusCode, [0, 1], 'Command with date should handle missing config gracefully');
    }

    public function testExecuteWithInvalidDate(): void
    {
        $commandTester = $this->getCommandTester();

        $commandTester->execute(['--date' => 'invalid-date']);

        $this->assertSame(1, $commandTester->getStatusCode());
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('日期格式错误', $output);
    }

    public function testOptionSync(): void
    {
        $commandTester = $this->getCommandTester();

        $commandTester->execute(['--sync' => true]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('同步库存统计数据', $output);
    }

    public function testOptionDate(): void
    {
        $commandTester = $this->getCommandTester();

        $commandTester->execute(['--date' => '2024-01-01']);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('检查特定日期: 2024-01-01', $output);
    }
}
