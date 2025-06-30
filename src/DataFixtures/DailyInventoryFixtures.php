<?php

namespace Tourze\HotelContractBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Tourze\HotelContractBundle\Entity\DailyInventory;
use Tourze\HotelContractBundle\Entity\HotelContract;
use Tourze\HotelContractBundle\Enum\DailyInventoryStatusEnum;
use Tourze\HotelProfileBundle\DataFixtures\RoomTypeFixtures;
use Tourze\HotelProfileBundle\Entity\RoomType;

/**
 * 日库存数据填充
 * 为合同创建库存数据
 */
class DailyInventoryFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    public function load(ObjectManager $manager): void
    {
        // 获取所有合同
        $contracts = [];
        for ($i = 1; $i <= 10; $i++) {
            try {
                $contract = $this->getReference(HotelContractFixtures::CONTRACT_REFERENCE_PREFIX . $i, HotelContract::class);
                $contracts[] = $contract;
            } catch (\Throwable $e) {
                // 忽略找不到的引用
                continue;
            }
        }

        // 获取合同关联酒店的所有房型
        foreach ($contracts as $contract) {
            $hotel = $contract->getHotel();

            // 使用引用获取房型，而不是查询数据库
            $roomTypes = [];
            for ($j = 1; $j <= 5; $j++) { // 假设每个酒店最多5个房型
                try {
                    $roomType = $this->getReference(RoomTypeFixtures::ROOM_TYPE_REFERENCE_PREFIX . $hotel->getId() . '_' . $j, RoomType::class);
                    $roomTypes[] = $roomType;
                } catch (\Throwable $e) {
                    // 如果找不到引用就跳过
                    break;
                }
            }

            if (empty($roomTypes)) {
                continue;
            }

            // 计算每个房型的库存数量
            $totalRooms = $contract->getTotalRooms();
            $roomCount = count($roomTypes);
            $roomsPerType = (int)floor($totalRooms / $roomCount);
            $extraRooms = $totalRooms % $roomCount;

            // 为每个房型创建库存
            $typeIndex = 0;
            foreach ($roomTypes as $roomType) {
                // 计算此房型的房间数
                $typeTotalRooms = $roomsPerType;
                if ($typeIndex < $extraRooms) {
                    $typeTotalRooms += 1;
                }
                $typeIndex++;

                if ($typeTotalRooms <= 0) {
                    continue;
                }

                // 获取合同的起始日期
                $startDate = new \DateTimeImmutable($contract->getStartDate()->format('Y-m-d'));
                $endDate = new \DateTimeImmutable($contract->getEndDate()->format('Y-m-d'));

                // 只生成7天的库存用于测试
                $endDate = $startDate->modify('+6 days');

                // 为每一天创建库存
                $currentDate = clone $startDate;
                while ($currentDate <= $endDate) {
                    $dateFormatted = $currentDate->format('Y-m-d');

                    // 为每个房间创建库存
                    for ($i = 1; $i <= $typeTotalRooms; $i++) {
                        // 生成唯一code
                        $code = sprintf(
                            'INV-%s-%s-%s-%d',
                            $contract->getContractNo(),
                            $roomType->getId(),
                            $dateFormatted,
                            $i
                        );

                        // 创建库存记录
                        $inventory = new DailyInventory();
                        $inventory->setRoomType($roomType);
                        $inventory->setHotel($hotel);
                        $inventory->setDate(clone $currentDate);
                        $inventory->setContract($contract);
                        $inventory->setCode($code);
                        $inventory->setIsReserved(false);
                        $inventory->setStatus(DailyInventoryStatusEnum::AVAILABLE);

                        // 设置随机价格
                        $baseCost = rand(200, 600);
                        $basePrice = $baseCost * (1 + rand(10, 30) / 100);
                        $inventory->setCostPrice((string)$baseCost);
                        $inventory->setSellingPrice((string)$basePrice);

                        $manager->persist($inventory);
                    }

                    // 移动到下一天
                    $currentDate = $currentDate->modify('+1 day');
                }
            }
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            HotelContractFixtures::class,
            RoomTypeFixtures::class,
        ];
    }

    public static function getGroups(): array
    {
        return ['dev', 'test'];
    }
}
