<?php

namespace Tourze\HotelContractBundle\Controller\Admin;

use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminAction;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminCrud;
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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Tourze\HotelContractBundle\Entity\InventorySummary;
use Tourze\HotelContractBundle\Enum\InventorySummaryStatusEnum;
use Tourze\HotelContractBundle\Service\InventorySummaryService;

/**
 * @extends AbstractCrudController<InventorySummary>
 */
#[AdminCrud(routePath: '/hotel/inventory-summary', routeName: 'hotel_inventory_summary')]
final class InventorySummaryCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly InventorySummaryService $summaryService,
    ) {
    }

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
            ->setDefaultSort(['date' => 'DESC', 'id' => 'DESC'])
        ;
    }

    public function configureActions(Actions $actions): Actions
    {
        $sync = Action::new('syncInventorySummary', '同步统计数据')
            ->linkToCrudAction('syncInventorySummary')
            ->createAsGlobalAction()
            ->setCssClass('btn btn-primary')
            ->setIcon('fa fa-sync')
        ;

        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $sync)
            ->disable(Action::NEW, Action::EDIT, Action::DELETE)
            ->update(Crud::PAGE_INDEX, Action::DETAIL, function (Action $action) {
                return $action->setLabel('详情');
            })
        ;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('hotel', '酒店'))
            ->add(EntityFilter::new('roomType', '房型'))
            ->add(DateTimeFilter::new('date', '日期'))
            ->add('status')
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', 'ID')->hideOnForm()->hideOnIndex();

        yield AssociationField::new('hotel', '酒店')
            ->setRequired(true)
        ;

        yield AssociationField::new('roomType', '房型')
            ->setRequired(true)
        ;

        yield DateField::new('date', '日期')
            ->setRequired(true)
        ;

        yield IntegerField::new('totalRooms', '总房间数')
            ->setRequired(true)
        ;

        yield IntegerField::new('availableRooms', '可售房间数')
            ->setRequired(true)
        ;

        yield IntegerField::new('reservedRooms', '预留房间数')
            ->setRequired(true)
        ;

        yield IntegerField::new('soldRooms', '已售房间数')
            ->setRequired(true)
        ;

        yield IntegerField::new('pendingRooms', '待确认房间数')
            ->setRequired(true)
        ;

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
            ])
        ;

        yield MoneyField::new('lowestPrice', '最低采购价')
            ->setCurrency('CNY')
            ->setStoredAsCents(false)
        ;

        yield AssociationField::new('lowestContract', '最低价合同')
            ->hideOnIndex()
        ;

        yield DateTimeField::new('createTime', '创建时间')
            ->hideOnForm()
            ->hideOnIndex()
        ;

        yield DateTimeField::new('updateTime', '更新时间')
            ->hideOnForm()
            ->hideOnIndex()
        ;
    }

    public function createIndexQueryBuilder(
        SearchDto $searchDto,
        EntityDto $entityDto,
        FieldCollection $fields,
        FilterCollection $filters,
    ): QueryBuilder {
        $queryBuilder = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);
        $rootAlias = $queryBuilder->getRootAliases()[0];

        // 默认加载关联
        $queryBuilder->leftJoin($rootAlias . '.hotel', 'hotel')
            ->leftJoin($rootAlias . '.roomType', 'roomType')
            ->leftJoin($rootAlias . '.lowestContract', 'lowestContract')
            ->addSelect('hotel', 'roomType', 'lowestContract')
        ;

        return $queryBuilder;
    }

    /**
     * 同步库存统计数据
     */
    #[AdminAction(routePath: 'sync', routeName: 'sync')]
    public function syncInventorySummary(Request $request): Response
    {
        $result = $this->summaryService->syncInventorySummary();

        if (isset($result['success']) && true === $result['success']) {
            $this->addFlash('success', $result['message']);
        } else {
            $this->addFlash('danger', $result['message']);
        }

        return $this->redirect($request->headers->get('referer') ?? $this->generateUrl('admin'));
    }
}
