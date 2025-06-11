<?php

namespace Tourze\HotelContractBundle\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\PercentField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Tourze\HotelContractBundle\Entity\DailyInventory;
use Tourze\HotelContractBundle\Entity\HotelContract;
use Tourze\HotelContractBundle\Enum\ContractStatusEnum;
use Tourze\HotelContractBundle\Enum\DailyInventoryStatusEnum;
use Tourze\HotelContractBundle\Service\RoomTypeInventoryService;
use Tourze\HotelProfileBundle\Entity\RoomType;

/**
 * 房型库存管理控制器
 */
class RoomTypeInventoryCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly LoggerInterface $logger,
        private readonly RoomTypeInventoryService $roomTypeInventoryService,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return DailyInventory::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('房型库存')
            ->setEntityLabelInPlural('房型库存管理')
            ->setSearchFields(['roomType.name', 'hotel.name', 'contract.contractNo', 'code'])
            ->setPaginatorPageSize(20)
            ->setDefaultSort(['date' => 'DESC'])
            ->showEntityActionsInlined();
    }

    public function configureActions(Actions $actions): Actions
    {
        $batchCreateInventory = Action::new('batchCreateInventory', '批量创建库存')
            ->linkToCrudAction('batchCreateInventoryForm')
            ->createAsGlobalAction()
            ->setIcon('fa fa-plus-circle');

        $generateAllContractInventory = Action::new('generateAllContractInventory', '一键生成所有合同库存')
            ->linkToCrudAction('generateAllContractInventoryForm')
            ->createAsGlobalAction()
            ->setIcon('fa fa-magic');

        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $batchCreateInventory)
            ->add(Crud::PAGE_INDEX, $generateAllContractInventory)
            ->update(Crud::PAGE_INDEX, Action::NEW, function (Action $action) {
                return $action->setLabel('添加库存');
            });
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('roomType', '房型'))
            ->add(EntityFilter::new('hotel', '酒店'))
            ->add(EntityFilter::new('contract', '合同'))
            ->add(DateTimeFilter::new('date', '日期'))
            ->add('isReserved')
            ->add('status');
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm()->setMaxLength(9999);

        yield AssociationField::new('roomType', '房型')
            ->setRequired(true)
            ->setFormTypeOption('query_builder', function ($repository) {
                return $repository->createQueryBuilder('rt')
                    ->join('rt.hotel', 'h')
                    ->addSelect('h')
                    ->orderBy('h.name', 'ASC')
                    ->addOrderBy('rt.name', 'ASC');
            })
            ->setFormTypeOption('group_by', 'hotel.name')
            ->setHelp('选择房型后，酒店将自动设置');

        yield AssociationField::new('hotel', '酒店')
            ->setRequired(true)
            ->hideOnForm();

        yield DateField::new('date', '日期')
            ->setRequired(true);

        yield TextField::new('code', '库存编码')
            ->hideOnForm();

        yield AssociationField::new('contract', '合同')
            ->setRequired(true)
            ->setFormTypeOption('query_builder', function ($repository) {
                return $repository->createQueryBuilder('c')
                    ->orderBy('c.contractNo', 'ASC');
            });

        // 价格相关字段 - 在列表页面也显示
        yield MoneyField::new('costPrice', '成本价')
            ->setCurrency('CNY')
            ->setStoredAsCents(false);

        yield MoneyField::new('sellingPrice', '销售价')
            ->setCurrency('CNY')
            ->setStoredAsCents(false);

        if (Crud::PAGE_INDEX !== $pageName) {
            yield TextField::new('priceAdjustReason', '价格调整原因');

            // 在详情页面显示利润率
            if (Crud::PAGE_DETAIL === $pageName) {
                yield PercentField::new('profitRate', '利润率')
                    ->setNumDecimals(2)
                    ->setHelp('基于成本价和销售价自动计算');
            }
        } else {
            // 在列表页面显示利润率
            yield PercentField::new('profitRate', '利润率')
                ->setNumDecimals(2)
                ->setFormattedValue('html')
                ->formatValue(function ($value, $entity) {
                    if ($entity instanceof DailyInventory) {
                        $costPrice = (float)$entity->getCostPrice();
                        $sellingPrice = (float)$entity->getSellingPrice();

                        if ($costPrice > 0) {
                            $profit = $sellingPrice - $costPrice;
                            $profitRate = ($profit / $costPrice) * 100;

                            $color = 'secondary';
                            if ($profitRate >= 30) {
                                $color = 'success';
                            } elseif ($profitRate >= 15) {
                                $color = 'info';
                            } elseif ($profitRate < 0) {
                                $color = 'danger';
                            }

                            return sprintf('<span class="badge bg-%s">%.2f%%</span>', $color, $profitRate);
                        }
                    }
                    return '<span class="badge bg-secondary">0.00%</span>';
                });
        }

        // 状态相关字段
        yield BooleanField::new('isReserved', '预留')
            ->renderAsSwitch(Crud::PAGE_EDIT === $pageName);

        yield ChoiceField::new('status', '状态')
            ->setChoices([
                '可用' => DailyInventoryStatusEnum::AVAILABLE,
                '已预订' => DailyInventoryStatusEnum::RESERVED,
                '已售出' => DailyInventoryStatusEnum::SOLD,
                '已取消' => DailyInventoryStatusEnum::CANCELLED,
                '已退款' => DailyInventoryStatusEnum::REFUNDED,
            ])
            ->renderAsBadges([
                DailyInventoryStatusEnum::AVAILABLE->value => 'success',
                DailyInventoryStatusEnum::RESERVED->value => 'warning',
                DailyInventoryStatusEnum::SOLD->value => 'info',
                DailyInventoryStatusEnum::CANCELLED->value => 'secondary',
                DailyInventoryStatusEnum::REFUNDED->value => 'danger',
            ]);

        yield DateTimeField::new('createTime', '创建时间')
            ->hideOnForm();

        yield DateTimeField::new('updateTime', '更新时间')
            ->hideOnForm()
            ->hideOnIndex();
    }

    public function createIndexQueryBuilder(
        SearchDto        $searchDto,
        EntityDto        $entityDto,
        FieldCollection  $fields,
        FilterCollection $filters
    ): QueryBuilder
    {
        $queryBuilder = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);
        $rootAlias = $queryBuilder->getRootAliases()[0];

        // 默认加载关联
        $queryBuilder->leftJoin($rootAlias . '.roomType', 'roomType')
            ->leftJoin($rootAlias . '.hotel', 'hotel')
            ->leftJoin($rootAlias . '.contract', 'contract')
            ->addSelect('roomType', 'hotel', 'contract');

        return $queryBuilder;
    }

    /**
     * 批量创建库存表单
     */
    #[Route('/admin/room-type-inventory/batch-create', name: 'admin_room_type_inventory_batch_create')]
    public function batchCreateInventoryForm(Request $request, EntityManagerInterface $em): Response
    {
        // 获取所有房型
        $roomTypes = $em->getRepository(RoomType::class)
            ->createQueryBuilder('rt')
            ->leftJoin('rt.hotel', 'h')
            ->addSelect('h')
            ->orderBy('h.name', 'ASC')
            ->addOrderBy('rt.name', 'ASC')
            ->getQuery()
            ->getResult();

        // 获取所有合同
        $contracts = $em->getRepository(HotelContract::class)
            ->createQueryBuilder('c')
            ->orderBy('c.contractNo', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->render('@HotelContract/admin/room_type_inventory/batch_create.html.twig', [
            'roomTypes' => $roomTypes,
            'contracts' => $contracts,
        ]);
    }

    /**
     * 批量创建库存处理
     */
    #[Route('/admin/room-type-inventory/batch-create-process', name: 'admin_room_type_inventory_batch_create_process', methods: ['POST'])]
    public function batchCreateInventoryProcess(Request $request, EntityManagerInterface $em): Response
    {
        $roomTypeId = $request->request->getInt('roomType');
        $contractId = $request->request->getInt('contract');
        $count = $request->request->getInt('count', 1);
        $startDate = $request->request->get('startDate');
        $endDate = $request->request->get('endDate');
        $costPrice = (float) $request->request->get('costPrice', 0);
        $sellingPrice = (float) $request->request->get('sellingPrice', 0);

        if (!$roomTypeId || !$contractId || $count < 1 || !$startDate || !$endDate) {
            $this->addFlash('danger', '请填写所有必填字段');
            return $this->redirectToRoute('admin_room_type_inventory_batch_create');
        }

        try {
            $startDateTime = new \DateTime($startDate);
            $endDateTime = new \DateTime($endDate);

            $result = $this->roomTypeInventoryService->oneClickGenerateRoomTypeInventory(
                $contractId,
                $roomTypeId,
                $count,
                $startDateTime,
                $endDateTime,
                $costPrice,
                $sellingPrice
            );

            if ($result['success']) {
                $this->addFlash('success', $result['message']);
            } else {
                $this->addFlash('danger', $result['message']);
            }
        } catch  (\Throwable $e) {
            $this->addFlash('danger', '创建库存失败: ' . $e->getMessage());
        }

        return $this->redirect($this->adminUrlGenerator
            ->setController(self::class)
            ->setAction(Action::INDEX)
            ->generateUrl());
    }

    /**
     * 为所有生效合同生成库存表单
     */
    #[Route('/admin/room-type-inventory/generate-all-contract', name: 'admin_room_type_inventory_generate_all_contract')]
    public function generateAllContractInventoryForm(Request $request, EntityManagerInterface $em): Response
    {
        return $this->render('@HotelContract/admin/room_type_inventory/generate_all_contract.html.twig', [
            'days' => 30, // 默认生成30天的库存
        ]);
    }

    /**
     * 为所有生效合同生成库存处理
     */
    #[Route('/admin/room-type-inventory/generate-all-contract-process', name: 'admin_room_type_inventory_generate_all_contract_process', methods: ['POST'])]
    public function generateAllContractInventoryProcess(Request $request, EntityManagerInterface $em): Response
    {
        // 获取参数
        $days = $request->request->getInt('days', 30);
        $startDate = new \DateTime();
        $endDate = (new \DateTime())->modify('+' . $days . ' days');

        // 开始事务
        $em->getConnection()->beginTransaction();
        try {
            // 获取所有生效的合同
            $activeContracts = $em->getRepository(HotelContract::class)
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

            // 记录处理开始
            $this->logger->info('开始批量生成库存', [
                'contractCount' => count($activeContracts),
                'startDate' => $startDate->format('Y-m-d'),
                'endDate' => $endDate->format('Y-m-d'),
                'days' => $days
            ]);

            // 处理每个合同
            foreach ($activeContracts as $contract) {
                $processedContracts++;

                // 获取合同关联的酒店所有房型
                $roomTypes = $em->getRepository(RoomType::class)
                    ->createQueryBuilder('rt')
                    ->where('rt.hotel = :hotel')
                    ->setParameter('hotel', $contract->getHotel())
                    ->getQuery()
                    ->getResult();

                // 计算每个房型应分配的库存数量
                $totalRoomTypes = count($roomTypes);
                if ($totalRoomTypes > 0) {
                    // 从合同获取总房间数
                    $contractTotalRooms = max(1, $contract->getTotalRooms());

                    // 平均分配给每个房型，确保总数不超过合同规定数量
                    $roomsPerType = (int)floor($contractTotalRooms / $totalRoomTypes);
                    $extraRooms = $contractTotalRooms % $totalRoomTypes;

                    $this->logger->info('合同库存分配', [
                        'contractNo' => $contract->getContractNo(),
                        'totalRooms' => $contractTotalRooms,
                        'roomTypes' => $totalRoomTypes,
                        'roomsPerType' => $roomsPerType
                    ]);

                    $typeIndex = 0;
                    foreach ($roomTypes as $roomType) {
                        // 计算此房型应分配的房间数
                        $targetRooms = $roomsPerType;
                        if ($typeIndex < $extraRooms) {
                            $targetRooms += 1; // 将余数分配给前几个房型
                        }
                        $typeIndex++;

                        // 对每个房型生成库存
                        try {
                            // 记录处理情况
                            $this->logger->info('处理合同房型库存', [
                                'contractNo' => $contract->getContractNo(),
                                'roomType' => $roomType->getName(),
                                'targetRooms' => $targetRooms
                            ]);

                            // 处理每一天的库存
                            $currentDate = clone $startDate;
                            $skippedDaysCount = 0;
                            $generatedDaysCount = 0;
                            $generatedInventoryCount = 0;

                            while ($currentDate <= $endDate) {
                                // 生成当天的库存
                                $dateFormatted = $currentDate->format('Y-m-d');

                                // 需要创建的库存数量
                                $toCreateCount = $targetRooms;
                                $createdToday = 0;

                                for ($i = 1; $i <= $toCreateCount; $i++) {
                                    // 生成唯一的code
                                    $code = sprintf(
                                        'INV-%s-%s-%s-%d',
                                        $contract->getContractNo(),
                                        $roomType->getId(),
                                        $dateFormatted,
                                        $i
                                    );

                                    // 检查是否已存在
                                    $existingInventory = $em->getRepository(DailyInventory::class)
                                        ->findOneBy(['code' => $code]);

                                    if (!$existingInventory) {
                                        // 创建新的库存
                                        $inventory = new DailyInventory();
                                        $inventory->setRoomType($roomType);
                                        $inventory->setHotel($roomType->getHotel());
                                        $inventory->setContract($contract);
                                        $inventory->setDate(clone $currentDate);
                                        $inventory->setStatus(DailyInventoryStatusEnum::AVAILABLE);
                                        $inventory->setIsReserved(false);

                                        // 设置唯一code
                                        $inventory->setCode($code);

                                        $em->persist($inventory);
                                        $generatedInventoryCount++;
                                        $createdToday++;

                                        // 每50个库存刷新一次，避免内存问题
                                        if ($generatedInventoryCount % 50 === 0) {
                                            $em->flush();

                                            // 提交当前事务并开启新事务，防止单个事务过大
                                            $em->getConnection()->commit();
                                            $em->getConnection()->beginTransaction();
                                        }
                                    } else {
                                        $totalSkipped++;
                                    }
                                }

                                // 更新统计
                                if ($createdToday > 0) {
                                    $generatedDaysCount++;
                                    $totalGenerated += $createdToday;
                                } else {
                                    $skippedDaysCount++;
                                }

                                // 移动到下一天
                                $currentDate->modify('+1 day');
                            }

                            // 最后刷新一次
                            $em->flush();

                            // 日志消息
                            if ($generatedDaysCount > 0) {
                                $this->addFlash('info', sprintf(
                                    '合同 %s 的房型 %s: 处理了%d天，生成了%d个库存，跳过了%d天',
                                    $contract->getContractNo(),
                                    $roomType->getName(),
                                    $generatedDaysCount + $skippedDaysCount,
                                    $generatedInventoryCount,
                                    $skippedDaysCount
                                ));
                            }
                        } catch  (\Throwable $e) {
                            $this->logger->error('生成库存失败', [
                                'exception' => $e,
                            ]);
                            $this->addFlash('danger', sprintf(
                                '为合同 %s 的房型 %s 生成库存失败: %s',
                                $contract->getContractNo(),
                                $roomType->getName(),
                                $e->getMessage()
                            ));
                            $totalFailed++;
                        }
                    }
                }
            }

            $this->addFlash('success', sprintf(
                '处理完成: 处理了%d个合同，生成了%d个库存，跳过了%d个已存在的库存，失败%d个',
                $processedContracts,
                $totalGenerated,
                $totalSkipped,
                $totalFailed
            ));

            // 提交事务
            $em->getConnection()->commit();
        } catch  (\Throwable $e) {
            // 回滚事务
            $em->getConnection()->rollBack();
            $this->addFlash('danger', '批量生成库存失败: ' . $e->getMessage());
        }

        return $this->redirect($this->adminUrlGenerator
            ->setController(self::class)
            ->setAction(Action::INDEX)
            ->generateUrl());
    }

    /**
     * 持久化实体前的操作
     */
    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof DailyInventory) {
            // 确保酒店根据房型自动设置
            if ($entityInstance->getRoomType()) {
                $entityInstance->setHotel($entityInstance->getRoomType()->getHotel());
            }

            // 触发利润率计算
            $costPrice = $entityInstance->getCostPrice();
            $sellingPrice = $entityInstance->getSellingPrice();
            $entityInstance->setCostPrice($costPrice);
            $entityInstance->setSellingPrice($sellingPrice);
        }

        parent::persistEntity($entityManager, $entityInstance);
    }

    /**
     * 更新实体前的操作
     */
    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof DailyInventory) {
            // 触发利润率计算
            $costPrice = $entityInstance->getCostPrice();
            $sellingPrice = $entityInstance->getSellingPrice();
            $entityInstance->setCostPrice($costPrice);
            $entityInstance->setSellingPrice($sellingPrice);
        }

        parent::updateEntity($entityManager, $entityInstance);
    }

    /**
     * 批量调价表单
     */
    #[Route('/admin/room-type-inventory/batch-price-update', name: 'admin_room_type_inventory_batch_price_update')]
    public function batchPriceUpdateForm(Request $request, EntityManagerInterface $em): Response
    {
        // 获取所有酒店
        $hotels = $em->getRepository(\Tourze\HotelProfileBundle\Entity\Hotel::class)
            ->findBy([], ['name' => 'ASC']);

        // 获取所有房型
        $roomTypes = $em->getRepository(RoomType::class)
            ->createQueryBuilder('rt')
            ->leftJoin('rt.hotel', 'h')
            ->addSelect('h')
            ->orderBy('h.name', 'ASC')
            ->addOrderBy('rt.name', 'ASC')
            ->getQuery()
            ->getResult();

        // 获取所有合同
        $contracts = $em->getRepository(HotelContract::class)
            ->findBy([], ['contractNo' => 'ASC']);

        return $this->render('@HotelContract/admin/room_type_inventory/batch_price_update.html.twig', [
            'hotels' => $hotels,
            'roomTypes' => $roomTypes,
            'contracts' => $contracts,
        ]);
    }

    /**
     * 批量调价处理
     */
    #[Route('/admin/room-type-inventory/batch-price-update-process', name: 'admin_room_type_inventory_batch_price_update_process', methods: ['POST'])]
    public function batchPriceUpdateProcess(Request $request): Response
    {
        // 调用价格管理的批量调价功能
        return $this->redirect($this->generateUrl('admin', [
            'crudAction' => 'batchPriceAdjustment',
            'crudControllerFqcn' => InventorySummaryCrudController::class,
        ]));
    }
}
