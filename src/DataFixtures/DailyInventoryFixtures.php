<?php

namespace Tourze\HotelContractBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\DependencyInjection\Attribute\When;
use Tourze\HotelContractBundle\Entity\DailyInventory;
use Tourze\HotelContractBundle\Entity\HotelContract;
use Tourze\HotelContractBundle\Enum\DailyInventoryStatusEnum;
use Tourze\HotelProfileBundle\DataFixtures\HotelFixtures;
use Tourze\HotelProfileBundle\DataFixtures\RoomTypeFixtures;
use Tourze\HotelProfileBundle\Entity\Hotel;
use Tourze\HotelProfileBundle\Entity\RoomType;

#[When(env: 'test')]
#[When(env: 'dev')]
class DailyInventoryFixtures extends Fixture implements FixtureGroupInterface, DependentFixtureInterface
{
    public const INVENTORY_AVAILABLE = 'inventory-available';
    public const INVENTORY_SOLD = 'inventory-sold';
    public const INVENTORY_RESERVED = 'inventory-reserved';

    public function load(ObjectManager $manager): void
    {
        $luxuryHotel = $this->getReference(HotelFixtures::LUXURY_HOTEL_REFERENCE, Hotel::class);
        $businessHotel = $this->getReference(HotelFixtures::BUSINESS_HOTEL_REFERENCE, Hotel::class);

        $standardRoom = $this->getReference(RoomTypeFixtures::STANDARD_ROOM_REFERENCE, RoomType::class);
        $deluxeRoom = $this->getReference(RoomTypeFixtures::LUXURY_SUITE_REFERENCE, RoomType::class);
        $businessRoom = $this->getReference(RoomTypeFixtures::BUSINESS_ROOM_REFERENCE, RoomType::class);

        $activeContract = $this->getReference(HotelContractFixtures::CONTRACT_ACTIVE, HotelContract::class);

        // 可用库存
        $availableInventory = new DailyInventory();
        $availableInventory->setCode('INV-2024-001');
        $availableInventory->setRoomType($standardRoom);
        $availableInventory->setHotel($luxuryHotel);
        $availableInventory->setContract($activeContract);
        $availableInventory->setDate(new \DateTimeImmutable('2024-08-15'));
        $availableInventory->setIsReserved(false);
        $availableInventory->setStatus(DailyInventoryStatusEnum::AVAILABLE);
        $availableInventory->setCostPrice('150.00');
        $availableInventory->setSellingPrice('300.00');
        // DailyInventory 实体不包含 totalRooms 等字段

        $manager->persist($availableInventory);

        // 已售完库存
        $soldInventory = new DailyInventory();
        $soldInventory->setCode('INV-2024-002');
        $soldInventory->setRoomType($deluxeRoom);
        $soldInventory->setHotel($luxuryHotel);
        $soldInventory->setContract($activeContract);
        $soldInventory->setDate(new \DateTimeImmutable('2024-08-16'));
        $soldInventory->setIsReserved(false);
        $soldInventory->setStatus(DailyInventoryStatusEnum::SOLD);
        $soldInventory->setCostPrice('200.00');
        $soldInventory->setSellingPrice('450.00');
        // DailyInventory 实体不包含 totalRooms 等字段

        $manager->persist($soldInventory);

        // 预留库存
        $reservedInventory = new DailyInventory();
        $reservedInventory->setCode('INV-2024-003');
        $reservedInventory->setRoomType($businessRoom);
        $reservedInventory->setHotel($businessHotel);
        $reservedInventory->setDate(new \DateTimeImmutable('2024-08-17'));
        $reservedInventory->setIsReserved(true);
        $reservedInventory->setStatus(DailyInventoryStatusEnum::RESERVED);
        $reservedInventory->setCostPrice('100.00');
        $reservedInventory->setSellingPrice('180.00');
        // DailyInventory 实体不包含 totalRooms 等字段
        $reservedInventory->setPriceAdjustReason('节假日价格调整');

        $manager->persist($reservedInventory);

        // 额外的测试数据
        for ($i = 1; $i <= 7; ++$i) {
            $inventory = new DailyInventory();
            $inventory->setCode(sprintf('TEST-INV-%03d', $i));
            $inventory->setRoomType(0 === $i % 2 ? $standardRoom : $businessRoom);
            $inventory->setHotel($luxuryHotel);
            $inventory->setContract($activeContract);
            $inventory->setDate(new \DateTimeImmutable(sprintf('2024-08-%02d', $i + 10)));
            $inventory->setIsReserved(false);
            $inventory->setStatus(DailyInventoryStatusEnum::AVAILABLE);
            $inventory->setCostPrice('120.00');
            $inventory->setSellingPrice('250.00');
            // DailyInventory 实体不包含 totalRooms 等字段

            $manager->persist($inventory);
        }

        $manager->flush();

        $this->addReference(self::INVENTORY_AVAILABLE, $availableInventory);
        $this->addReference(self::INVENTORY_SOLD, $soldInventory);
        $this->addReference(self::INVENTORY_RESERVED, $reservedInventory);
    }

    public static function getGroups(): array
    {
        return ['hotel-contract', 'test'];
    }

    public function getDependencies(): array
    {
        return [
            HotelFixtures::class,
            RoomTypeFixtures::class,
            HotelContractFixtures::class,
        ];
    }
}
