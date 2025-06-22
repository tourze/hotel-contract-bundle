<?php

namespace Tourze\HotelContractBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Tourze\HotelContractBundle\Entity\HotelContract;
use Tourze\HotelContractBundle\Enum\ContractStatusEnum;
use Tourze\HotelContractBundle\Enum\ContractTypeEnum;
use Tourze\HotelProfileBundle\DataFixtures\HotelFixtures;
use Tourze\HotelProfileBundle\Entity\Hotel;

/**
 * 酒店合同数据填充
 * 为酒店创建测试合同数据，依赖于HotelFixtures
 */
class HotelContractFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    // 定义引用常量，以便其他Fixture使用
    public const CONTRACT_REFERENCE_PREFIX = 'contract-';

    public function load(ObjectManager $manager): void
    {
        // 使用随机ID避免合同编号冲突
        $randomPrefix = substr(md5(uniqid()), 0, 6);

        // 为每个五星级酒店创建多个合同，测试优先级
        $this->createContractsForHotel(
            $manager,
            $this->getReference(HotelFixtures::FIVE_STAR_HOTEL_REFERENCE, Hotel::class),
            [
                [
                    'contractType' => ContractTypeEnum::FIXED_PRICE,
                    'startDate' => new \DateTimeImmutable('first day of this month'),
                    'endDate' => (new \DateTimeImmutable('first day of next month'))->modify('+2 months'),
                    'totalRooms' => 17,
                    'totalDays' => 7,
                    'totalAmount' => '500000.00',
                    'attachmentUrl' => 'luxury-hotel-contract-1.pdf',
                    'status' => ContractStatusEnum::ACTIVE,
                    'priority' => 1, // 最高优先级
                ],
                [
                    'contractType' => ContractTypeEnum::DYNAMIC_PRICE,
                    'startDate' => new \DateTimeImmutable('first day of next month'),
                    'endDate' => (new \DateTimeImmutable('first day of next month'))->modify('+3 months'),
                    'totalRooms' => 20,
                    'totalDays' => 7,
                    'totalAmount' => '300000.00',
                    'attachmentUrl' => 'luxury-hotel-contract-2.pdf',
                    'status' => ContractStatusEnum::PENDING,
                    'priority' => 2,
                ],
                [
                    'contractType' => ContractTypeEnum::FIXED_PRICE,
                    'startDate' => (new \DateTimeImmutable())->modify('-2 months'),
                    'endDate' => (new \DateTimeImmutable()),
                    'totalRooms' => 20,
                    'totalDays' => 7,
                    'totalAmount' => '350000.00',
                    'attachmentUrl' => 'luxury-hotel-contract-3.pdf',
                    'status' => ContractStatusEnum::TERMINATED,
                    'priority' => 3,
                    'terminationReason' => '合同到期自动终止',
                ],
            ],
            1,
            $randomPrefix . 'A'
        );

        // 为四星级酒店创建合同
        $this->createContractsForHotel(
            $manager,
            $this->getReference(HotelFixtures::FOUR_STAR_HOTEL_REFERENCE, Hotel::class),
            [
                [
                    'contractType' => ContractTypeEnum::DYNAMIC_PRICE,
                    'startDate' => new \DateTimeImmutable('first day of this month'),
                    'endDate' => (new \DateTimeImmutable('first day of next month'))->modify('+5 months'),
                    'totalRooms' => 18,
                    'totalDays' => 7,
                    'totalAmount' => '420000.00',
                    'attachmentUrl' => 'business-hotel-contract-1.pdf',
                    'status' => ContractStatusEnum::ACTIVE,
                    'priority' => 1,
                ],
                [
                    'contractType' => ContractTypeEnum::FIXED_PRICE,
                    'startDate' => (new \DateTimeImmutable())->modify('-1 months'),
                    'endDate' => (new \DateTimeImmutable())->modify('+1 months'),
                    'totalRooms' => 20,
                    'totalDays' => 7,
                    'totalAmount' => '150000.00',
                    'attachmentUrl' => 'business-hotel-contract-2.pdf',
                    'status' => ContractStatusEnum::ACTIVE,
                    'priority' => 2,
                ],
            ],
            4,
            $randomPrefix . 'B'
        );

        // 为三星级酒店创建合同
        $this->createContractsForHotel(
            $manager,
            $this->getReference(HotelFixtures::THREE_STAR_HOTEL_REFERENCE, Hotel::class),
            [
                [
                    'contractType' => ContractTypeEnum::FIXED_PRICE,
                    'startDate' => new \DateTimeImmutable('first day of this month'),
                    'endDate' => (new \DateTimeImmutable('first day of next month'))->modify('+6 months'),
                    'totalRooms' => 25,
                    'totalDays' => 7,
                    'totalAmount' => '250000.00',
                    'attachmentUrl' => 'economy-hotel-contract-1.pdf',
                    'status' => ContractStatusEnum::ACTIVE,
                    'priority' => 1,
                ],
            ],
            7,
            $randomPrefix . 'C'
        );

        // 为其他几个酒店创建单个合同
        for ($i = 2; $i <= 10; $i++) {
            // 跳过已经创建过合同的酒店
            if ($i == 4 || $i == 7) {
                continue;
            }

            $hotel = $this->getReference(HotelFixtures::HOTEL_REFERENCE_PREFIX . $i, Hotel::class);
            $contractType = $i % 2 == 0 ? ContractTypeEnum::FIXED_PRICE : ContractTypeEnum::DYNAMIC_PRICE;
            $startDate = new \DateTimeImmutable('first day of this month');
            $endDate = (new \DateTimeImmutable('first day of next month'))->modify('+' . rand(1, 6) . ' months');
            $totalDays = 7; // 固定为7天
            $totalRooms = rand(10, 25); // 房间数在10-25之间

            $contract = new HotelContract();
            $contract->setHotel($hotel);
            $contract->setContractNo('HT' . $randomPrefix . date('md') . str_pad((string)$i, 3, '0', STR_PAD_LEFT));
            $contract->setContractType($contractType);
            $contract->setStartDate($startDate);
            $contract->setEndDate($endDate);
            $contract->setTotalRooms($totalRooms);
            $contract->setTotalDays($totalDays);
            $contract->setTotalAmount((string)(rand(1000, 3000) * $totalDays * $totalRooms / 100));
            $contract->setAttachmentUrl('contract-' . $i . '.pdf');
            $contract->setStatus(ContractStatusEnum::ACTIVE);
            $contract->setPriority(1);

            $manager->persist($contract);
            // 使用不同的命名约定，避免冲突
            $this->addReference(self::CONTRACT_REFERENCE_PREFIX . 'other-' . $i, $contract);
        }

        $manager->flush();
    }

    /**
     * 为指定酒店创建多个合同
     */
    private function createContractsForHotel(
        ObjectManager $manager,
        Hotel $hotel,
        array $contractsData,
        int $startIndex,
        string $contractNoPrefix = ''
    ): void {
        foreach ($contractsData as $index => $contractData) {
            $contract = new HotelContract();
            $contract->setHotel($hotel);
            $contract->setContractNo('HT' . $contractNoPrefix . date('md') . str_pad((string)($startIndex + $index), 3, '0', STR_PAD_LEFT));
            $contract->setContractType($contractData['contractType']);
            $contract->setStartDate($contractData['startDate']);
            $contract->setEndDate($contractData['endDate']);
            $contract->setTotalRooms($contractData['totalRooms']);
            $contract->setTotalDays($contractData['totalDays']);
            $contract->setTotalAmount($contractData['totalAmount']);
            $contract->setAttachmentUrl($contractData['attachmentUrl']);
            $contract->setStatus($contractData['status']);
            $contract->setPriority($contractData['priority']);

            if (isset($contractData['terminationReason']) && $contractData['status'] === ContractStatusEnum::TERMINATED) {
                $contract->setTerminationReason($contractData['terminationReason']);
            }

            $manager->persist($contract);
            $this->addReference(self::CONTRACT_REFERENCE_PREFIX . ($startIndex + $index), $contract);
        }
    }

    public function getDependencies(): array
    {
        return [
            HotelFixtures::class,
        ];
    }

    public static function getGroups(): array
    {
        return ['dev', 'test'];
    }
}
