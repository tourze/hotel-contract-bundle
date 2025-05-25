<?php

namespace Tourze\HotelContractBundle\Service;

//use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Knp\Menu\ItemInterface;
use Tourze\EasyAdminMenuBundle\Service\LinkGeneratorInterface;
use Tourze\EasyAdminMenuBundle\Service\MenuProviderInterface;
use Tourze\HotelContractBundle\Entity\DailyInventory;
use Tourze\HotelContractBundle\Entity\HotelContract;
use Tourze\HotelContractBundle\Entity\InventorySummary;

//use Tourze\HotelContractBundle\Controller\Admin\InventorySummaryCrudController;

/**
 * 应用管理菜单服务
 */
class AdminMenu implements MenuProviderInterface
{
    public function __construct(
        private readonly LinkGeneratorInterface $linkGenerator,
//        private readonly AdminUrlGenerator $adminUrlGenerator,
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

//        if (!$item->getChild('价格管理')) {
//            $item->addChild('价格管理');
//        }
//        $subMenu = $item->getChild('价格管理');
//        $subMenu->addChild('合同价格日历')
//            ->setUri($this->adminUrlGenerator
//                ->unsetAll()
//                ->setController(InventorySummaryCrudController::class)
//                ->setAction('contractPriceCalendar')
//                ->generateUrl())
//            ->setAttribute('icon', 'fas fa-calendar-alt');
//        $subMenu->addChild('销售价格管理')
//            ->setUri($this->adminUrlGenerator
//                ->unsetAll()
//                ->setController(InventorySummaryCrudController::class)
//                ->setAction('sellingPriceManagement')
//                ->generateUrl())
//            ->setAttribute('icon', 'fas fa-tag');
//        $subMenu->addChild('批量调价')
//            ->setUri($this->adminUrlGenerator
//                ->unsetAll()
//                ->setController(InventorySummaryCrudController::class)
//                ->setAction('batchPriceAdjustment')
//                ->generateUrl())
//            ->setAttribute('icon', 'fas fa-money-bill-wave');
    }
}
