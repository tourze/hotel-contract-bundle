<?php

namespace Tourze\HotelContractBundle\Tests\Repository;

use PHPUnit\Framework\TestCase;
use Tourze\HotelContractBundle\Repository\DailyInventoryRepository;
use Tourze\HotelContractBundle\Repository\HotelContractRepository;
use Tourze\HotelContractBundle\Repository\InventorySummaryRepository;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;

/**
 * 抽象 Repository 测试类，用于测试 Repository 类的基本结构
 * 由于需要 Doctrine 支持，仅测试类的继承关系和方法存在性
 */
class AbstractRepositoryTest extends TestCase
{
    public function test_dailyInventoryRepository_extendsServiceEntityRepository(): void
    {
        $this->assertTrue(is_subclass_of(DailyInventoryRepository::class, ServiceEntityRepository::class));
    }

    public function test_hotelContractRepository_extendsServiceEntityRepository(): void
    {
        $this->assertTrue(is_subclass_of(HotelContractRepository::class, ServiceEntityRepository::class));
    }

    public function test_inventorySummaryRepository_extendsServiceEntityRepository(): void
    {
        $this->assertTrue(is_subclass_of(InventorySummaryRepository::class, ServiceEntityRepository::class));
    }

    public function test_dailyInventoryRepository_hasSaveMethod(): void
    {
        $this->assertTrue(method_exists(DailyInventoryRepository::class, 'save'));
    }

    public function test_dailyInventoryRepository_hasRemoveMethod(): void
    {
        $this->assertTrue(method_exists(DailyInventoryRepository::class, 'remove'));
    }

    public function test_hotelContractRepository_hasSaveMethod(): void
    {
        $this->assertTrue(method_exists(HotelContractRepository::class, 'save'));
    }

    public function test_hotelContractRepository_hasRemoveMethod(): void
    {
        $this->assertTrue(method_exists(HotelContractRepository::class, 'remove'));
    }

    public function test_inventorySummaryRepository_hasSaveMethod(): void
    {
        $this->assertTrue(method_exists(InventorySummaryRepository::class, 'save'));
    }

    public function test_inventorySummaryRepository_hasRemoveMethod(): void
    {
        $this->assertTrue(method_exists(InventorySummaryRepository::class, 'remove'));
    }

    public function test_dailyInventoryRepository_hasCustomQueryMethods(): void
    {
        $this->assertTrue(method_exists(DailyInventoryRepository::class, 'findByRoomAndDate'));
        $this->assertTrue(method_exists(DailyInventoryRepository::class, 'findAvailableByDateRange'));
        $this->assertTrue(method_exists(DailyInventoryRepository::class, 'findByContractId'));
        $this->assertTrue(method_exists(DailyInventoryRepository::class, 'findByDate'));
        $this->assertTrue(method_exists(DailyInventoryRepository::class, 'findByStatus'));
    }

    public function test_hotelContractRepository_hasCustomQueryMethods(): void
    {
        $this->assertTrue(method_exists(HotelContractRepository::class, 'findByHotelId'));
        $this->assertTrue(method_exists(HotelContractRepository::class, 'findActiveContracts'));
        $this->assertTrue(method_exists(HotelContractRepository::class, 'findByContractNo'));
        $this->assertTrue(method_exists(HotelContractRepository::class, 'findContractsInDateRange'));
    }

    public function test_inventorySummaryRepository_hasCustomQueryMethods(): void
    {
        $this->assertTrue(method_exists(InventorySummaryRepository::class, 'findByHotelRoomTypeAndDate'));
        $this->assertTrue(method_exists(InventorySummaryRepository::class, 'findByDateRange'));
        $this->assertTrue(method_exists(InventorySummaryRepository::class, 'findByHotelId'));
        $this->assertTrue(method_exists(InventorySummaryRepository::class, 'findByStatus'));
        $this->assertTrue(method_exists(InventorySummaryRepository::class, 'findWarningInventory'));
        $this->assertTrue(method_exists(InventorySummaryRepository::class, 'findSoldOutInventory'));
    }

    public function test_repositoryClasses_haveCorrectNamespace(): void
    {
        $this->assertStringStartsWith('Tourze\\HotelContractBundle\\Repository\\', DailyInventoryRepository::class);
        $this->assertStringStartsWith('Tourze\\HotelContractBundle\\Repository\\', HotelContractRepository::class);
        $this->assertStringStartsWith('Tourze\\HotelContractBundle\\Repository\\', InventorySummaryRepository::class);
    }
} 