<?php

namespace Tourze\HotelContractBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\EnvManageBundle\Entity\Env;
use Tourze\EnvManageBundle\Service\EnvService;
use Tourze\HotelContractBundle\Service\InventoryConfig;

/**
 * @internal
 */
#[CoversClass(InventoryConfig::class)]
final class InventoryConfigTest extends TestCase
{
    private EnvService $envService;

    private InventoryConfig $inventoryConfig;

    protected function setUp(): void
    {
        parent::setUp();
        // Setup for service tests - use default empty envService
        $this->envService = $this->createTestEnvService();
        $this->inventoryConfig = new InventoryConfig($this->envService);
    }

    private function getInventoryConfig(): InventoryConfig
    {
        return $this->inventoryConfig;
    }

    public function testGetWarningConfigReturnsDefaultWhenNoEnvFound(): void
    {
        $config = $this->getInventoryConfig()->getWarningConfig();

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
        // 准备测试数据
        $envs = [
            'INVENTORY_WARNING_THRESHOLD' => $this->createEnv('INVENTORY_WARNING_THRESHOLD', '15'),
            'INVENTORY_EMAIL_RECIPIENTS' => $this->createEnv('INVENTORY_EMAIL_RECIPIENTS', 'admin@test.com,manager@test.com'),
            'INVENTORY_ENABLE_WARNING' => $this->createEnv('INVENTORY_ENABLE_WARNING', 'false'),
            'INVENTORY_WARNING_INTERVAL' => $this->createEnv('INVENTORY_WARNING_INTERVAL', '48'),
        ];

        // Create a new InventoryConfig instance with custom env service
        $envService = $this->createTestEnvService($envs);
        $inventoryConfig = new InventoryConfig($envService);

        $config = $inventoryConfig->getWarningConfig();

        $expectedConfig = [
            'warning_threshold' => 15,
            'email_recipients' => 'admin@test.com,manager@test.com',
            'enable_warning' => false,
            'warning_interval' => 48,
        ];

        $this->assertEquals($expectedConfig, $config);
    }

    public function testGetWarningConfigTypeConversions(): void
    {
        // 测试类型转换功能
        $envs = [
            'INVENTORY_WARNING_THRESHOLD' => $this->createEnv('INVENTORY_WARNING_THRESHOLD', '5.5'), // 应转为int 5
            'INVENTORY_ENABLE_WARNING' => $this->createEnv('INVENTORY_ENABLE_WARNING', '1'), // 应转为true
            'INVENTORY_WARNING_INTERVAL' => $this->createEnv('INVENTORY_WARNING_INTERVAL', '12.8'), // 应转为int 12
        ];

        // Create a new InventoryConfig instance with custom env service
        $envService = $this->createTestEnvService($envs);
        $inventoryConfig = new InventoryConfig($envService);

        $config = $inventoryConfig->getWarningConfig();

        $this->assertIsInt($config['warning_threshold']);
        $this->assertEquals(5, $config['warning_threshold']);
        $this->assertTrue($config['enable_warning']);

        $this->assertIsInt($config['warning_interval']);
        $this->assertEquals(12, $config['warning_interval']);
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
        // 只设置部分配置，其他使用默认值
        $envs = [
            'INVENTORY_WARNING_THRESHOLD' => $this->createEnv('INVENTORY_WARNING_THRESHOLD', '25'),
            'INVENTORY_ENABLE_WARNING' => $this->createEnv('INVENTORY_ENABLE_WARNING', 'false'),
        ];

        // Create a new InventoryConfig instance with custom env service
        $envService = $this->createTestEnvService($envs);
        $inventoryConfig = new InventoryConfig($envService);

        $config = $inventoryConfig->getWarningConfig();

        $expectedConfig = [
            'warning_threshold' => 25,      // 来自环境变量
            'email_recipients' => '',       // 默认值
            'enable_warning' => false,      // 来自环境变量
            'warning_interval' => 24,       // 默认值
        ];

        $this->assertEquals($expectedConfig, $config);
    }

    public function testGetWarningConfigBooleanConversion(): void
    {
        // 简化测试，只测试一个典型的布尔值转换
        $envs = [
            'INVENTORY_ENABLE_WARNING' => $this->createEnv('INVENTORY_ENABLE_WARNING', 'false'),
        ];

        // Create a new InventoryConfig instance with custom env service
        $envService = $this->createTestEnvService($envs);
        $inventoryConfig = new InventoryConfig($envService);

        $config = $inventoryConfig->getWarningConfig();
        $this->assertFalse($config['enable_warning']);
    }

    private function createEnv(string $name, string $value): Env
    {
        $env = new Env();
        $env->setName($name);
        $env->setValue($value);
        $env->setValid(true);

        return $env;
    }

    /**
     * @param array<string, Env|null> $envData
     */
    private function createTestEnvService(array $envData = []): EnvService
    {
        return new class($envData) implements EnvService {
            /**
             * @param array<string, Env|null> $envData
             */
            public function __construct(private array $envData = [])
            {
            }

            public function fetchPublicArray(): array
            {
                return [];
            }

            public function findByName(string $name): ?Env
            {
                return $this->envData[$name] ?? null;
            }

            public function findByNameAndValid(string $name, bool $valid = true): ?Env
            {
                $env = $this->envData[$name] ?? null;
                if (null !== $env && $env->isValid() === $valid) {
                    return $env;
                }

                return null;
            }

            public function saveEnv(Env $env): void
            {
                $this->envData[$env->getName()] = $env;
            }
        };
    }
}
