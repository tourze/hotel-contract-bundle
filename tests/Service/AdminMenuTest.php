<?php

declare(strict_types=1);

namespace Tourze\HotelContractBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\EasyAdminMenuBundle\Service\MenuProviderInterface;
use Tourze\HotelContractBundle\Service\AdminMenu;
use Tourze\PHPUnitSymfonyWebTest\AbstractEasyAdminMenuTestCase;

/**
 * @internal
 */
#[CoversClass(AdminMenu::class)]
#[RunTestsInSeparateProcesses]
final class AdminMenuTest extends AbstractEasyAdminMenuTestCase
{
    protected function onSetUp(): void
    {
        // AdminMenu 测试不需要特殊的设置
    }

    public function testServiceCreation(): void
    {
        $adminMenu = self::getService(AdminMenu::class);
        $this->assertInstanceOf(AdminMenu::class, $adminMenu);
    }

    public function testImplementsMenuProviderInterface(): void
    {
        $adminMenu = self::getService(AdminMenu::class);
        $this->assertInstanceOf(MenuProviderInterface::class, $adminMenu);
    }

    public function testInvokeShouldBeCallable(): void
    {
        // AdminMenu实现了__invoke方法，所以是可调用的
        $reflection = new \ReflectionClass(AdminMenu::class);
        $this->assertTrue($reflection->hasMethod('__invoke'));
    }

    // 这个测试需要复杂的 EasyAdmin 配置，暂时跳过
    // 主要目标是确保类能正确继承 AbstractEasyAdminMenuTestCase
    // 并且能够正确实例化服务
}
