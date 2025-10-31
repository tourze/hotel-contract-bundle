<?php

namespace Tourze\HotelContractBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\DependencyInjection\Attribute\When;
use Tourze\HotelContractBundle\Entity\HotelContract;
use Tourze\HotelContractBundle\Enum\ContractStatusEnum;
use Tourze\HotelContractBundle\Enum\ContractTypeEnum;
use Tourze\HotelProfileBundle\DataFixtures\HotelFixtures;
use Tourze\HotelProfileBundle\Entity\Hotel;

#[When(env: 'test')]
#[When(env: 'dev')]
class HotelContractFixtures extends Fixture implements FixtureGroupInterface, DependentFixtureInterface
{
    public const CONTRACT_ACTIVE = 'contract-active';
    public const CONTRACT_PENDING = 'contract-pending';
    public const CONTRACT_TERMINATED = 'contract-terminated';

    public function load(ObjectManager $manager): void
    {
        $luxuryHotel = $this->getReference(HotelFixtures::LUXURY_HOTEL_REFERENCE, Hotel::class);
        $businessHotel = $this->getReference(HotelFixtures::BUSINESS_HOTEL_REFERENCE, Hotel::class);
        $budgetHotel = $this->getReference(HotelFixtures::BUDGET_HOTEL_REFERENCE, Hotel::class);

        $activeContract = new HotelContract();
        $activeContract->setContractNo('CONTRACT-2024-001');
        $activeContract->setHotel($luxuryHotel);
        $activeContract->setContractType(ContractTypeEnum::FIXED_PRICE);
        $activeContract->setStartDate(new \DateTimeImmutable('2024-01-01'));
        $activeContract->setEndDate(new \DateTimeImmutable('2024-12-31'));
        $activeContract->setTotalRooms(100);
        $activeContract->setTotalDays(365);
        $activeContract->setTotalAmount('365000.00');
        $activeContract->setAttachmentUrl('https://cdn.unsplash.com/contracts/2024-001.pdf');
        $activeContract->setStatus(ContractStatusEnum::ACTIVE);
        $activeContract->setPriority(1);

        $manager->persist($activeContract);

        $pendingContract = new HotelContract();
        $pendingContract->setContractNo('CONTRACT-2024-002');
        $pendingContract->setHotel($businessHotel);
        $pendingContract->setContractType(ContractTypeEnum::DYNAMIC_PRICE);
        $pendingContract->setStartDate(new \DateTimeImmutable('2024-06-01'));
        $pendingContract->setEndDate(new \DateTimeImmutable('2024-11-30'));
        $pendingContract->setTotalRooms(50);
        $pendingContract->setTotalDays(183);
        $pendingContract->setTotalAmount('91500.00');
        $pendingContract->setStatus(ContractStatusEnum::PENDING);
        $pendingContract->setPriority(2);

        $manager->persist($pendingContract);

        $terminatedContract = new HotelContract();
        $terminatedContract->setContractNo('CONTRACT-2023-999');
        $terminatedContract->setHotel($budgetHotel);
        $terminatedContract->setContractType(ContractTypeEnum::FIXED_PRICE);
        $terminatedContract->setStartDate(new \DateTimeImmutable('2023-01-01'));
        $terminatedContract->setEndDate(new \DateTimeImmutable('2023-12-31'));
        $terminatedContract->setTotalRooms(75);
        $terminatedContract->setTotalDays(365);
        $terminatedContract->setTotalAmount('273750.00');
        $terminatedContract->setStatus(ContractStatusEnum::TERMINATED);
        $terminatedContract->setTerminationReason('客户要求提前终止合同');
        $terminatedContract->setPriority(3);

        $manager->persist($terminatedContract);

        $manager->flush();

        $this->addReference(self::CONTRACT_ACTIVE, $activeContract);
        $this->addReference(self::CONTRACT_PENDING, $pendingContract);
        $this->addReference(self::CONTRACT_TERMINATED, $terminatedContract);
    }

    public static function getGroups(): array
    {
        return ['hotel-contract', 'test'];
    }

    public function getDependencies(): array
    {
        return [
            HotelFixtures::class,
        ];
    }
}
