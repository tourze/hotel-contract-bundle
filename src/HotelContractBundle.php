<?php

namespace Tourze\HotelContractBundle;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use EasyCorp\Bundle\EasyAdminBundle\EasyAdminBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Tourze\BundleDependency\BundleDependencyInterface;
use Tourze\EnvManageBundle\EnvManageBundle;
use Tourze\HotelProfileBundle\HotelProfileBundle;
use Tourze\RoutingAutoLoaderBundle\RoutingAutoLoaderBundle;

class HotelContractBundle extends Bundle implements BundleDependencyInterface
{
    public static function getBundleDependencies(): array
    {
        return [
            FrameworkBundle::class => ['all' => true],
            EasyAdminBundle::class => ['all' => true],
            TwigBundle::class => ['all' => true],
            SecurityBundle::class => ['all' => true],
            DoctrineBundle::class => ['all' => true],
            RoutingAutoLoaderBundle::class => ['all' => true],
            HotelProfileBundle::class => ['all' => true],
            EnvManageBundle::class => ['all' => true],
        ];
    }
}
