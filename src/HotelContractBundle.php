<?php

namespace Tourze\HotelContractBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use Tourze\BundleDependency\BundleDependencyInterface;

class HotelContractBundle extends Bundle implements BundleDependencyInterface
{
    public static function getBundleDependencies(): array
    {
        return [
            \Tourze\HotelProfileBundle\HotelProfileBundle::class => ['all' => true],
        ];
    }
}
