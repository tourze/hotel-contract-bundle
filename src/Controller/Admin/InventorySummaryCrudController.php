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
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Tourze\HotelContractBundle\Entity\InventorySummary;
use Tourze\HotelContractBundle\Enum\InventorySummaryStatusEnum;
use Tourze\HotelContractBundle\Service\InventoryConfig;
use Tourze\HotelContractBundle\Service\InventorySummaryService;
use Tourze\HotelContractBundle\Service\PriceManagementService;

class InventorySummaryCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly InventorySummaryService $summaryService,
        private readonly RequestStack $requestStack,
        private readonly InventoryConfig $inventoryConfig,
        private readonly PriceManagementService $priceManagementService,
    ) {}

    public static function getEntityFqcn(): string
    {
        return InventorySummary::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('库存统计')
            ->setEntityLabelInPlural('库存统计')
            ->setSearchFields(['hotel.name', 'roomType.name'])
            ->setPaginatorPageSize(20)
            ->setDefaultSort(['date' => 'DESC', 'id' => 'DESC']);
    }

    public function configureActions(Actions $actions): Actions
    {
        $export = Action::new('exportInventorySummary', '导出报表')
            ->linkToCrudAction('exportInventorySummary')
            ->createAsGlobalAction()
            ->setCssClass('btn btn-success')
            ->setIcon('fa fa-file-excel');

        $sync = Action::new('syncInventorySummary', '同步统计数据')
            ->linkToCrudAction('syncInventorySummary')
            ->createAsGlobalAction()
            ->setCssClass('btn btn-primary')
            ->setIcon('fa fa-sync');

        $warningConfig = Action::new('inventoryWarningConfig', '预警配置')
            ->linkToCrudAction('inventoryWarningConfig')
            ->createAsGlobalAction()
            ->setCssClass('btn btn-warning')
            ->setIcon('fa fa-bell');

        $contractPriceCalendar = Action::new('contractPriceCalendar', '合同价格日历')
            ->linkToCrudAction('contractPriceCalendar')
            ->createAsGlobalAction()
            ->setCssClass('btn btn-secondary')
            ->setIcon('fa fa-calendar-alt');

        $sellingPriceManagement = Action::new('sellingPriceManagement', '销售价格管理')
            ->linkToCrudAction('sellingPriceManagement')
            ->createAsGlobalAction()
            ->setCssClass('btn btn-info')
            ->setIcon('fa fa-tag');

        $batchPriceAdjustment = Action::new('batchPriceAdjustment', '批量调价')
            ->linkToCrudAction('batchPriceAdjustment')
            ->createAsGlobalAction()
            ->setCssClass('btn btn-dark')
            ->setIcon('fa fa-money-bill-wave');

        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $export)
            ->add(Crud::PAGE_INDEX, $sync)
            ->add(Crud::PAGE_INDEX, $warningConfig)
            ->add(Crud::PAGE_INDEX, $contractPriceCalendar)
            ->add(Crud::PAGE_INDEX, $sellingPriceManagement)
            ->add(Crud::PAGE_INDEX, $batchPriceAdjustment)
            ->disable(Action::NEW, Action::EDIT, Action::DELETE)
            ->update(Crud::PAGE_INDEX, Action::DETAIL, function (Action $action) {
                return $action->setLabel('详情');
            });
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('hotel', '酒店'))
            ->add(EntityFilter::new('roomType', '房型'))
            ->add(DateTimeFilter::new('date', '日期'))
            ->add('status');
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();

        yield AssociationField::new('hotel', '酒店')
            ->setRequired(true);

        yield AssociationField::new('roomType', '房型')
            ->setRequired(true);

        yield DateField::new('date', '日期')
            ->setRequired(true);

        yield IntegerField::new('totalRooms', '总房间数')
            ->setRequired(true);

        yield IntegerField::new('availableRooms', '可售房间数')
            ->setRequired(true);

        yield IntegerField::new('reservedRooms', '预留房间数')
            ->setRequired(true);

        yield IntegerField::new('soldRooms', '已售房间数')
            ->setRequired(true);

        yield IntegerField::new('pendingRooms', '待确认房间数')
            ->setRequired(true);

        yield ChoiceField::new('status', '状态')
            ->setChoices([
                '正常' => InventorySummaryStatusEnum::NORMAL,
                '预警' => InventorySummaryStatusEnum::WARNING,
                '售罄' => InventorySummaryStatusEnum::SOLD_OUT,
            ])
            ->renderAsBadges([
                InventorySummaryStatusEnum::NORMAL->value => 'success',
                InventorySummaryStatusEnum::WARNING->value => 'warning',
                InventorySummaryStatusEnum::SOLD_OUT->value => 'danger',
            ]);

        yield MoneyField::new('lowestPrice', '最低采购价')
            ->setCurrency('CNY')
            ->setStoredAsCents(false);

        yield AssociationField::new('lowestContract', '最低价合同')
            ->hideOnIndex();

        yield DateTimeField::new('createTime', '创建时间')
            ->hideOnForm()
            ->hideOnIndex();

        yield DateTimeField::new('updateTime', '更新时间')
            ->hideOnForm()
            ->hideOnIndex();
    }

    public function createIndexQueryBuilder(
        SearchDto        $searchDto,
        EntityDto        $entityDto,
        FieldCollection  $fields,
        FilterCollection $filters
    ): QueryBuilder {
        $queryBuilder = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);
        $rootAlias = $queryBuilder->getRootAliases()[0];

        // 默认加载关联
        $queryBuilder->leftJoin($rootAlias . '.hotel', 'hotel')
            ->leftJoin($rootAlias . '.roomType', 'roomType')
            ->leftJoin($rootAlias . '.lowestContract', 'lowestContract')
            ->addSelect('hotel', 'roomType', 'lowestContract');

        return $queryBuilder;
    }

    /**
     * 导出库存统计报表
     */
    public function exportInventorySummary()
    {
        // 获取当前筛选条件
        $request = $this->requestStack->getCurrentRequest();
        $filters = $request->query->all('filters') ?: [];

        // 构建查询
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('is')
            ->from(InventorySummary::class, 'is')
            ->leftJoin('is.hotel', 'h')
            ->leftJoin('is.roomType', 'rt')
            ->orderBy('is.date', 'DESC')
            ->addOrderBy('h.name', 'ASC')
            ->addOrderBy('rt.name', 'ASC');

        // 应用筛选条件
        if (isset($filters['hotel'])) {
            $qb->andWhere('is.hotel = :hotel')
                ->setParameter('hotel', $filters['hotel']);
        }

        if (isset($filters['roomType'])) {
            $qb->andWhere('is.roomType = :roomType')
                ->setParameter('roomType', $filters['roomType']);
        }

        if (isset($filters['date'])) {
            $dateFilter = $filters['date'];
            if (isset($dateFilter['comparison']) && isset($dateFilter['value'])) {
                $comparison = $dateFilter['comparison'];
                $value = new \DateTimeImmutable($dateFilter['value']);

                switch ($comparison) {
                    case '>':
                        $qb->andWhere('is.date > :date')->setParameter('date', $value);
                        break;
                    case '>=':
                        $qb->andWhere('is.date >= :date')->setParameter('date', $value);
                        break;
                    case '<':
                        $qb->andWhere('is.date < :date')->setParameter('date', $value);
                        break;
                    case '<=':
                        $qb->andWhere('is.date <= :date')->setParameter('date', $value);
                        break;
                    case '=':
                        $qb->andWhere('is.date = :date')->setParameter('date', $value);
                        break;
                }
            }
        }

        if (isset($filters['status'])) {
            $qb->andWhere('is.status = :status')
                ->setParameter('status', $filters['status']);
        }

        $results = $qb->getQuery()->getResult();

        if (empty($results)) {
            $this->addFlash('warning', '没有符合条件的数据');

            return $this->redirect($this->generateUrl('admin', [
                'crudAction' => 'index',
                'crudControllerFqcn' => self::class,
            ]));
        }

        // 创建CSV文件
        $tempFile = tempnam(sys_get_temp_dir(), 'inventory_summary_');
        $csvFile = fopen($tempFile, 'w');

        // 添加UTF-8 BOM以支持中文
        fputs($csvFile, "\xEF\xBB\xBF");

        // 写入表头
        fputcsv($csvFile, [
            '日期',
            '酒店',
            '房型',
            '总房间数',
            '可售房间数',
            '预留房间数',
            '已售房间数',
            '待确认房间数',
            '状态',
            '最低采购价'
        ]);

        // 写入数据
        foreach ($results as $summary) {
            // 格式化状态
            $status = match ($summary->getStatus()) {
                InventorySummaryStatusEnum::NORMAL => '正常',
                InventorySummaryStatusEnum::WARNING => '预警',
                InventorySummaryStatusEnum::SOLD_OUT => '售罄',
                default => $summary->getStatus()->value
            };

            fputcsv($csvFile, [
                $summary->getDate()->format('Y-m-d'),
                $summary->getHotel()->getName(),
                $summary->getRoomType()->getName(),
                $summary->getTotalRooms(),
                $summary->getAvailableRooms(),
                $summary->getReservedRooms(),
                $summary->getSoldRooms(),
                $summary->getPendingRooms(),
                $status,
                $summary->getLowestPrice()
            ]);
        }

        fclose($csvFile);

        // 设置响应
        $response = new BinaryFileResponse($tempFile);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            'inventory_summary_' . (new \DateTimeImmutable())->format('Ymd_His') . '.csv'
        );

        // 请求结束后删除临时文件
        register_shutdown_function(function () use ($tempFile) {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        });

        return $response;
    }

    /**
     * 同步库存统计数据
     */
    #[AdminAction('sync', 'sync')]
    public function syncInventorySummary(Request $request): Response
    {
        $result = $this->summaryService->syncInventorySummary();

        if ($result['success']) {
            $this->addFlash('success', $result['message']);
        } else {
            $this->addFlash('danger', $result['message']);
        }

        return $this->redirect($request->headers->get('referer'));
    }

    /**
     * 库存预警配置页面
     */
    #[AdminAction('warn', 'warn', methods: ['GET', 'POST'])]
    public function inventoryWarningConfig(): Response
    {
        $request = $this->requestStack->getCurrentRequest();

        // 加载当前配置
        $config = $this->inventoryConfig->getWarningConfig();

        if ($request->isMethod('POST')) {
            // 处理表单提交
            $warningThreshold = $request->request->getInt('warning_threshold', 10);
            $emailRecipients = $request->request->get('email_recipients', '');
            $enableWarning = $request->request->getBoolean('enable_warning', false);
            $warningInterval = $request->request->getInt('warning_interval', 24);

            // 保存配置
            $newConfig = [
                'warning_threshold' => max(1, min(100, $warningThreshold)),
                'email_recipients' => $emailRecipients,
                'enable_warning' => $enableWarning,
                'warning_interval' => max(1, $warningInterval),
            ];

            if ($this->inventoryConfig->saveWarningConfig($newConfig)) {
                $this->addFlash('success', '库存预警配置已保存');

                // 同时更新所有库存状态
                $this->summaryService->updateInventorySummaryStatus($newConfig['warning_threshold']);
            } else {
                $this->addFlash('danger', '保存配置失败');
            }
        }

        return $this->render('@HotelContract/admin/inventory/inventory_warning.html.twig', [
            'warning_threshold' => $config['warning_threshold'],
            'email_recipients' => $config['email_recipients'],
            'enable_warning' => $config['enable_warning'],
            'warning_interval' => $config['warning_interval'],
        ]);
    }

    /**
     * 合同价格日历管理
     */
    #[AdminAction('contract-calendar', 'contract_price_calendar')]
    public function contractPriceCalendar(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            return $this->updateContractPrice();
        }

        $request = $this->requestStack->getCurrentRequest();
        $contractId = $request->query->get('contract');
        $month = $request->query->get('month', date('Y-m'));

        $data = $this->priceManagementService->getContractPriceCalendarData($contractId ? (int)$contractId : null, $month);

        return $this->render('@HotelContract/admin/price/contract_calendar.html.twig', $data);
    }

    /**
     * 更新合同价格
     */
    private function updateContractPrice(): Response
    {
        $request = $this->requestStack->getCurrentRequest();
        $inventoryId = $request->request->get('inventory_id');
        $costPrice = $request->request->get('cost_price');
        $redirectUrl = $request->request->get('redirect_url', $request->headers->get('referer'));

        if (!$inventoryId || $costPrice === null) {
            $this->addFlash('danger', '参数错误');
            return $this->redirect($redirectUrl);
        }

        $result = $this->priceManagementService->updateContractPrice((int)$inventoryId, (string)$costPrice);

        if ($result['success']) {
            $this->addFlash('success', $result['message']);
        } else {
            $this->addFlash('danger', $result['message']);
        }

        return $this->redirect($redirectUrl);
    }

    /**
     * 销售价格管理
     */
    #[AdminAction('selling-price', 'selling_price_management')]
    public function sellingPriceManagement(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            return $this->updateSellingPrice();
        }

        $request = $this->requestStack->getCurrentRequest();
        $hotelId = $request->query->get('hotel');
        $roomTypeId = $request->query->get('room_type');
        $month = $request->query->get('month', date('Y-m'));

        $data = $this->priceManagementService->getSellingPriceData(
            $hotelId ? (int)$hotelId : null,
            $roomTypeId ? (int)$roomTypeId : null,
            $month
        );

        return $this->render('@HotelContract/admin/price/selling_price.html.twig', $data);
    }

    /**
     * 更新销售价格
     */
    #[AdminAction('update-selling-price', 'update_selling_price', methods: ['POST'])]
    public function updateSellingPrice(): Response
    {
        $request = $this->requestStack->getCurrentRequest();
        $inventoryId = $request->request->get('inventory_id');
        $sellingPrice = $request->request->get('selling_price');
        $redirectUrl = $request->request->get('redirect_url', $request->headers->get('referer'));

        if (!$inventoryId || $sellingPrice === null) {
            $this->addFlash('danger', '参数错误');
            return $this->redirect($redirectUrl);
        }

        $result = $this->priceManagementService->updateSellingPrice((int)$inventoryId, (string)$sellingPrice);

        if ($result['success']) {
            $this->addFlash('success', $result['message']);
        } else {
            $this->addFlash('danger', $result['message']);
        }

        return $this->redirect($redirectUrl);
    }

    /**
     * 批量调价页面
     */
    #[AdminAction('batch-adjustment', 'batch_price_adjustment', methods: ['GET', 'POST'])]
    public function batchPriceAdjustment(): Response
    {
        $request = $this->requestStack->getCurrentRequest();

        if ($request->isMethod('POST')) {
            $params = [
                'hotel_id' => $request->request->get('hotel'),
                'room_type_id' => $request->request->get('room_type'),
                'start_date' => $request->request->get('start_date'),
                'end_date' => $request->request->get('end_date'),
                'price_type' => $request->request->get('price_type'),
                'adjust_method' => $request->request->get('adjust_method'),
                'day_filter' => $request->request->get('day_filter'),
                'days' => $request->request->all('days') ?: [],
                'reason' => $request->request->get('reason'),
                'price_value' => $request->request->get('price_value'),
                'adjust_value' => $request->request->get('adjust_value'),
            ];

            if (!$params['hotel_id'] || !$params['start_date'] || !$params['end_date']) {
                $this->addFlash('danger', '请填写必要的参数');
                return $this->redirectToCrudAction('batchPriceAdjustment');
            }

            $result = $this->priceManagementService->processBatchPriceAdjustment($params);

            if ($result['success']) {
                $this->addFlash('success', $result['message']);
            } else {
                $this->addFlash('danger', $result['message']);
            }

            return $this->redirectToCrudAction('batchPriceAdjustment');
        }

        $data = $this->priceManagementService->getBatchAdjustmentData();

        return $this->render('@HotelContract/admin/price/batch_adjustment.html.twig', $data);
    }

    /**
     * 重定向到 CRUD 操作
     */
    private function redirectToCrudAction(string $action): Response
    {
        return $this->redirect($this->generateUrl('admin', [
            'crudAction' => $action,
            'crudControllerFqcn' => self::class,
        ]));
    }

    /**
     * AJAX更新合同价格
     */
    #[AdminAction('ajax-update-contract-price', 'ajax_update_contract_price', methods: ['POST'])]
    public function ajaxUpdateContractPrice(Request $request): Response
    {
        if (!$request->isXmlHttpRequest()) {
            return $this->json(['success' => false, 'message' => '请求方式不正确'], 400);
        }

        $inventoryId = $request->request->get('inventory_id');
        $costPrice = $request->request->get('cost_price');

        if (!$inventoryId || $costPrice === null) {
            return $this->json(['success' => false, 'message' => '参数错误'], 400);
        }

        $result = $this->priceManagementService->updateContractPrice((int)$inventoryId, (string)$costPrice);

        return $this->json($result);
    }

    /**
     * AJAX更新销售价格
     */
    #[AdminAction('ajax-update-selling-price', 'ajax_update_selling_price', methods: ['POST'])]
    public function ajaxUpdateSellingPrice(Request $request): Response
    {
        if (!$request->isXmlHttpRequest()) {
            return $this->json(['success' => false, 'message' => '请求方式不正确'], 400);
        }

        $inventoryId = $request->request->get('inventory_id');
        $sellingPrice = $request->request->get('selling_price');

        if (!$inventoryId || $sellingPrice === null) {
            return $this->json(['success' => false, 'message' => '参数错误'], 400);
        }

        $result = $this->priceManagementService->updateSellingPrice((int)$inventoryId, (string)$sellingPrice);

        return $this->json($result);
    }

    /**
     * AJAX批量价格调整
     */
    #[AdminAction('ajax-batch-price', 'ajax_batch_price', methods: ['POST'])]
    public function ajaxBatchPriceAdjustment(Request $request): Response
    {
        if (!$request->isXmlHttpRequest()) {
            return $this->json(['success' => false, 'message' => '请求方式不正确'], 400);
        }

        $data = json_decode($request->getContent(), true);

        if ($data === null || !is_array($data)) {
            return $this->json(['success' => false, 'message' => '请求数据格式错误'], 400);
        }

        $result = $this->priceManagementService->processBatchPriceAdjustment($data);

        return $this->json($result);
    }
}
