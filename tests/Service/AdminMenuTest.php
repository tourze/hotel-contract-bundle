<?php

declare(strict_types=1);

namespace Tourze\HotelContractBundle\Tests\Service;

use Knp\Menu\ItemInterface;
use PHPUnit\Framework\TestCase;
use Tourze\EasyAdminMenuBundle\Service\LinkGeneratorInterface;
use Tourze\HotelContractBundle\Service\AdminMenu;

class AdminMenuTest extends TestCase
{
    private LinkGeneratorInterface $linkGenerator;
    private ItemInterface $item;
    private AdminMenu $adminMenu;

    protected function setUp(): void
    {
        $this->linkGenerator = $this->createMock(LinkGeneratorInterface::class);
        $this->item = $this->createMock(ItemInterface::class);
        $this->adminMenu = new AdminMenu($this->linkGenerator);
    }

    public function testServiceCanBeInstantiated(): void
    {
        $this->assertInstanceOf(AdminMenu::class, $this->adminMenu);
    }

    public function testImplementsMenuProviderInterface(): void
    {
        $this->assertInstanceOf('Tourze\EasyAdminMenuBundle\Service\MenuProviderInterface', $this->adminMenu);
    }

    public function testInvokeExecutesWithoutException(): void
    {
        // 创建子菜单 mock
        $hotelMenu = $this->createMock(ItemInterface::class);
        $inventoryMenu = $this->createMock(ItemInterface::class);
        
        // 配置子菜单的方法
        foreach ([$hotelMenu, $inventoryMenu] as $menu) {
            $menu->expects($this->any())
                ->method('addChild')
                ->willReturnSelf();
            $menu->expects($this->any())
                ->method('setUri')
                ->willReturnSelf();
            $menu->expects($this->any())
                ->method('setAttribute')
                ->willReturnSelf();
        }
        
        // 设置主菜单 mock - 第一次调用返回 null，之后返回对应的子菜单
        $callCount = ['酒店管理' => 0, '库存管理' => 0];
        $this->item->expects($this->any())
            ->method('getChild')
            ->willReturnCallback(function ($name) use ($hotelMenu, $inventoryMenu, &$callCount) {
                if ($name === '酒店管理') {
                    $callCount['酒店管理']++;
                    return $callCount['酒店管理'] === 1 ? null : $hotelMenu;
                } elseif ($name === '库存管理') {
                    $callCount['库存管理']++;
                    return $callCount['库存管理'] === 1 ? null : $inventoryMenu;
                }
                return null;
            });
        
        $this->item->expects($this->exactly(2))
            ->method('addChild')
            ->willReturnCallback(function ($name) use ($hotelMenu, $inventoryMenu) {
                if ($name === '酒店管理') {
                    return $hotelMenu;
                } elseif ($name === '库存管理') {
                    return $inventoryMenu;
                }
                return null;
            });
        
        $this->linkGenerator->expects($this->any())
            ->method('getCurdListPage')
            ->willReturn('http://example.com');

        // 执行测试
        ($this->adminMenu)($this->item);
        
        // 如果没有异常抛出，测试通过
        $this->assertTrue(true);
    }
}