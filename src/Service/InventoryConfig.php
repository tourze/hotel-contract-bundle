<?php

namespace Tourze\HotelContractBundle\Service;

use Tourze\EnvManageBundle\Entity\Env;
use Tourze\EnvManageBundle\Repository\EnvRepository;

class InventoryConfig
{
    private const PREFIX = 'INVENTORY_';
    private const CONFIG_KEYS = [
        'warning_threshold' => self::PREFIX . 'WARNING_THRESHOLD',
        'email_recipients' => self::PREFIX . 'EMAIL_RECIPIENTS',
        'enable_warning' => self::PREFIX . 'ENABLE_WARNING',
        'warning_interval' => self::PREFIX . 'WARNING_INTERVAL',
    ];

    public function __construct(
        private readonly EnvRepository $envRepository,
    ) {
    }

    /**
     * 读取库存预警配置
     *
     * @return array 库存预警配置
     */
    public function getWarningConfig(): array
    {
        $config = $this->getDefaultConfig();
        
        foreach (self::CONFIG_KEYS as $key => $envName) {
            $env = $this->envRepository->findOneBy(['name' => $envName, 'valid' => true]);
            if ($env) {
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
     * @param array $config 库存预警配置
     * @return bool 是否保存成功
     */
    public function saveWarningConfig(array $config): bool
    {
        try {
            foreach (self::CONFIG_KEYS as $key => $envName) {
                if (!isset($config[$key])) {
                    continue;
                }

                $env = $this->envRepository->findOneBy(['name' => $envName]) ?? new Env();
                $env->setName($envName);
                $env->setValue((string) $config[$key]);
                $env->setRemark($this->getRemarkForKey($key));
                $env->setValid(true);
                $env->setSync(true);

                $this->envRepository->getEntityManager()->persist($env);
            }

            $this->envRepository->getEntityManager()->flush();
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * 获取默认配置
     *
     * @return array 默认配置
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
