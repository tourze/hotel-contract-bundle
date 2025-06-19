<?php

namespace Tourze\HotelContractBundle\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Tourze\HotelContractBundle\Entity\DailyInventory;
use Tourze\HotelContractBundle\Entity\HotelContract;
use Tourze\HotelContractBundle\Entity\RoomType;
use Tourze\HotelContractBundle\Enum\ContractStatusEnum;
use Tourze\HotelContractBundle\Enum\DailyInventoryStatusEnum;

/**
 * 为所有生效合同生成库存处理控制器
 */
#[Route('/admin/room-type-inventory/generate-all-contract-process', name: 'admin_room_type_inventory_generate_all_contract_process', methods: ['POST'])]
class GenerateAllContractInventoryProcessController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        // 获取参数
        $days = $request->request->getInt('days', 30);
        $startDate = new \DateTimeImmutable();
        $endDate = (new \DateTimeImmutable())->modify('+' . $days . ' days');

        // 开始事务
        $this->entityManager->getConnection()->beginTransaction();
        try {
            // 获取所有生效的合同
            $activeContracts = $this->entityManager->getRepository(HotelContract::class)
                ->createQueryBuilder('c')
                ->where('c.status = :status')
                ->andWhere('c.startDate <= :endDate')
                ->andWhere('c.endDate >= :startDate')
                ->setParameter('status', ContractStatusEnum::ACTIVE)
                ->setParameter('startDate', $startDate)
                ->setParameter('endDate', $endDate)
                ->getQuery()
                ->getResult();

            if (empty($activeContracts)) {
                $this->addFlash('warning', '未找到符合条件的生效合同');
                return $this->redirectToRoute('admin_room_type_inventory_generate_all_contract');
            }

            $totalGenerated = 0;
            $totalSkipped = 0;
            $totalFailed = 0;
            $processedContracts = 0;

            foreach ($activeContracts as $contract) {
                $processedContracts++;
                
                // 获取合同关联的房型
                /** @var RoomType[] $roomTypes */
                $roomTypes = $this->entityManager->getRepository(RoomType::class)
                    ->createQueryBuilder('rt')
                    ->join('rt.hotelContracts', 'c')
                    ->where('c.id = :contractId')
                    ->setParameter('contractId', $contract->getId())
                    ->getQuery()
                    ->getResult();

                if (empty($roomTypes)) {
                    continue;
                }

                // 为每个房型生成库存记录
                foreach ($roomTypes as $roomType) {
                    $currentDate = clone $startDate;
                    
                    while ($currentDate <= $endDate) {
                        // 检查日期是否在合同有效期内
                        if ($currentDate < $contract->getStartDate() || $currentDate > $contract->getEndDate()) {
                            $currentDate = $currentDate->modify('+1 day');
                            continue;
                        }

                        // 检查是否已存在库存记录
                        $existingInventory = $this->entityManager->getRepository(DailyInventory::class)
                            ->findOneBy([
                                'roomType' => $roomType,
                                'date' => $currentDate,
                                'contract' => $contract,
                            ]);

                        if ($existingInventory) {
                            $totalSkipped++;
                            $currentDate = $currentDate->modify('+1 day');
                            continue;
                        }

                        try {
                            // 创建新的库存记录
                            $inventory = new DailyInventory();
                            $inventory->setRoomType($roomType);
                            $inventory->setDate($currentDate);
                            $inventory->setContract($contract);
                            $inventory->setTotalRooms($roomType->getDefaultInventory() ?? 10);
                            $inventory->setAvailableRooms($roomType->getDefaultInventory() ?? 10);
                            $inventory->setReservedRooms(0);
                            $inventory->setSoldRooms(0);
                            $inventory->setStatus(DailyInventoryStatusEnum::AVAILABLE);
                            $inventory->setBasePrice($roomType->getDefaultPrice() ?? '0');
                            $inventory->setSellPrice($roomType->getDefaultPrice() ?? '0');

                            $this->entityManager->persist($inventory);
                            $totalGenerated++;
                        } catch (\Exception $e) {
                            $totalFailed++;
                        }

                        $currentDate = $currentDate->modify('+1 day');
                    }
                }
            }

            $this->entityManager->flush();
            $this->entityManager->getConnection()->commit();

            $this->addFlash('success', sprintf(
                '成功处理 %d 个合同，生成 %d 条库存记录，跳过 %d 条已存在记录，失败 %d 条',
                $processedContracts,
                $totalGenerated,
                $totalSkipped,
                $totalFailed
            ));

        } catch (\Exception $e) {
            $this->entityManager->getConnection()->rollBack();
            $this->addFlash('error', '生成库存时发生错误: ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin');
    }
}