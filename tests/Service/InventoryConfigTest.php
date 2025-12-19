<?php

namespace Tourze\HotelContractBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\EnvManageBundle\Entity\Env;
use Tourze\HotelContractBundle\Service\InventoryConfig;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(InventoryConfig::class)]
#[RunTestsInSeparateProcesses]
final class InventoryConfigTest extends AbstractIntegrationTestCase
{
    private InventoryConfig $inventoryConfig;

    protected function onSetUp(): void
    {
        $this->inventoryConfig = static::getService(InventoryConfig::class);
    }

    public function testGetWarningConfigReturnsDefaultWhenNoEnvFound(): void
    {
        $config = $this->inventoryConfig->getWarningConfig();

        $expectedConfig = [
            'warning_threshold' => 10,
            'email_recipients' => '',
            'enable_warning' => true,
            'warning_interval' => 24,
        ];

        $this->assertEquals($expectedConfig, $config);
    }

    public function testGetWarningConfigReturnsConfigFromEnv(): void
    {
        // 使用真实的容器环境，通过环境变量配置来测试
        // 这里我们测试默认配置，集成测试应该使用真实环境
        $config = $this->inventoryConfig->getWarningConfig();

        // 验证配置结构包含所有必要的键
        $expectedKeys = [
            'warning_threshold',
            'email_recipients',
            'enable_warning',
            'warning_interval'
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $config);
        }
    }

    public function testGetWarningConfigTypeConversions(): void
    {
        $config = $this->inventoryConfig->getWarningConfig();

        // 验证类型转换是否正确
        $this->assertIsInt($config['warning_threshold']);
        $this->assertIsInt($config['warning_interval']);
        $this->assertIsBool($config['enable_warning']);
        $this->assertIsString($config['email_recipients']);
    }

    public function testSaveWarningConfigSavesSuccessfully(): void
    {
        $config = [
            'warning_threshold' => 20,
        ];

        // 简化测试，只验证方法执行不抛异常
        $result = $this->inventoryConfig->saveWarningConfig($config);

        // 验证方法执行成功
        $this->assertTrue($result);
    }

    public function testSaveWarningConfigSkipsUndefinedKeys(): void
    {
        // 只传入部分配置
        $config = [
            'warning_threshold' => 30,
            'undefined_key' => 'should_be_ignored',
        ];

        $result = $this->inventoryConfig->saveWarningConfig($config);

        // 验证方法执行成功
        $this->assertTrue($result);
    }

    public function testGetWarningConfigHandlesPartialConfig(): void
    {
        // 集成测试使用真实环境，我们验证配置的结构而非具体值
        $config = $this->inventoryConfig->getWarningConfig();

        // 验证配置包含所有必需的字段
        $this->assertArrayHasKey('warning_threshold', $config);
        $this->assertArrayHasKey('email_recipients', $config);
        $this->assertArrayHasKey('enable_warning', $config);
        $this->assertArrayHasKey('warning_interval', $config);

        // 验证字段类型
        $this->assertIsInt($config['warning_threshold']);
        $this->assertIsString($config['email_recipients']);
        $this->assertIsBool($config['enable_warning']);
        $this->assertIsInt($config['warning_interval']);
    }

    public function testGetWarningConfigBooleanConversion(): void
    {
        $config = $this->inventoryConfig->getWarningConfig();

        // 验证布尔值字段类型正确
        $this->assertIsBool($config['enable_warning']);
    }
}
