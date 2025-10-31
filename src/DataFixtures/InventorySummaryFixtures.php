<?php

namespace Tourze\HotelContractBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\DependencyInjection\Attribute\When;
use Tourze\HotelContractBundle\Entity\HotelContract;
use Tourze\HotelContractBundle\Entity\InventorySummary;
use Tourze\HotelContractBundle\Enum\InventorySummaryStatusEnum;
use Tourze\HotelProfileBundle\DataFixtures\HotelFixtures;
use Tourze\HotelProfileBundle\DataFixtures\RoomTypeFixtures;
use Tourze\HotelProfileBundle\Entity\Hotel;
use Tourze\HotelProfileBundle\Entity\RoomType;

#[When(env: 'test')]
#[When(env: 'dev')]
class InventorySummaryFixtures extends Fixture implements FixtureGroupInterface, DependentFixtureInterface
{
    public const SUMMARY_NORMAL = 'summary-normal';
    public const SUMMARY_WARNING = 'summary-warning';
    public const SUMMARY_SOLD_OUT = 'summary-sold-out';

    public function load(ObjectManager $manager): void
    {
        $luxuryHotel = $this->getReference(HotelFixtures::LUXURY_HOTEL_REFERENCE, Hotel::class);
        $businessHotel = $this->getReference(HotelFixtures::BUSINESS_HOTEL_REFERENCE, Hotel::class);

        $standardRoom = $this->getReference(RoomTypeFixtures::STANDARD_ROOM_REFERENCE, RoomType::class);
        $deluxeRoom = $this->getReference(RoomTypeFixtures::LUXURY_SUITE_REFERENCE, RoomType::class);
        $businessRoom = $this->getReference(RoomTypeFixtures::BUSINESS_ROOM_REFERENCE, RoomType::class);

        $activeContract = $this->getReference(HotelContractFixtures::CONTRACT_ACTIVE, HotelContract::class);

        // 正常库存汇总
        $normalSummary = new InventorySummary();
        $normalSummary->setHotel($luxuryHotel);
        $normalSummary->setRoomType($standardRoom);
        // $normalSummary 不需要设置 contract
        $normalSummary->setDate(new \DateTimeImmutable('2024-08-15'));
        $normalSummary->setTotalRooms(50);
        $normalSummary->setAvailableRooms(35);
        $normalSummary->setReservedRooms(8);
        $normalSummary->setSoldRooms(7);
        $normalSummary->setPendingRooms(0);
        $normalSummary->setStatus(InventorySummaryStatusEnum::NORMAL);
        $normalSummary->setLowestPrice('250.00');
        $normalSummary->setLowestContract($activeContract);

        $manager->persist($normalSummary);

        // 警告库存汇总
        $warningSummary = new InventorySummary();
        $warningSummary->setHotel($luxuryHotel);
        $warningSummary->setRoomType($deluxeRoom);
        // $warningSummary 不需要设置 contract
        $warningSummary->setDate(new \DateTimeImmutable('2024-08-16'));
        $warningSummary->setTotalRooms(20);
        $warningSummary->setAvailableRooms(2);
        $warningSummary->setReservedRooms(3);
        $warningSummary->setSoldRooms(15);
        $warningSummary->setPendingRooms(0);
        $warningSummary->setStatus(InventorySummaryStatusEnum::WARNING);
        $warningSummary->setLowestPrice('450.00');
        $warningSummary->setLowestContract($activeContract);

        $manager->persist($warningSummary);

        // 售罄库存汇总
        $soldOutSummary = new InventorySummary();
        $soldOutSummary->setHotel($businessHotel);
        $soldOutSummary->setRoomType($businessRoom);
        $soldOutSummary->setDate(new \DateTimeImmutable('2024-08-17'));
        $soldOutSummary->setTotalRooms(30);
        $soldOutSummary->setAvailableRooms(0);
        $soldOutSummary->setReservedRooms(0);
        $soldOutSummary->setSoldRooms(30);
        $soldOutSummary->setPendingRooms(0);
        $soldOutSummary->setStatus(InventorySummaryStatusEnum::SOLD_OUT);
        $soldOutSummary->setLowestPrice('180.00');

        $manager->persist($soldOutSummary);

        // 额外的测试数据
        for ($i = 1; $i <= 5; ++$i) {
            $summary = new InventorySummary();
            $summary->setHotel($luxuryHotel);
            $summary->setRoomType(0 === $i % 2 ? $standardRoom : $deluxeRoom);
            // $summary 不需要设置 contract
            $summary->setDate(new \DateTimeImmutable(sprintf('2024-08-%02d', $i + 20)));
            $summary->setTotalRooms(40);
            $summary->setAvailableRooms(40 - ($i * 5));
            $summary->setReservedRooms($i * 2);
            $summary->setSoldRooms($i * 3);
            $summary->setPendingRooms(0);
            $summary->setStatus($i <= 3 ? InventorySummaryStatusEnum::NORMAL : InventorySummaryStatusEnum::WARNING);
            $summary->setLowestPrice(sprintf('%.2f', 200 + $i * 10));
            $summary->setLowestContract($activeContract);

            $manager->persist($summary);
        }

        $manager->flush();

        $this->addReference(self::SUMMARY_NORMAL, $normalSummary);
        $this->addReference(self::SUMMARY_WARNING, $warningSummary);
        $this->addReference(self::SUMMARY_SOLD_OUT, $soldOutSummary);
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
