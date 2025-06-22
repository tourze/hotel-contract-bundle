<?php

namespace Tourze\HotelContractBundle\Tests\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use PHPUnit\Framework\TestCase;
use Tourze\HotelContractBundle\Repository\DailyInventoryRepository;
use Tourze\HotelContractBundle\Repository\HotelContractRepository;
use Tourze\HotelContractBundle\Repository\InventorySummaryRepository;

/**
 * 抽象 Repository 测试类，用于测试 Repository 类的基本结构
 * 由于需要 Doctrine 支持，仅测试类的继承关系和方法存在性
 */
class AbstractRepositoryTest extends TestCase
{
    public function test_repositoriesExtendServiceEntityRepository(): void
    {
        // 验证所有 Repository 类都继承自 ServiceEntityRepository
        $repositories = [
            DailyInventoryRepository::class,
            HotelContractRepository::class,
            InventorySummaryRepository::class,
        ];

        foreach ($repositories as $repositoryClass) {
            $reflection = new \ReflectionClass($repositoryClass);
            $parent = $reflection->getParentClass();
            $this->assertNotFalse($parent);
            $this->assertEquals(ServiceEntityRepository::class, $parent->getName());
        }
    }

    public function test_repository_methods_exist(): void
    {
        // 使用反射API检查方法存在性
        $dailyInventoryReflection = new \ReflectionClass(DailyInventoryRepository::class);
        $this->assertTrue($dailyInventoryReflection->hasMethod('save'));
        $this->assertTrue($dailyInventoryReflection->hasMethod('remove'));

        $contractReflection = new \ReflectionClass(HotelContractRepository::class);
        $this->assertTrue($contractReflection->hasMethod('save'));
        $this->assertTrue($contractReflection->hasMethod('remove'));

        $summaryReflection = new \ReflectionClass(InventorySummaryRepository::class);
        $this->assertTrue($summaryReflection->hasMethod('save'));
        $this->assertTrue($summaryReflection->hasMethod('remove'));
    }

    public function test_dailyInventoryRepository_hasCustomQueryMethods(): void
    {
        $reflection = new \ReflectionClass(DailyInventoryRepository::class);
        $this->assertTrue($reflection->hasMethod('findByRoomAndDate'));
    }

    public function test_hotelContractRepository_hasCustomQueryMethods(): void
    {
        $reflection = new \ReflectionClass(HotelContractRepository::class);
        $this->assertTrue($reflection->hasMethod('findByHotelId'));
    }

    public function test_inventorySummaryRepository_hasCustomQueryMethods(): void
    {
        $reflection = new \ReflectionClass(InventorySummaryRepository::class);
        $this->assertTrue($reflection->hasMethod('findByHotelRoomTypeAndDate'));
    }

    public function test_repositoryClasses_haveCorrectNamespace(): void
    {
        $this->assertStringStartsWith('Tourze\\HotelContractBundle\\Repository\\', DailyInventoryRepository::class);
        $this->assertStringStartsWith('Tourze\\HotelContractBundle\\Repository\\', HotelContractRepository::class);
        $this->assertStringStartsWith('Tourze\\HotelContractBundle\\Repository\\', InventorySummaryRepository::class);
    }
} 