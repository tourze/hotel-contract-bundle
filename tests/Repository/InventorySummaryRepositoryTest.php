<?php

namespace Tourze\HotelContractBundle\Tests\Repository;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\HotelContractBundle\Entity\InventorySummary;
use Tourze\HotelContractBundle\Enum\InventorySummaryStatusEnum;
use Tourze\HotelContractBundle\Repository\InventorySummaryRepository;
use Tourze\HotelProfileBundle\Entity\Hotel;
use Tourze\HotelProfileBundle\Entity\RoomType;
use Tourze\HotelProfileBundle\Enum\HotelStatusEnum;
use Tourze\HotelProfileBundle\Enum\RoomTypeStatusEnum;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;

/**
 * @internal
 */
#[CoversClass(InventorySummaryRepository::class)]
#[RunTestsInSeparateProcesses]
final class InventorySummaryRepositoryTest extends AbstractRepositoryTestCase
{
    protected function onSetUp(): void
    {
        // Setup for repository tests
    }

    protected function createNewEntity(): InventorySummary
    {
        // 创建测试所需的关联实体
        $hotel = new Hotel();
        $hotel->setName('测试酒店_' . uniqid());
        $hotel->setStarLevel(5);
        $hotel->setAddress('测试地址');
        $hotel->setPhone('123456789');
        $hotel->setContactPerson('测试联系人');
        $hotel->setStatus(HotelStatusEnum::OPERATING);

        $roomType = new RoomType();
        $roomType->setName('标准间_' . uniqid());
        $roomType->setHotel($hotel);
        $roomType->setDescription('测试房型');
        $roomType->setMaxGuests(2);
        $roomType->setArea(25.0);
        $roomType->setBedType('大床房');
        $roomType->setBreakfastCount(2);
        $roomType->setStatus(RoomTypeStatusEnum::ACTIVE);

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

        // 持久化关联实体
        self::getEntityManager()->persist($hotel);
        self::getEntityManager()->persist($roomType);
        self::getEntityManager()->flush();

        return $summary;
    }

    protected function getRepository(): InventorySummaryRepository
    {
        return self::getService(InventorySummaryRepository::class);
    }

    public function testSaveWithValidSummaryPersistsToDatabase(): void
    {
        $summary = $this->createTestSummary();

        self::getEntityManager()->persist($summary);
        self::getEntityManager()->flush();

        $this->assertNotNull($summary->getId());
        $this->assertEquals(10, $summary->getTotalRooms());
    }

    public function testSaveWithFlushImmediatelyPersists(): void
    {
        $summary = $this->createTestSummary();

        self::getEntityManager()->persist($summary);
        self::getEntityManager()->flush();
        $foundSummary = $this->getRepository()->find($summary->getId());

        $this->assertNotNull($foundSummary);
        $this->assertEquals($summary->getTotalRooms(), $foundSummary->getTotalRooms());
    }

    public function testRemoveWithValidSummaryDeletesFromDatabase(): void
    {
        $summary = $this->createTestSummary();
        self::getEntityManager()->persist($summary);
        self::getEntityManager()->flush();
        $summaryId = $summary->getId();

        self::getEntityManager()->remove($summary);
        self::getEntityManager()->flush();
        $foundSummary = $this->getRepository()->find($summaryId);

        $this->assertNull($foundSummary);
    }

    public function testFindByHotelRoomTypeAndDateWithExistingSummaryReturnsSummary(): void
    {
        $hotel = $this->createTestHotel();
        $roomType = $this->createTestRoomType('Standard Room', $hotel);
        $date = new \DateTimeImmutable('2024-01-15');

        $summary = $this->createTestSummary();
        $summary->setHotel($hotel);
        $summary->setRoomType($roomType);
        $summary->setDate($date);
        self::getEntityManager()->persist($summary);
        self::getEntityManager()->flush();

        $hotelId = $hotel->getId();
        $roomTypeId = $roomType->getId();
        $this->assertNotNull($hotelId);
        $this->assertNotNull($roomTypeId);

        $foundSummary = $this->getRepository()->findByHotelRoomTypeAndDate(
            $hotelId,
            $roomTypeId,
            $date
        );

        $this->assertNotNull($foundSummary);
        $this->assertEquals($summary->getId(), $foundSummary->getId());
        $this->assertNotNull($foundSummary->getHotel());
        $this->assertNotNull($foundSummary->getRoomType());
        $this->assertEquals($hotel->getId(), $foundSummary->getHotel()->getId());
        $this->assertEquals($roomType->getId(), $foundSummary->getRoomType()->getId());
    }

