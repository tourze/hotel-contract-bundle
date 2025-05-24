<?php

namespace Tourze\HotelContractBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use Tourze\BundleDependency\BundleDependencyInterface;

class HotelContractBundle extends Bundle implements BundleDependencyInterface
{
    public static function getBundleDependencies(): array
    {
        return [
            \Tourze\RoutingAutoLoaderBundle\RoutingAutoLoaderBundle::class => ['all' => true],
            \Tourze\HotelProfileBundle\HotelProfileBundle::class => ['all' => true],
            \Tourze\EnvManageBundle\EnvManageBundle::class => ['all' => true],
        ];
    }
}
