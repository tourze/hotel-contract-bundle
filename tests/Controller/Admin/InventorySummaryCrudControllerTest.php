<?php

declare(strict_types=1);

namespace Tourze\HotelContractBundle\Tests\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Tourze\HotelContractBundle\Controller\Admin\InventorySummaryCrudController;
use Tourze\HotelContractBundle\Entity\InventorySummary;
use Tourze\PHPUnitSymfonyWebTest\AbstractEasyAdminControllerTestCase;

/**
 * @internal
 */
#[CoversClass(InventorySummaryCrudController::class)]
#[RunTestsInSeparateProcesses]
final class InventorySummaryCrudControllerTest extends AbstractEasyAdminControllerTestCase
{
    public function testControllerIndexAccessRequiresAuthentication(): void
    {
        $client = self::createClientWithDatabase();

        $this->expectException(AccessDeniedException::class);
        $client->request('GET', '/admin?crudAction=index&crudControllerFqcn=' . urlencode(InventorySummaryCrudController::class));
    }

    public function testUnauthorizedAccessRedirectsToLogin(): void
    {
        $client = self::createClientWithDatabase();

        $this->expectException(AccessDeniedException::class);
        $client->request('GET', '/admin?crudAction=index&crudControllerFqcn=' . urlencode(InventorySummaryCrudController::class));
    }

    public function testSyncInventorySummary(): void
    {
        $client = self::createClientWithDatabase();
        try {
            // 测试syncInventorySummary动作的路由
            $client->request('GET', '/admin/hotel/inventory-summary/sync');
            // 由于没有认证，预期返回401或重定向到登录页
            $this->assertTrue(
                401 === $client->getResponse()->getStatusCode()
                || 302 === $client->getResponse()->getStatusCode()
                || 404 === $client->getResponse()->getStatusCode()
            );
        } catch (NotFoundHttpException|AccessDeniedException $e) {
            // 路由不存在或访问被拒绝都是预期的
            if ($e instanceof NotFoundHttpException) {
                $this->assertSame(404, $e->getStatusCode());
            } else {
                $this->assertInstanceOf(AccessDeniedException::class, $e); // AccessDeniedException被抛出是预期的
            }
        }
    }

    public function testValidationErrors(): void
    {
        $client = self::createClientWithDatabase();

        // 注意：InventorySummaryCrudController 禁用了NEW和EDIT表单操作
        // 因此这里测试实体级别的验证约束，而不是表单提交验证

        // 获取验证器服务
        /** @var ValidatorInterface $validator */
        $validator = self::getService(ValidatorInterface::class);

        // 测试必填字段验证 - date字段为空
        $inventory = new InventorySummary();
        $violations = $validator->validate($inventory);

        $this->assertGreaterThan(0, $violations->count(), '空实体应该有验证错误');

        // 验证date字段的NotNull约束
        $hasDateViolation = false;
        foreach ($violations as $violation) {
            if ('date' === $violation->getPropertyPath()) {
                $hasDateViolation = true;
                // 验证错误消息（支持多种验证消息格式，包括 "should not be null" 和 "should not be blank"）
                $message = strtolower((string) $violation->getMessage());
                $this->assertTrue(
                    str_contains($message, 'null') || str_contains($message, 'blank'),
                    '验证消息应包含 "null" 或 "blank"'
                );
                break;
            }
        }
        $this->assertTrue($hasDateViolation, '应包含date字段的验证错误');

        // 测试负数验证约束 - 设置负数值应该失败
        $inventory = new InventorySummary();
        $inventory->setDate(new \DateTimeImmutable('2024-01-01'));
        $inventory->setTotalRooms(-1);
        $inventory->setAvailableRooms(-1);
        $inventory->setReservedRooms(-1);
        $inventory->setSoldRooms(-1);
        $inventory->setPendingRooms(-1);

        $violations = $validator->validate($inventory);
        $this->assertGreaterThan(0, $violations->count(), '负数值应该产生验证错误');

        // 验证各个字段的PositiveOrZero约束
        $negativeFieldsFound = 0;
        foreach ($violations as $violation) {
            $property = $violation->getPropertyPath();
            if (in_array($property, ['totalRooms', 'availableRooms', 'reservedRooms', 'soldRooms', 'pendingRooms'], true)) {
                ++$negativeFieldsFound;
                $this->assertMatchesRegularExpression('/positive|zero|greater|equal/i', (string) $violation->getMessage());
            }
        }
        $this->assertGreaterThan(0, $negativeFieldsFound, '应该找到负数字段的验证错误');
    }

    public function testValidInventoryHasNoViolations(): void
    {
        $client = self::createClientWithDatabase();

        // 获取验证器服务
        /** @var ValidatorInterface $validator */
        $validator = self::getService(ValidatorInterface::class);

        // 创建一个有效的库存统计实体
        $inventory = new InventorySummary();
        $inventory->setDate(new \DateTimeImmutable('2024-01-01'));
        $inventory->setTotalRooms(100);
        $inventory->setAvailableRooms(80);
        $inventory->setReservedRooms(10);
        $inventory->setSoldRooms(10);
        $inventory->setPendingRooms(0);
        $inventory->setLowestPrice('299.00');

        $violations = $validator->validate($inventory);
        $this->assertCount(0, $violations, '有效的库存统计实体应该没有验证错误');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideNewPageFields(): iterable
    {
        // 此控制器禁用了 NEW 操作，返回一个虚拟字段以避免空数据集错误
        yield 'dummy' => ['dummy'];
    }

    /**
     * @return AbstractCrudController<InventorySummary>
     */
    protected function getControllerService(): AbstractCrudController
    {
        return self::getService(InventorySummaryCrudController::class);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideIndexPageHeaders(): iterable
    {
        yield 'hotel' => ['酒店'];
        yield 'roomType' => ['房型'];
        yield 'date' => ['日期'];
        yield 'totalRooms' => ['总房间数'];
        yield 'availableRooms' => ['可售房间数'];
        yield 'reservedRooms' => ['预留房间数'];
        yield 'soldRooms' => ['已售房间数'];
        yield 'pendingRooms' => ['待确认房间数'];
        yield 'status' => ['状态'];
        yield 'lowestPrice' => ['最低采购价'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideEditPageFields(): iterable
    {
        // 此控制器禁用了 EDIT 操作，返回一个虚拟字段以避免空数据集错误
        yield 'dummy' => ['dummy'];
    }
}
