<?php

namespace Tourze\HotelContractBundle\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminAction;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\HttpFoundation\Response;
use Tourze\HotelContractBundle\Entity\HotelContract;
use Tourze\HotelContractBundle\Enum\ContractStatusEnum;
use Tourze\HotelContractBundle\Enum\ContractTypeEnum;
use Tourze\HotelContractBundle\Enum\DailyInventoryStatusEnum;
use Tourze\HotelContractBundle\Repository\DailyInventoryRepository;
use Tourze\HotelContractBundle\Service\ContractService;

class HotelContractCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly ContractService $contractService,
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly DailyInventoryRepository $dailyInventoryRepository,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return HotelContract::class;
    }

    public function configureActions(Actions $actions): Actions
    {
        $terminateAction = Action::new('terminate', '终止合同')
            ->linkToCrudAction('terminateContract')
            ->displayIf(static function (HotelContract $entity) {
                return $entity->getStatus() === ContractStatusEnum::ACTIVE;
            })
            ->setCssClass('btn btn-warning');

        $approveAction = Action::new('approve', '确认生效')
            ->linkToCrudAction('approveContract')
            ->displayIf(static function (HotelContract $entity) {
                return $entity->getStatus() === ContractStatusEnum::PENDING;
            })
            ->setCssClass('btn btn-success');

        $inventoryStatsAction = Action::new('inventoryStats', '库存统计')
            ->linkToCrudAction('inventoryStats')
            ->setCssClass('btn btn-info');

        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_DETAIL, $terminateAction)
            ->add(Crud::PAGE_DETAIL, $approveAction)
            ->add(Crud::PAGE_DETAIL, $inventoryStatsAction)
            ->reorder(Crud::PAGE_DETAIL, [
                'approve', 'terminate', 'inventoryStats', Action::EDIT, Action::DELETE, Action::INDEX
            ]);
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('酒店合同')
            ->setEntityLabelInPlural('酒店合同')
            ->setPageTitle(Crud::PAGE_INDEX, '合同列表')
            ->setPageTitle(Crud::PAGE_NEW, '创建新合同')
            ->setPageTitle(Crud::PAGE_EDIT, fn (HotelContract $contract) => sprintf('编辑合同 <strong>%s</strong>', $contract->getContractNo()))
            ->setPageTitle(Crud::PAGE_DETAIL, fn (HotelContract $contract) => sprintf('合同详情 <strong>%s</strong>', $contract->getContractNo()))
            ->setDefaultSort(['priority' => 'ASC', 'createTime' => 'DESC'])
            ->setFormOptions(['validation_groups' => ['Default']]);
    }

    public function configureFilters(Filters $filters): Filters
    {
        $contractTypeChoices = [];
        foreach (ContractTypeEnum::cases() as $case) {
            $contractTypeChoices[$case->getLabel()] = $case->value;
        }
        
        $contractStatusChoices = [];
        foreach (ContractStatusEnum::cases() as $case) {
            $contractStatusChoices[$case->getLabel()] = $case->value;
        }
        
        return $filters
            ->add(TextFilter::new('contractNo', '合同编号'))
            ->add(EntityFilter::new('hotel', '酒店'))
            ->add(ChoiceFilter::new('contractType', '合同类型')->setChoices($contractTypeChoices))
            ->add(ChoiceFilter::new('status', '合同状态')->setChoices($contractStatusChoices))
            ->add(DateTimeFilter::new('startDate', '开始日期'))
            ->add(DateTimeFilter::new('endDate', '结束日期'))
            ->add(DateTimeFilter::new('createTime', '创建时间'));
    }

    public function configureFields(string $pageName): iterable
    {
        yield FormField::addPanel('基本信息');
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('contractNo', '合同编号')
            ->setFormTypeOption('disabled', 'disabled')
            ->setHelp('合同编号将自动生成，格式为：HT + 年月日 + 3位序号')
            ->hideOnForm();
        
        yield AssociationField::new('hotel', '酒店')
            ->setRequired(true)
            ->setFormTypeOption('placeholder', '请选择酒店');

        yield ChoiceField::new('contractType', '合同类型')
            ->setChoices(ContractTypeEnum::cases())
            ->setRequired(true)
            ->formatValue(function ($value) {
                return $value instanceof ContractTypeEnum ? $value->getLabel() : '';
            })
            ->renderAsBadges([
                ContractTypeEnum::FIXED_PRICE->value => 'primary',
                ContractTypeEnum::DYNAMIC_PRICE->value => 'success',
            ]);

        yield ChoiceField::new('status', '合同状态')
            ->setChoices(ContractStatusEnum::cases())
            ->setRequired(true)
            ->setFormTypeOption('disabled', 'disabled')
            ->formatValue(function ($value) {
                return $value instanceof ContractStatusEnum ? $value->getLabel() : '';
            })
            ->renderAsBadges([
                ContractStatusEnum::PENDING->value => 'warning',
                ContractStatusEnum::ACTIVE->value => 'success',
                ContractStatusEnum::TERMINATED->value => 'danger',
            ])
            ->hideOnForm();
        
        yield FormField::addPanel('合同期限与房间配置');
        yield DateField::new('startDate', '开始日期')
            ->setRequired(true);
        
        yield DateField::new('endDate', '结束日期')
            ->setRequired(true);
        
        yield IntegerField::new('totalRooms', '总房间数')
            ->setRequired(true)
            ->setHelp('此合同包含的房间总数');
        
        yield IntegerField::new('totalDays', '总天数')
            ->setRequired(true)
            ->setHelp('此合同覆盖的总天数');
        
        yield MoneyField::new('totalAmount', '合同总成本')
            ->setCurrency('CNY')
            ->setStoredAsCents(false)
            ->setRequired(true)
            ->setHelp('合同规定的总成本金额');

        // 计算字段 - 仅在详情和列表页面显示
        if ($pageName === Crud::PAGE_DETAIL || $pageName === Crud::PAGE_INDEX) {
            yield MoneyField::new('totalSellingAmount', '合同总售价')
                ->setCurrency('CNY')
                ->setStoredAsCents(false)
                ->hideOnForm()
                ->setHelp('基于关联库存售价计算的总售价')
                ->formatValue(function ($value, $entity) {
                    if ($entity instanceof HotelContract) {
                        return $entity->getTotalSellingAmount();
                    }
                    return 0;
                });

            // 使用虚拟字段显示利润率
            yield TextField::new('profit_rate_display', '利润率')
                ->hideOnForm()
                ->setHelp('基于成本和售价计算的利润率')
                ->setTemplatePath('@HotelContract/admin/field/profit_rate_badge.html.twig')
                ->formatValue(function ($value, $entity) {
                    if ($entity instanceof HotelContract) {
                        $profitRate = $entity->getProfitRate();
                        $color = 'secondary';
                        if ($profitRate >= 30) {
                            $color = 'success';
                        } elseif ($profitRate >= 15) {
                            $color = 'info';
                        } elseif ($profitRate < 0) {
                            $color = 'danger';
                        }
                        return [
                            'rate' => $profitRate,
                            'color' => $color,
                            'formatted' => sprintf('%.2f%%', $profitRate)
                        ];
                    }
                    return [
                        'rate' => 0,
                        'color' => 'secondary', 
                        'formatted' => '0.00%'
                    ];
                });
        }
        
        yield FormField::addPanel('其他信息');
        yield IntegerField::new('priority', '合同优先级')
            ->setHelp('数字越小优先级越高，相同酒店的合同会按优先级排序使用')
            ->setRequired(true);
        
        if ($pageName === Crud::PAGE_EDIT || $pageName === Crud::PAGE_DETAIL) {
            yield TextareaField::new('terminationReason', '终止原因')
                ->hideOnIndex()
                ->setFormTypeOption('disabled', 'disabled')
                ->setCustomOption('renderCondition', function(HotelContract $entity) {
                    return $entity->getStatus() === ContractStatusEnum::TERMINATED;
                });
        }

        yield FormField::addPanel('系统信息');
        yield DateTimeField::new('createTime', '创建时间')
            ->hideOnForm()
            ->setFormTypeOption('disabled', 'disabled')
            ->setFormat('Y-m-d H:i:s');
        
        yield DateTimeField::new('updateTime', '更新时间')
            ->hideOnForm()
            ->hideOnIndex()
            ->setFormTypeOption('disabled', 'disabled')
            ->setFormat('Y-m-d H:i:s');
    }

    public function createIndexQueryBuilder(
        SearchDto        $searchDto,
        EntityDto        $entityDto,
        FieldCollection  $fields,
        FilterCollection $filters
    ): QueryBuilder {
        $queryBuilder = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);
        $rootAlias = $queryBuilder->getRootAliases()[0];

        // 预加载相关数据以提高性能
        $queryBuilder->leftJoin($rootAlias . '.hotel', 'hotel')
            ->addSelect('hotel');

        return $queryBuilder;
    }
    
    public function createEntity(string $entityFqcn)
    {
        $contract = new HotelContract();
        $contract->setStatus(ContractStatusEnum::PENDING);
        // 初始化合同编号为空，保存时生成
        return $contract;
    }
    
    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        // 生成合同编号
        if (empty($entityInstance->getContractNo())) {
            $contractNo = $this->contractService->generateContractNumber();
            $entityInstance->setContractNo($contractNo);
        }
        
        parent::persistEntity($entityManager, $entityInstance);
        
        // 创建合同后发送通知
        $this->contractService->sendContractCreatedNotification($entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        parent::updateEntity($entityManager, $entityInstance);
        
        // 更新合同后发送通知
        $this->contractService->sendContractUpdatedNotification($entityInstance);
    }
    
    public function approveContract(AdminContext $context)
    {
        $contract = $context->getEntity()->getInstance();
        $this->contractService->approveContract($contract);
        
        $this->addFlash('success', sprintf('合同 %s 已审批生效', $contract->getContractNo()));
        
        return $this->redirect(
            $this->adminUrlGenerator
                ->setAction(Action::DETAIL)
                ->setEntityId($contract->getId())
                ->generateUrl()
        );
    }

    #[AdminAction('{entityId}/terminate', 'terminate_contract', methods: ['GET', 'POST'])]
    public function terminateContract(AdminContext $context)
    {
        $contract = $context->getEntity()->getInstance();
        assert($contract instanceof HotelContract);

        // 创建终止表单
        $formBuilder = $this->createFormBuilder()
            ->add('terminationReason', TextareaType::class, [
                'required' => true,
                'label' => '请输入终止原因',
                'attr' => ['rows' => 5],
            ]);

        $form = $formBuilder->getForm();
        $form->handleRequest($context->getRequest());

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $this->contractService->terminateContract($contract, $data['terminationReason']);

            $this->addFlash('success', sprintf('合同 %s 已终止', $contract->getContractNo()));

            return $this->redirect(
                $this->adminUrlGenerator
                    ->setAction(Action::DETAIL)
                    ->setEntityId($contract->getId())
                    ->generateUrl()
            );
        }

        return $this->render('@HotelContract/admin/contract/terminate.html.twig', [
            'contract' => $contract,
            'form' => $form->createView(),
        ]);
    }

    #[AdminAction('{entityId}/inventory-stats', 'contract_inventory_stats', methods: ['GET'])]
    public function inventoryStats(AdminContext $context): Response
    {
        $contract = $context->getEntity()->getInstance();
        assert($contract instanceof HotelContract);
        
        // 获取合同库存统计
        $startDate = new \DateTime();
        $endDate = (new \DateTime())->modify('+30 days'); // 统计未来30天的库存
        
        // 按房型统计库存情况
        $inventoryStats = $this->dailyInventoryRepository
            ->createQueryBuilder('di')
            ->select('rt.id as roomTypeId, rt.name as roomTypeName')
            ->addSelect('COUNT(di.id) as totalCount')
            ->addSelect('SUM(CASE WHEN di.status = :statusAvailable THEN 1 ELSE 0 END) as availableCount')
            ->addSelect('SUM(CASE WHEN di.status = :statusSold THEN 1 ELSE 0 END) as soldCount')
            ->addSelect('SUM(CASE WHEN di.status = :statusReserved THEN 1 ELSE 0 END) as reservedCount')
            ->join('di.roomType', 'rt')
            ->where('di.contract = :contract')
            ->andWhere('di.date BETWEEN :startDate AND :endDate')
            ->groupBy('rt.id')
            ->setParameter('contract', $contract)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->setParameter('statusAvailable', DailyInventoryStatusEnum::AVAILABLE)
            ->setParameter('statusSold', DailyInventoryStatusEnum::SOLD)
            ->setParameter('statusReserved', DailyInventoryStatusEnum::RESERVED)
            ->getQuery()
            ->getResult();

        // 计算每日库存情况
        $dailyStatsQuery = $this->dailyInventoryRepository
            ->createQueryBuilder('di')
            ->select('di.date as dateObj')
            ->addSelect('COUNT(di.id) as totalCount')
            ->addSelect('SUM(CASE WHEN di.status = :statusAvailable THEN 1 ELSE 0 END) as availableCount')
            ->addSelect('SUM(CASE WHEN di.status = :statusSold THEN 1 ELSE 0 END) as soldCount')
            ->addSelect('SUM(CASE WHEN di.status = :statusReserved THEN 1 ELSE 0 END) as reservedCount')
            ->where('di.contract = :contract')
            ->andWhere('di.date BETWEEN :startDate AND :endDate')
            ->groupBy('di.date')
            ->orderBy('di.date', 'ASC')
            ->setParameter('contract', $contract)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->setParameter('statusAvailable', DailyInventoryStatusEnum::AVAILABLE)
            ->setParameter('statusSold', DailyInventoryStatusEnum::SOLD)
            ->setParameter('statusReserved', DailyInventoryStatusEnum::RESERVED)
            ->getQuery();

        $dailyStatsResults = $dailyStatsQuery->getResult();

        // 格式化日期
        $dailyStats = [];
        foreach ($dailyStatsResults as $result) {
            $dateObj = $result['dateObj'];
            $result['date'] = $dateObj->format('Y-m-d');
            unset($result['dateObj']);
            $dailyStats[] = $result;
        }

        return $this->render('@HotelContract/admin/contract/inventory_stats.html.twig', [
            'contract' => $contract,
            'inventoryStats' => $inventoryStats,
            'dailyStats' => $dailyStats,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);
    }
}
