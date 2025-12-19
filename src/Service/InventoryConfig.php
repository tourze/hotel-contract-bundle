<?php

namespace Tourze\HotelContractBundle\Service;

use Tourze\EnvManageBundle\Entity\Env;
use Tourze\EnvManageBundle\Service\EnvService;

final class InventoryConfig
{
    private const PREFIX = 'INVENTORY_';
    private const CONFIG_KEYS = [
        'warning_threshold' => self::PREFIX . 'WARNING_THRESHOLD',
        'email_recipients' => self::PREFIX . 'EMAIL_RECIPIENTS',
        'enable_warning' => self::PREFIX . 'ENABLE_WARNING',
        'warning_interval' => self::PREFIX . 'WARNING_INTERVAL',
    ];

    public function __construct(
        private readonly EnvService $envService,
    ) {
    }

    /**
     * 读取库存预警配置
     *
     * @return array<string, mixed> 库存预警配置
     */
    public function getWarningConfig(): array
    {
        $config = $this->getDefaultConfig();

        foreach (self::CONFIG_KEYS as $key => $envName) {
            $env = $this->envService->findByNameAndValid($envName, true);
            if (null !== $env) {
                $value = $env->getValue();

                // 根据配置项类型转换值
                $config[$key] = match ($key) {
                    'warning_threshold', 'warning_interval' => (int) $value,
                    'enable_warning' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
                    default => $value,
                };
            }
        }

        return $config;
    }

    /**
     * 保存库存预警配置
     *
     * @param array<string, mixed> $config 库存预警配置
     *
     * @return bool 是否保存成功
     */
    public function saveWarningConfig(array $config): bool
    {
        try {
            foreach (self::CONFIG_KEYS as $key => $envName) {
                if (!isset($config[$key])) {
                    continue;
                }

                $this->saveConfigItem($envName, $key, $config[$key]);
            }

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * 保存单个配置项
     *
     * @param string $envName 环境变量名称
     * @param string $key 配置键名
     * @param mixed $value 配置值
     */
    private function saveConfigItem(string $envName, string $key, mixed $value): void
    {
        $stringValue = $this->convertValueToString($value);
        if (null === $stringValue) {
            return;
        }

        $env = $this->envService->findByName($envName) ?? new Env();
        $env->setName($envName);
        $env->setValue($stringValue);
        $env->setRemark($this->getRemarkForKey($key));
        $env->setValid(true);
        $env->setSync(true);

        $this->envService->saveEnv($env);
    }

    /**
     * 将配置值转换为字符串
     *
     * @param mixed $value 配置值
     *
     * @return string|null 转换后的字符串，无法转换时返回null
     */
    private function convertValueToString(mixed $value): ?string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_string($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        if (is_array($value)) {
            return json_encode($value, JSON_THROW_ON_ERROR);
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return (string) $value;
        }

        return null;
    }

    /**
     * 获取默认配置
     *
     * @return array<string, mixed> 默认配置
     */
    private function getDefaultConfig(): array
    {
        return [
            'warning_threshold' => 10, // 预警阈值为库存剩余10%
            'email_recipients' => '', // 默认无收件人
            'enable_warning' => true, // 默认启用预警
            'warning_interval' => 24, // 预警发送间隔，单位小时
        ];
    }

    /**
     * 获取配置项的备注说明
     *
     * @param string $key 配置项键名
     *
     * @return string 备注说明
     */
    private function getRemarkForKey(string $key): string
    {
        return match ($key) {
            'warning_threshold' => '库存预警阈值（百分比）',
            'email_recipients' => '预警邮件收件人（多个用逗号分隔）',
            'enable_warning' => '是否启用库存预警',
            'warning_interval' => '预警发送间隔（小时）',
            default => '库存管理配置项',
        };
    }
}
