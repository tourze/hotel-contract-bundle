<?php

namespace Tourze\HotelContractBundle\Tests\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\KernelInterface;
use Tourze\HotelContractBundle\Entity\InventorySummary;
use Tourze\HotelContractBundle\Enum\InventorySummaryStatusEnum;
use Tourze\HotelContractBundle\HotelContractBundle;
use Tourze\HotelContractBundle\Repository\InventorySummaryRepository;
use Tourze\HotelProfileBundle\Entity\Hotel;
use Tourze\HotelProfileBundle\Entity\RoomType;
use Tourze\HotelProfileBundle\Enum\HotelStatusEnum;
use Tourze\HotelProfileBundle\Enum\RoomTypeStatusEnum;
use Tourze\HotelProfileBundle\HotelProfileBundle;
use Tourze\IntegrationTestKernel\IntegrationTestKernel;

class InventorySummaryRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private InventorySummaryRepository $repository;

    protected static function createKernel(array $options = []): KernelInterface
    {
        $env = $options['environment'] ?? $_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? 'test';
        $debug = $options['debug'] ?? $_ENV['APP_DEBUG'] ?? $_SERVER['APP_DEBUG'] ?? true;

        return new IntegrationTestKernel($env, $debug, [
            HotelContractBundle::class => ['all' => true],
            HotelProfileBundle::class => ['all' => true],
        ]);
    }

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->repository = static::getContainer()->get(InventorySummaryRepository::class);
    }

    protected function tearDown(): void
    {
        $this->cleanDatabase();
        self::ensureKernelShutdown();
        parent::tearDown();
    }

    private function cleanDatabase(): void
    {
        $connection = $this->entityManager->getConnection();
        $connection->executeStatement('DELETE FROM inventory_summary');
        $connection->executeStatement('DELETE FROM hotel_contract');
        $connection->executeStatement('DELETE FROM ims_hotel_room_type');
        $connection->executeStatement('DELETE FROM ims_hotel_profile');
    }

    public function test_save_withValidSummary_persistsToDatabase(): void
    {
        $summary = $this->createTestSummary();

        $this->repository->save($summary, true);

        $this->assertNotNull($summary->getId());
        $this->assertEquals(10, $summary->getTotalRooms());
    }

    public function test_save_withFlush_immediatelyPersists(): void
    {
        $summary = $this->createTestSummary();

        $this->repository->save($summary, true);
        $foundSummary = $this->repository->find($summary->getId());

        $this->assertNotNull($foundSummary);
        $this->assertEquals($summary->getTotalRooms(), $foundSummary->getTotalRooms());
    }

    public function test_remove_withValidSummary_deletesFromDatabase(): void
    {
        $summary = $this->createTestSummary();
        $this->repository->save($summary, true);
        $summaryId = $summary->getId();

        $this->repository->remove($summary, true);
        $foundSummary = $this->repository->find($summaryId);

        $this->assertNull($foundSummary);
    }

    public function test_findByHotelRoomTypeAndDate_withExistingSummary_returnsSummary(): void
    {
        $hotel = $this->createTestHotel();
        $roomType = $this->createTestRoomType('Standard Room', $hotel);
        $date = new \DateTimeImmutable('2024-01-15');

        $summary = $this->createTestSummary();
        $summary->setHotel($hotel);
        $summary->setRoomType($roomType);
        $summary->setDate($date);
        $this->repository->save($summary, true);

        $foundSummary = $this->repository->findByHotelRoomTypeAndDate(
            $hotel->getId(),
            $roomType->getId(),
            $date
        );

        $this->assertNotNull($foundSummary);
        $this->assertEquals($summary->getId(), $foundSummary->getId());
        $this->assertEquals($hotel->getId(), $foundSummary->getHotel()->getId());
        $this->assertEquals($roomType->getId(), $foundSummary->getRoomType()->getId());
    }

    public function test_findByHotelRoomTypeAndDate_withNonExistentData_returnsNull(): void
    {
        $foundSummary = $this->repository->findByHotelRoomTypeAndDate(
            999999,
            999999,
            new \DateTimeImmutable('2024-01-15')
        );

        $this->assertNull($foundSummary);
    }

    public function test_findByDateRange_returnsOrderedByDate(): void
    {
        $summary1 = $this->createTestSummary();
        $summary1->setDate(new \DateTimeImmutable('2024-01-01'));

        $summary2 = $this->createTestSummary();
        $summary2->setDate(new \DateTimeImmutable('2024-01-03'));

        $summary3 = $this->createTestSummary();
        $summary3->setDate(new \DateTimeImmutable('2024-01-02'));

        $this->repository->save($summary1, false);
        $this->repository->save($summary2, false);
        $this->repository->save($summary3, true);

        $results = $this->repository->findByDateRange(
            new \DateTimeImmutable('2024-01-01'),
            new \DateTimeImmutable('2024-01-31')
        );

        $this->assertCount(3, $results);
        $this->assertEquals('2024-01-01', $results[0]->getDate()->format('Y-m-d'));
        $this->assertEquals('2024-01-02', $results[1]->getDate()->format('Y-m-d'));
        $this->assertEquals('2024-01-03', $results[2]->getDate()->format('Y-m-d'));
    }

    public function test_findByDateRange_withNoMatchingDates_returnsEmptyArray(): void
    {
        $summary = $this->createTestSummary();
        $summary->setDate(new \DateTimeImmutable('2024-06-01'));
        $this->repository->save($summary, true);

        $results = $this->repository->findByDateRange(
            new \DateTimeImmutable('2024-01-01'),
            new \DateTimeImmutable('2024-01-31')
        );

        $this->assertEmpty($results);
    }

    public function test_findByHotelId_returnsOnlyHotelSummaries(): void
    {
        $hotel1 = $this->createTestHotel('Hotel 1');
        $hotel2 = $this->createTestHotel('Hotel 2');

        $summary1 = $this->createTestSummary();
        $summary1->setHotel($hotel1);

        $summary2 = $this->createTestSummary();
        $summary2->setHotel($hotel2);

        $this->repository->save($summary1, false);
        $this->repository->save($summary2, true);

        $results = $this->repository->findByHotelId($hotel1->getId());

        $this->assertCount(1, $results);
        $this->assertEquals($hotel1->getId(), $results[0]->getHotel()->getId());
    }

    public function test_findByRoomTypeId_returnsOnlyRoomTypeSummaries(): void
    {
        $roomType1 = $this->createTestRoomType('Standard Room');
        $roomType2 = $this->createTestRoomType('Deluxe Room');

        $summary1 = $this->createTestSummary();
        $summary1->setRoomType($roomType1);

        $summary2 = $this->createTestSummary();
        $summary2->setRoomType($roomType2);

        $this->repository->save($summary1, false);
        $this->repository->save($summary2, true);

        $results = $this->repository->findByRoomTypeId($roomType1->getId());

        $this->assertCount(1, $results);
        $this->assertEquals($roomType1->getId(), $results[0]->getRoomType()->getId());
    }

    public function test_findByDate_returnsAllSummariesForDate(): void
    {
        $targetDate = new \DateTimeImmutable('2024-01-15');
        $otherDate = new \DateTimeImmutable('2024-01-16');

        $summary1 = $this->createTestSummary();
        $summary1->setDate($targetDate);

        $summary2 = $this->createTestSummary();
        $summary2->setDate($otherDate);

        $summary3 = $this->createTestSummary();
        $summary3->setDate($targetDate);

        $this->repository->save($summary1, false);
        $this->repository->save($summary2, false);
        $this->repository->save($summary3, true);

        $results = $this->repository->findByDate($targetDate);

        $this->assertCount(2, $results);
        foreach ($results as $result) {
            $this->assertEquals($targetDate->format('Y-m-d'), $result->getDate()->format('Y-m-d'));
        }
    }

    public function test_findByStatus_returnsOnlyMatchingStatus(): void
    {
        $summary1 = $this->createTestSummary();
        $summary1->setStatus(InventorySummaryStatusEnum::NORMAL);

        $summary2 = $this->createTestSummary();
        $summary2->setStatus(InventorySummaryStatusEnum::WARNING);

        $summary3 = $this->createTestSummary();
        $summary3->setStatus(InventorySummaryStatusEnum::NORMAL);

        $this->repository->save($summary1, false);
        $this->repository->save($summary2, false);
        $this->repository->save($summary3, true);

        $results = $this->repository->findByStatus(InventorySummaryStatusEnum::NORMAL);

        $this->assertCount(2, $results);
        foreach ($results as $result) {
            $this->assertEquals(InventorySummaryStatusEnum::NORMAL, $result->getStatus());
        }
    }

    public function test_findWarningInventory_returnsOnlyWarningStatus(): void
    {
        $summary1 = $this->createTestSummary();
        $summary1->setStatus(InventorySummaryStatusEnum::NORMAL);

        $summary2 = $this->createTestSummary();
        $summary2->setStatus(InventorySummaryStatusEnum::WARNING);

        $summary3 = $this->createTestSummary();
        $summary3->setStatus(InventorySummaryStatusEnum::SOLD_OUT);

        $this->repository->save($summary1, false);
        $this->repository->save($summary2, false);
        $this->repository->save($summary3, true);

        $results = $this->repository->findWarningInventory();

        $this->assertCount(1, $results);
        $this->assertEquals(InventorySummaryStatusEnum::WARNING, $results[0]->getStatus());
    }

    public function test_findSoldOutInventory_returnsOnlySoldOutStatus(): void
    {
        $summary1 = $this->createTestSummary();
        $summary1->setStatus(InventorySummaryStatusEnum::NORMAL);

        $summary2 = $this->createTestSummary();
        $summary2->setStatus(InventorySummaryStatusEnum::WARNING);

        $summary3 = $this->createTestSummary();
        $summary3->setStatus(InventorySummaryStatusEnum::SOLD_OUT);

        $this->repository->save($summary1, false);
        $this->repository->save($summary2, false);
        $this->repository->save($summary3, true);

        $results = $this->repository->findSoldOutInventory();

        $this->assertCount(1, $results);
        $this->assertEquals(InventorySummaryStatusEnum::SOLD_OUT, $results[0]->getStatus());
    }

    private function createTestHotel(string $name = 'Test Hotel'): Hotel
    {
        $hotel = new Hotel();
        $hotel->setName($name);
        $hotel->setStarLevel(5);
        $hotel->setAddress('Test Address');
        $hotel->setPhone('123456789');
        $hotel->setContactPerson('Test Contact');
        $hotel->setStatus(HotelStatusEnum::OPERATING);

        $this->entityManager->persist($hotel);
        $this->entityManager->flush();

        return $hotel;
    }

    private function createTestRoomType(string $name = 'Standard Room', ?Hotel $hotel = null): RoomType
    {
        if (!$hotel) {
            $hotel = $this->createTestHotel();
        }

        $roomType = new RoomType();
        $roomType->setName($name);
        $roomType->setHotel($hotel);
        $roomType->setDescription('Test room type');
        $roomType->setMaxGuests(2);
        $roomType->setArea(25.0);
        $roomType->setBedType('Double Bed');
        $roomType->setBreakfastCount(2);
        $roomType->setStatus(RoomTypeStatusEnum::ACTIVE);

        $this->entityManager->persist($roomType);
        $this->entityManager->flush();

        return $roomType;
    }


    private function createTestSummary(): InventorySummary
    {
        $hotel = $this->createTestHotel();
        $roomType = $this->createTestRoomType('Standard Room', $hotel);

        $summary = new InventorySummary();
        $summary->setHotel($hotel);
        $summary->setRoomType($roomType);
        $summary->setDate(new \DateTimeImmutable('2024-01-01'));
        $summary->setTotalRooms(10);
        $summary->setAvailableRooms(8);
        $summary->setReservedRooms(1);
        $summary->setSoldRooms(1);
        $summary->setPendingRooms(0);
        $summary->setStatus(InventorySummaryStatusEnum::NORMAL);
        $summary->setLowestPrice('100.00');

        return $summary;
    }
}
