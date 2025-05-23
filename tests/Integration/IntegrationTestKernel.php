<?php

namespace Tourze\HotelContractBundle\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Tourze\BundleDependency\ResolveHelper;
use Tourze\HotelContractBundle\HotelContractBundle;

class IntegrationTestKernel extends Kernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        foreach (ResolveHelper::resolveBundleDependencies([
            HotelContractBundle::class => ['all' => true],
        ]) as $bundleClass => $bundleConfig) {
            yield new $bundleClass();
        }
    }

    protected function configureContainer(ContainerBuilder $container, LoaderInterface $loader): void
    {
        $container->loadFromExtension('framework', [
            'secret' => 'TEST_SECRET',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'router' => [
                'utf8' => true,
            ],
            'php_errors' => [
                'log' => true,
            ],
            'validation' => [
                'email_validation_mode' => 'html5',
            ],
            'uid' => [
                'default_uuid_version' => 7,
                'time_based_uuid_version' => 7,
            ],
        ]);
        $container->loadFromExtension('security', [
            'providers' => [
                'test_provider' => [
                    'memory' => [
                        'users' => [],
                    ],
                ],
            ],
            'firewalls' => [
                'main' => [
                    'provider' => 'test_provider',
                    'http_basic' => null,
                ],
            ],
        ]);

        $container->loadFromExtension('doctrine', [
            'dbal' => [
                'driver' => 'pdo_sqlite',
                'memory' => true,
            ],
            'orm' => [
                'auto_generate_proxy_classes' => true,
                'controller_resolver' => [
                    'auto_mapping' => false,
                ],
                'enable_lazy_ghost_objects' => true,
                'naming_strategy' => 'doctrine.orm.naming_strategy.underscore_number_aware',
                'mappings' => [
                    'HotelContractBundle' => [
                        'is_bundle' => true,
                        'type' => 'attribute',
                        'dir' => 'Entity',
                        'prefix' => 'Tourze\HotelContractBundle\Entity',
                    ],
                ],
            ],
        ]);
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        // 测试不需要路由配置
    }
}
