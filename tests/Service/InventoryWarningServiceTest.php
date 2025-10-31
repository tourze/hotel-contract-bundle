<?php

declare(strict_types=1);

namespace Tourze\HotelContractBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\HotelContractBundle\Service\InventoryWarningService;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(InventoryWarningService::class)]
#[RunTestsInSeparateProcesses]
final class InventoryWarningServiceTest extends AbstractIntegrationTestCase
{
    protected function onSetUp(): void
    {
        // 设置邮件环境变量
        putenv('MAILER_DSN=smtp://localhost:1025');
    }

    private function getInventoryWarningService(): InventoryWarningService
    {
        return self::getService(InventoryWarningService::class);
    }

    public function testCheckAndSendWarningsProcessesData(): void
    {
        // 执行测试 - 主要验证方法不抛出异常
        $result = $this->getInventoryWarningService()->checkAndSendWarnings();

        // 验证返回结果包含预期的键
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('sent_count', $result);
        $this->assertArrayHasKey('message', $result);

        // 验证结果值的类型
        $this->assertIsBool($result['success']);
        $this->assertIsInt($result['sent_count']);
        // message字段已确定为字符串类型，无需重复检查
    }
}