    public function testFindByHotelRoomTypeAndDateWithNonExistentDataReturnsNull(): void
    {
        $foundSummary = $this->getRepository()->findByHotelRoomTypeAndDate(
            999999,
            999999,
            new \DateTimeImmutable('2024-01-15')
        );

        $this->assertNull($foundSummary);
    }

    public function testFindByDateRangeReturnsOrderedByDate(): void
    {
        $summary1 = $this->createTestSummary();
        $summary1->setDate(new \DateTimeImmutable('2024-01-01'));

        $summary2 = $this->createTestSummary();
        $summary2->setDate(new \DateTimeImmutable('2024-01-03'));

        $summary3 = $this->createTestSummary();
        $summary3->setDate(new \DateTimeImmutable('2024-01-02'));

        self::getEntityManager()->persist($summary1);
        self::getEntityManager()->persist($summary2);
        self::getEntityManager()->persist($summary3);
        self::getEntityManager()->flush();

        $results = $this->getRepository()->findByDateRange(
            new \DateTimeImmutable('2024-01-01'),
            new \DateTimeImmutable('2024-01-31')
        );

        $this->assertCount(3, $results);
        $this->assertNotNull($results[0]->getDate());
        $this->assertNotNull($results[1]->getDate());
        $this->assertNotNull($results[2]->getDate());
        $this->assertEquals('2024-01-01', $results[0]->getDate()->format('Y-m-d'));
        $this->assertEquals('2024-01-02', $results[1]->getDate()->format('Y-m-d'));
        $this->assertEquals('2024-01-03', $results[2]->getDate()->format('Y-m-d'));
    }

    public function testFindByDateRangeWithNoMatchingDatesReturnsEmptyArray(): void
    {
        $summary = $this->createTestSummary();
        $summary->setDate(new \DateTimeImmutable('2024-06-01'));
        self::getEntityManager()->persist($summary);
        self::getEntityManager()->flush();

        $results = $this->getRepository()->findByDateRange(
            new \DateTimeImmutable('2024-01-01'),
            new \DateTimeImmutable('2024-01-31')
        );

        $this->assertEmpty($results);
    }

    public function testFindByHotelIdReturnsOnlyHotelSummaries(): void
    {
        $hotel1 = $this->createTestHotel('Hotel 1');
        $hotel2 = $this->createTestHotel('Hotel 2');

        $summary1 = $this->createTestSummary();
        $summary1->setHotel($hotel1);

        $summary2 = $this->createTestSummary();
        $summary2->setHotel($hotel2);

        self::getEntityManager()->persist($summary1);
        self::getEntityManager()->persist($summary2);
        self::getEntityManager()->flush();

        $hotel1Id = $hotel1->getId();
        $this->assertNotNull($hotel1Id);

        $results = $this->getRepository()->findByHotelId($hotel1Id);

        $this->assertCount(1, $results);
        $this->assertNotNull($results[0]->getHotel());
        $this->assertEquals($hotel1->getId(), $results[0]->getHotel()->getId());
    }

    public function testFindByRoomTypeIdReturnsOnlyRoomTypeSummaries(): void
    {
        $roomType1 = $this->createTestRoomType('Standard Room');
        $roomType2 = $this->createTestRoomType('Deluxe Room');

        $summary1 = $this->createTestSummary();
        $summary1->setRoomType($roomType1);

        $summary2 = $this->createTestSummary();
        $summary2->setRoomType($roomType2);

        self::getEntityManager()->persist($summary1);
        self::getEntityManager()->persist($summary2);
        self::getEntityManager()->flush();

        $roomType1Id = $roomType1->getId();
        $this->assertNotNull($roomType1Id);

        $results = $this->getRepository()->findByRoomTypeId($roomType1Id);

        $this->assertCount(1, $results);
        $this->assertNotNull($results[0]->getRoomType());
        $this->assertEquals($roomType1->getId(), $results[0]->getRoomType()->getId());
    }

