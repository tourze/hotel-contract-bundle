<?php

namespace Tourze\HotelContractBundle\Tests;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\KernelInterface;
use Tourze\HotelContractBundle\HotelContractBundle;
use Tourze\HotelContractBundle\Repository\DailyInventoryRepository;
use Tourze\HotelContractBundle\Repository\HotelContractRepository;
use Tourze\HotelContractBundle\Repository\InventorySummaryRepository;
use Tourze\IntegrationTestKernel\IntegrationTestKernel;

class HotelContractIntegrationTest extends KernelTestCase
{
    protected function setUp(): void
    {
        self::bootKernel();
    }

    protected static function createKernel(array $options = []): KernelInterface
    {
        $env = $options['environment'] ?? $_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? 'test';
        $debug = $options['debug'] ?? $_ENV['APP_DEBUG'] ?? $_SERVER['APP_DEBUG'] ?? true;

        return new IntegrationTestKernel($env, $debug, [
            HotelContractBundle::class => ['all' => true],
        ]);
    }

    public function test_hotelContractRepository_isRegisteredAsService(): void
    {
        $container = static::getContainer();

        $this->assertTrue($container->has(HotelContractRepository::class));

        $repository = $container->get(HotelContractRepository::class);
        $this->assertInstanceOf(HotelContractRepository::class, $repository);
    }

    public function test_dailyInventoryRepository_isRegisteredAsService(): void
    {
        $container = static::getContainer();

        $this->assertTrue($container->has(DailyInventoryRepository::class));

        $repository = $container->get(DailyInventoryRepository::class);
        $this->assertInstanceOf(DailyInventoryRepository::class, $repository);
    }

    public function test_inventorySummaryRepository_isRegisteredAsService(): void
    {
        $container = static::getContainer();

        $this->assertTrue($container->has(InventorySummaryRepository::class));

        $repository = $container->get(InventorySummaryRepository::class);
        $this->assertInstanceOf(InventorySummaryRepository::class, $repository);
    }

    public function test_bundleIsLoaded(): void
    {
        $kernel = self::$kernel;
        $bundles = $kernel->getBundles();

        $this->assertArrayHasKey('HotelContractBundle', $bundles);
        $this->assertInstanceOf(\Tourze\HotelContractBundle\HotelContractBundle::class, $bundles['HotelContractBundle']);
    }
}
