<?php

namespace Tourze\HotelContractBundle\DependencyInjection;

use Tourze\SymfonyDependencyServiceLoader\AutoExtension;

class HotelContractExtension extends AutoExtension
{
    protected function getConfigDir(): string
    {
        return __DIR__ . '/../Resources/config';
    }
}