    public function testFindByDateReturnsAllSummariesForDate(): void
    {
        $targetDate = new \DateTimeImmutable('2024-01-15');
        $otherDate = new \DateTimeImmutable('2024-01-16');

        $summary1 = $this->createTestSummary();
        $summary1->setDate($targetDate);

        $summary2 = $this->createTestSummary();
        $summary2->setDate($otherDate);

        $summary3 = $this->createTestSummary();
        $summary3->setDate($targetDate);

        self::getEntityManager()->persist($summary1);
        self::getEntityManager()->persist($summary2);
        self::getEntityManager()->persist($summary3);
        self::getEntityManager()->flush();

        $results = $this->getRepository()->findByDate($targetDate);

        $this->assertCount(2, $results);
        foreach ($results as $result) {
            $this->assertNotNull($result->getDate());
            $this->assertEquals($targetDate->format('Y-m-d'), $result->getDate()->format('Y-m-d'));
        }
    }

    public function testFindByStatusReturnsOnlyMatchingStatus(): void
    {
        // 清理数据库确保测试隔离
        self::getEntityManager()->createQuery('DELETE FROM ' . InventorySummary::class)->execute();

        $summary1 = $this->createTestSummary();
        $summary1->setStatus(InventorySummaryStatusEnum::NORMAL);

        $summary2 = $this->createTestSummary();
        $summary2->setStatus(InventorySummaryStatusEnum::WARNING);

        $summary3 = $this->createTestSummary();
        $summary3->setStatus(InventorySummaryStatusEnum::NORMAL);

        self::getEntityManager()->persist($summary1);
        self::getEntityManager()->persist($summary2);
        self::getEntityManager()->persist($summary3);
        self::getEntityManager()->flush();

        $results = $this->getRepository()->findByStatus(InventorySummaryStatusEnum::NORMAL);

        $this->assertCount(2, $results);
        foreach ($results as $result) {
            $this->assertEquals(InventorySummaryStatusEnum::NORMAL, $result->getStatus());
        }
    }

    public function testFindWarningInventoryReturnsOnlyWarningStatus(): void
    {
        // 清理数据库确保测试隔离
        self::getEntityManager()->createQuery('DELETE FROM ' . InventorySummary::class)->execute();

        $summary1 = $this->createTestSummary();
        $summary1->setStatus(InventorySummaryStatusEnum::NORMAL);

        $summary2 = $this->createTestSummary();
        $summary2->setStatus(InventorySummaryStatusEnum::WARNING);

        $summary3 = $this->createTestSummary();
        $summary3->setStatus(InventorySummaryStatusEnum::SOLD_OUT);

        self::getEntityManager()->persist($summary1);
        self::getEntityManager()->persist($summary2);
        self::getEntityManager()->persist($summary3);
        self::getEntityManager()->flush();

        $results = $this->getRepository()->findWarningInventory();

        $this->assertCount(1, $results);
        $this->assertEquals(InventorySummaryStatusEnum::WARNING, $results[0]->getStatus());
    }

    public function testFindSoldOutInventoryReturnsOnlySoldOutStatus(): void
    {
        // 清理数据库确保测试隔离
        self::getEntityManager()->createQuery('DELETE FROM ' . InventorySummary::class)->execute();

        $summary1 = $this->createTestSummary();
        $summary1->setStatus(InventorySummaryStatusEnum::NORMAL);

        $summary2 = $this->createTestSummary();
        $summary2->setStatus(InventorySummaryStatusEnum::WARNING);

        $summary3 = $this->createTestSummary();
        $summary3->setStatus(InventorySummaryStatusEnum::SOLD_OUT);

        self::getEntityManager()->persist($summary1);
        self::getEntityManager()->persist($summary2);
        self::getEntityManager()->persist($summary3);
        self::getEntityManager()->flush();

        $results = $this->getRepository()->findSoldOutInventory();

        $this->assertCount(1, $results);
        $this->assertEquals(InventorySummaryStatusEnum::SOLD_OUT, $results[0]->getStatus());
    }

