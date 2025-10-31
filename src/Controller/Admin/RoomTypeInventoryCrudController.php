<?php

namespace Tourze\HotelContractBundle\Controller\Admin;

use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminCrud;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
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
use Tourze\HotelContractBundle\Entity\DailyInventory;
use Tourze\HotelContractBundle\Enum\DailyInventoryStatusEnum;

/**
 * 房型库存管理控制器
 *
 * @extends AbstractCrudController<DailyInventory>
 */
#[AdminCrud(routePath: '/hotel/room-type-inventory', routeName: 'hotel_room_type_inventory')]
final class RoomTypeInventoryCrudController extends AbstractCrudController
{
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
            ->showEntityActionsInlined()
        ;
    }

    public function configureActions(Actions $actions): Actions
    {
        $batchCreateInventory = Action::new('batchCreateInventory', '批量创建库存')
            ->linkToRoute('admin_room_type_inventory_batch_create')
            ->createAsGlobalAction()
            ->setIcon('fa fa-plus-circle')
        ;

        $generateAllContractInventory = Action::new('generateAllContractInventory', '一键生成所有合同库存')
            ->linkToRoute('admin_room_type_inventory_generate_all_contract')
            ->createAsGlobalAction()
            ->setIcon('fa fa-magic')
        ;

        $batchPriceUpdate = Action::new('batchPriceUpdate', '批量更新价格')
            ->linkToRoute('admin_room_type_inventory_batch_price_update')
            ->createAsGlobalAction()
            ->setIcon('fa fa-dollar-sign')
        ;

        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $batchCreateInventory)
            ->add(Crud::PAGE_INDEX, $generateAllContractInventory)
            ->add(Crud::PAGE_INDEX, $batchPriceUpdate)
        ;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('roomType', '房型'))
            ->add(EntityFilter::new('hotel', '酒店'))
            ->add(EntityFilter::new('contract', '合同'))
            ->add(DateTimeFilter::new('date', '日期'))
            ->add('isReserved')
            ->add('status')
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield from $this->getBasicFields();
        yield from $this->getPriceFields($pageName);
        yield from $this->getStatusFields($pageName);
        yield from $this->getTimestampFields();
    }

    /**
     * @return \Generator<FieldInterface>
     */
    private function getBasicFields(): iterable
    {
        yield IdField::new('id', 'ID')->hideOnForm()->hideOnIndex()->setMaxLength(9999);

        yield AssociationField::new('roomType', '房型')
            ->setRequired(true)
            ->setFormTypeOption('query_builder', function ($repository) {
                return $repository->createQueryBuilder('rt')
                    ->join('rt.hotel', 'h')
                    ->addSelect('h')
                    ->orderBy('h.name', 'ASC')
                    ->addOrderBy('rt.name', 'ASC')
                ;
            })
            ->setFormTypeOption('group_by', 'hotel.name')
            ->setHelp('选择房型后，酒店将自动设置')
        ;

        yield AssociationField::new('hotel', '酒店')
            ->setRequired(true)
            ->hideOnForm()
        ;

        yield DateField::new('date', '日期')->setRequired(true);

        yield TextField::new('code', '库存编码')->hideOnForm();

        yield AssociationField::new('contract', '合同')
            ->setRequired(true)
            ->setFormTypeOption('query_builder', function ($repository) {
                return $repository->createQueryBuilder('c')
                    ->orderBy('c.contractNo', 'ASC')
                ;
            })
        ;
    }

    /**
     * @return \Generator<FieldInterface>
     */
    private function getPriceFields(string $pageName): iterable
    {
        yield MoneyField::new('costPrice', '成本价')
            ->setCurrency('CNY')
            ->setStoredAsCents(false)
        ;

        yield MoneyField::new('sellingPrice', '销售价')
            ->setCurrency('CNY')
            ->setStoredAsCents(false)
        ;

        if (Crud::PAGE_INDEX !== $pageName) {
            yield TextField::new('priceAdjustReason', '价格调整原因');

            if (Crud::PAGE_DETAIL === $pageName) {
                yield PercentField::new('profitRate', '利润率')
                    ->setNumDecimals(2)
                    ->setHelp('基于成本价和销售价自动计算')
                ;
            }
        } else {
            yield PercentField::new('profitRate', '利润率')
                ->setNumDecimals(2)
                ->setFormattedValue('html')
                ->formatValue(function ($value, $entity) {
                    return $this->formatProfitRateForIndex($entity);
                })
            ;
        }
    }

    /**
     * @return \Generator<FieldInterface>
     */
    private function getStatusFields(string $pageName): iterable
    {
        yield BooleanField::new('isReserved', '预留')
            ->renderAsSwitch(Crud::PAGE_EDIT === $pageName)
        ;

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
            ])
        ;
    }

    /**
     * @return \Generator<FieldInterface>
     */
    private function getTimestampFields(): iterable
    {
        yield DateTimeField::new('createTime', '创建时间')->hideOnForm();
        yield DateTimeField::new('updateTime', '更新时间')->hideOnForm()->hideOnIndex();
    }

    private function formatProfitRateForIndex(mixed $entity): string
    {
        if (!$entity instanceof DailyInventory) {
            return '<span class="badge bg-secondary">0.00%</span>';
        }

        $costPrice = (float) $entity->getCostPrice();
        $sellingPrice = (float) $entity->getSellingPrice();

        if ($costPrice <= 0) {
            return '<span class="badge bg-secondary">0.00%</span>';
        }

        $profit = $sellingPrice - $costPrice;
        $profitRate = ($profit / $costPrice) * 100;

        $color = $this->getProfitRateColor($profitRate);

        return sprintf('<span class="badge bg-%s">%.2f%%</span>', $color, $profitRate);
    }

    private function getProfitRateColor(float $profitRate): string
    {
        if ($profitRate >= 30) {
            return 'success';
        }
        if ($profitRate >= 15) {
            return 'info';
        }
        if ($profitRate < 0) {
            return 'danger';
        }

        return 'secondary';
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
        $queryBuilder->leftJoin($rootAlias . '.roomType', 'roomType')
            ->leftJoin($rootAlias . '.hotel', 'hotel')
            ->leftJoin($rootAlias . '.contract', 'contract')
            ->addSelect('roomType', 'hotel', 'contract')
        ;

        return $queryBuilder;
    }
}
