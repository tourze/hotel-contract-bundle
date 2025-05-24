<?php

namespace Tourze\HotelContractBundle\Service;

use Knp\Menu\ItemInterface;
use Tourze\EasyAdminMenuBundle\Service\LinkGeneratorInterface;
use Tourze\EasyAdminMenuBundle\Service\MenuProviderInterface;
use Tourze\HotelContractBundle\Entity\HotelContract;

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
    }
}