    public function testFindOneByWithOrderByShouldReturnCorrectEntity(): void
    {
        $summary1 = $this->createTestSummary();
        $summary1->setTotalRooms(10);
        $summary1->setAvailableRooms(5);

        $summary2 = $this->createTestSummary();
        $summary2->setTotalRooms(10);
        $summary2->setAvailableRooms(8);

        self::getEntityManager()->persist($summary1);
        self::getEntityManager()->persist($summary2);
        self::getEntityManager()->flush();

        $found = $this->getRepository()->findOneBy(['totalRooms' => 10], ['availableRooms' => 'DESC']);

        $this->assertInstanceOf(InventorySummary::class, $found);
        $this->assertEquals(8, $found->getAvailableRooms());
    }

    public function testFindByWithHotelRelationShouldReturnMatchingEntities(): void
    {
        $hotel1 = $this->createTestHotel('Test Hotel 1');
        $hotel2 = $this->createTestHotel('Test Hotel 2');

        $summary1 = $this->createTestSummary();
        $summary1->setHotel($hotel1);

        $summary2 = $this->createTestSummary();
        $summary2->setHotel($hotel2);

        $summary3 = $this->createTestSummary();
        $summary3->setHotel($hotel1);

        self::getEntityManager()->persist($summary1);
        self::getEntityManager()->persist($summary2);
        self::getEntityManager()->persist($summary3);
        self::getEntityManager()->flush();

        $results = $this->getRepository()->findBy(['hotel' => $hotel1]);

        $this->assertCount(2, $results);
        foreach ($results as $result) {
            $this->assertNotNull($result->getHotel());
            $this->assertEquals($hotel1->getId(), $result->getHotel()->getId());
        }
    }

    public function testCountWithHotelRelationShouldReturnCorrectNumber(): void
    {
        $hotel1 = $this->createTestHotel('Test Hotel 1');
        $hotel2 = $this->createTestHotel('Test Hotel 2');

        $summary1 = $this->createTestSummary();
        $summary1->setHotel($hotel1);

        $summary2 = $this->createTestSummary();
        $summary2->setHotel($hotel2);

        $summary3 = $this->createTestSummary();
        $summary3->setHotel($hotel1);

        self::getEntityManager()->persist($summary1);
        self::getEntityManager()->persist($summary2);
        self::getEntityManager()->persist($summary3);
        self::getEntityManager()->flush();

        $count = $this->getRepository()->count(['hotel' => $hotel1]);

        $this->assertEquals(2, $count);
    }

    public function testFindByWithRoomTypeRelationShouldReturnMatchingEntities(): void
    {
        $roomType1 = $this->createTestRoomType('Standard Room');
        $roomType2 = $this->createTestRoomType('Deluxe Room');

        $summary1 = $this->createTestSummary();
        $summary1->setRoomType($roomType1);

        $summary2 = $this->createTestSummary();
        $summary2->setRoomType($roomType2);

        $summary3 = $this->createTestSummary();
        $summary3->setRoomType($roomType1);

        self::getEntityManager()->persist($summary1);
        self::getEntityManager()->persist($summary2);
        self::getEntityManager()->persist($summary3);
        self::getEntityManager()->flush();

        $results = $this->getRepository()->findBy(['roomType' => $roomType1]);

        $this->assertCount(2, $results);
        foreach ($results as $result) {
            $this->assertNotNull($result->getRoomType());
            $this->assertEquals($roomType1->getId(), $result->getRoomType()->getId());
        }
    }

    public function testFindByWithNullLowestPriceShouldReturnMatchingEntities(): void
    {
        $summary1 = $this->createTestSummary();
        $summary1->setLowestPrice(null);

        $summary2 = $this->createTestSummary();
        $summary2->setLowestPrice('100.00');

        $summary3 = $this->createTestSummary();
        $summary3->setLowestPrice(null);

        self::getEntityManager()->persist($summary1);
        self::getEntityManager()->persist($summary2);
        self::getEntityManager()->persist($summary3);
        self::getEntityManager()->flush();

        $results = $this->getRepository()->findBy(['lowestPrice' => null]);

        $this->assertCount(2, $results);
        foreach ($results as $result) {
            $this->assertNull($result->getLowestPrice());
        }
    }

