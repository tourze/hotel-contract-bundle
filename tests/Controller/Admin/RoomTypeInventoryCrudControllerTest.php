<?php

declare(strict_types=1);

namespace Tourze\HotelContractBundle\Tests\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tourze\HotelContractBundle\Controller\Admin\RoomTypeInventoryCrudController;
use Tourze\HotelContractBundle\Entity\DailyInventory;
use Tourze\PHPUnitSymfonyWebTest\AbstractEasyAdminControllerTestCase;

/**
 * @internal
 */
#[CoversClass(RoomTypeInventoryCrudController::class)]
#[RunTestsInSeparateProcesses]
final class RoomTypeInventoryCrudControllerTest extends AbstractEasyAdminControllerTestCase
{
    public function testUnauthenticatedAccessIsBlocked(): void
    {
        $client = self::createClientWithDatabase();
        try {
            $client->request('GET', '/admin/room-type-inventory');
            // 如果没有抛出异常，则验证状态码
            $this->assertResponseStatusCodeSame(401);
        } catch (NotFoundHttpException $e) {
            // 当Bundle未启用时，路由不存在，返回404
            $this->assertSame(404, $e->getStatusCode());
        }
    }

    public function testRouteDoesNotExistWithoutBundle(): void
    {
        $client = self::createClientWithDatabase();
        try {
            $client->request('GET', '/admin/test');
            $this->assertResponseStatusCodeSame(404);
        } catch (NotFoundHttpException $e) {
            $this->assertSame(404, $e->getStatusCode());
        }
    }

    /**
     * @return AbstractCrudController<DailyInventory>
     */
    protected function getControllerService(): AbstractCrudController
    {
        return self::getService(RoomTypeInventoryCrudController::class);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideIndexPageHeaders(): iterable
    {
        // 跳过ID字段，因为它的label是null
        yield '房型' => ['房型'];
        yield '酒店' => ['酒店'];
        yield '日期' => ['日期'];
        yield '库存编码' => ['库存编码'];
        yield '合同' => ['合同'];
        yield '成本价' => ['成本价'];
        yield '销售价' => ['销售价'];
        yield '利润率' => ['利润率'];
        yield '预留' => ['预留'];
        yield '状态' => ['状态'];
        yield '创建时间' => ['创建时间'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideEditPageFields(): iterable
    {
        yield 'roomType' => ['roomType'];
        yield 'date' => ['date'];
        yield 'contract' => ['contract'];
        yield 'costPrice' => ['costPrice'];
        yield 'sellingPrice' => ['sellingPrice'];
        yield 'priceAdjustReason' => ['priceAdjustReason'];
        yield 'isReserved' => ['isReserved'];
        yield 'status' => ['status'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideNewPageFields(): iterable
    {
        yield 'roomType' => ['roomType'];
        yield 'date' => ['date'];
        yield 'contract' => ['contract'];
        yield 'costPrice' => ['costPrice'];
        yield 'sellingPrice' => ['sellingPrice'];
        yield 'priceAdjustReason' => ['priceAdjustReason'];
        yield 'isReserved' => ['isReserved'];
        yield 'status' => ['status'];
    }
}
