<?php

namespace Tourze\HotelContractBundle\Service;

use Knp\Menu\ItemInterface;
use Tourze\EasyAdminMenuBundle\Service\LinkGeneratorInterface;
use Tourze\EasyAdminMenuBundle\Service\MenuProviderInterface;
use Tourze\HotelContractBundle\Entity\DailyInventory;
use Tourze\HotelContractBundle\Entity\HotelContract;
use Tourze\HotelContractBundle\Entity\InventorySummary;

/**
 * 应用管理菜单服务
 */
class AdminMenu implements MenuProviderInterface
{
    public function __construct(
        private readonly LinkGeneratorInterface $linkGenerator,
    ) {
    }

    public function __invoke(ItemInterface $item): void
    {
        if (!$item->getChild('酒店管理')) {
            $item->addChild('酒店管理');
        }
        $appMenu = $item->getChild('酒店管理');
        $appMenu->addChild('合同管理')->setUri($this->linkGenerator->getCurdListPage(HotelContract::class))->setAttribute('icon', 'fas fa-file-contract');

        if (!$item->getChild('库存管理')) {
            $item->addChild('库存管理');
        }
        $appMenu = $item->getChild('库存管理');
        $appMenu->addChild('房型库存')->setUri($this->linkGenerator->getCurdListPage(DailyInventory::class))->setAttribute('icon', 'fas fa-calendar-check');
        $appMenu->addChild('库存统计')->setUri($this->linkGenerator->getCurdListPage(InventorySummary::class))->setAttribute('icon', 'fas fa-chart-bar');
    }
}