    public function testCountWithNullLowestPriceShouldReturnCorrectNumber(): void
    {
        $summary1 = $this->createTestSummary();
        $summary1->setLowestPrice(null);

        $summary2 = $this->createTestSummary();
        $summary2->setLowestPrice('100.00');

        $summary3 = $this->createTestSummary();
        $summary3->setLowestPrice(null);

        self::getEntityManager()->persist($summary1);
        self::getEntityManager()->persist($summary2);
        self::getEntityManager()->persist($summary3);
        self::getEntityManager()->flush();

        $count = $this->getRepository()->count(['lowestPrice' => null]);

        $this->assertEquals(2, $count);
    }

    public function testFindByWithNullLowestContractShouldReturnMatchingEntities(): void
    {
        // 清理数据库确保测试隔离
        self::getEntityManager()->createQuery('DELETE FROM ' . InventorySummary::class)->execute();

        $summary1 = $this->createTestSummary();
        $summary1->setLowestContract(null);

        $summary2 = $this->createTestSummary();
        $summary2->setLowestContract(null);

        self::getEntityManager()->persist($summary1);
        self::getEntityManager()->persist($summary2);
        self::getEntityManager()->flush();

        $results = $this->getRepository()->findBy(['lowestContract' => null]);

        $this->assertCount(2, $results);
        foreach ($results as $result) {
            $this->assertNull($result->getLowestContract());
        }
    }

    public function testCountWithNullLowestContractShouldReturnCorrectNumber(): void
    {
        // 清理数据库确保测试隔离
        self::getEntityManager()->createQuery('DELETE FROM ' . InventorySummary::class)->execute();

        $summary1 = $this->createTestSummary();
        $summary1->setLowestContract(null);

        $summary2 = $this->createTestSummary();
        $summary2->setLowestContract(null);

        self::getEntityManager()->persist($summary1);
        self::getEntityManager()->persist($summary2);
        self::getEntityManager()->flush();

        $count = $this->getRepository()->count(['lowestContract' => null]);

        $this->assertEquals(2, $count);
    }

    public function testFindOneByAssociationHotelShouldReturnMatchingEntity(): void
    {
        $hotel = $this->createTestHotel('Target Hotel');
        $summary = $this->createTestSummary();
        $summary->setHotel($hotel);
        self::getEntityManager()->persist($summary);
        self::getEntityManager()->flush();

        $found = $this->getRepository()->findOneBy(['hotel' => $hotel]);

        $this->assertInstanceOf(InventorySummary::class, $found);
        $this->assertNotNull($found->getHotel());
        $this->assertEquals($hotel->getId(), $found->getHotel()->getId());
    }

    public function testCountByAssociationHotelShouldReturnCorrectNumber(): void
    {
        $hotel1 = $this->createTestHotel('Hotel 1');
        $hotel2 = $this->createTestHotel('Hotel 2');

        $summary1 = $this->createTestSummary();
        $summary1->setHotel($hotel1);
        $summary2 = $this->createTestSummary();
        $summary2->setHotel($hotel1);
        $summary3 = $this->createTestSummary();
        $summary3->setHotel($hotel2);

        self::getEntityManager()->persist($summary1);
        self::getEntityManager()->persist($summary2);
        self::getEntityManager()->persist($summary3);
        self::getEntityManager()->flush();

        $count = $this->getRepository()->count(['hotel' => $hotel1]);

        $this->assertEquals(2, $count);
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

        self::getEntityManager()->persist($hotel);
        self::getEntityManager()->flush();

        return $hotel;
    }

    private function createTestRoomType(string $name = 'Standard Room', ?Hotel $hotel = null): RoomType
    {
        if (null === $hotel) {
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

        self::getEntityManager()->persist($roomType);
        self::getEntityManager()->flush();

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
