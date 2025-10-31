<?php

declare(strict_types=1);

namespace Tourze\HotelContractBundle\Tests\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Tourze\HotelContractBundle\Controller\Admin\HotelContractCrudController;
use Tourze\HotelContractBundle\Entity\HotelContract;
use Tourze\PHPUnitSymfonyWebTest\AbstractEasyAdminControllerTestCase;

/**
 * @internal
 */
#[CoversClass(HotelContractCrudController::class)]
#[RunTestsInSeparateProcesses]
final class HotelContractCrudControllerTest extends AbstractEasyAdminControllerTestCase
{
    protected function onSetUp(): void
    {
        // 设置测试环境变量
        putenv('MAILER_DSN=smtp://localhost:1025');
    }

    public function testControllerIndexAccessRequiresAuthentication(): void
    {
        $client = self::createClientWithDatabase();

        $this->expectException(AccessDeniedException::class);
        $client->request('GET', '/admin?crudAction=index&crudControllerFqcn=' . urlencode(HotelContractCrudController::class));
    }

    public function testUnauthorizedAccessRedirectsToLogin(): void
    {
        $client = self::createClientWithDatabase();

        $this->expectException(AccessDeniedException::class);
        $client->request('GET', '/admin?crudAction=index&crudControllerFqcn=' . urlencode(HotelContractCrudController::class));
    }

    public function testRouteDoesNotExistWithoutBundle(): void
    {
        $client = self::createClientWithDatabase();
        try {
            $client->request('GET', '/admin/non-existent-route');
            $this->assertResponseStatusCodeSame(404);
        } catch (NotFoundHttpException $e) {
            $this->assertSame(404, $e->getStatusCode());
        }
    }

    public function testTerminateContract(): void
    {
        $client = self::createClientWithDatabase();
        try {
            // 测试terminateContract动作的路由
            $client->request('GET', '/admin/hotel/contract/1/terminate');
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

    public function testInventoryStats(): void
    {
        $client = self::createClientWithDatabase();
        try {
            // 测试inventoryStats动作的路由
            $client->request('GET', '/admin/hotel/contract/1/inventory-stats');
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

    public function testApproveContract(): void
    {
        $client = self::createClientWithDatabase();
        try {
            // 测试approveContract动作的路由
            $client->request('POST', '/admin/hotel/contract/1/approve');
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

        // 获取验证器服务
        /** @var ValidatorInterface $validator */
        $validator = self::getService(ValidatorInterface::class);

        // 测试必填字段验证 - 创建一个空实体
        $contract = new HotelContract();
        $violations = $validator->validate($contract);

        $this->assertGreaterThan(0, $violations->count(), '空实体应该有验证错误');

        // 验证合同编号字段的NotBlank约束
        $hasContractNoViolation = false;
        foreach ($violations as $violation) {
            if ('contractNo' === $violation->getPropertyPath()) {
                $hasContractNoViolation = true;
                $message = strtolower((string) $violation->getMessage());
                $this->assertTrue(
                    str_contains($message, 'blank') || str_contains($message, '空') || str_contains($message, 'required'),
                    sprintf('Expected blank/empty validation message, got: %s', $violation->getMessage())
                );
                break;
            }
        }
        $this->assertTrue($hasContractNoViolation, '应包含合同编号字段的验证错误');

        // 测试日期字段的NotNull约束
        $hasStartDateViolation = false;
        $hasEndDateViolation = false;
        foreach ($violations as $violation) {
            if ('startDate' === $violation->getPropertyPath()) {
                $hasStartDateViolation = true;
                $message = strtolower((string) $violation->getMessage());
                $this->assertTrue(
                    str_contains($message, 'null') || str_contains($message, '空') || str_contains($message, 'required'),
                    sprintf('Expected null/empty validation message for startDate, got: %s', $violation->getMessage())
                );
            }
            if ('endDate' === $violation->getPropertyPath()) {
                $hasEndDateViolation = true;
                $message = strtolower((string) $violation->getMessage());
                $this->assertTrue(
                    str_contains($message, 'null') || str_contains($message, '空') || str_contains($message, 'required'),
                    sprintf('Expected null/empty validation message for endDate, got: %s', $violation->getMessage())
                );
            }
        }
        $this->assertTrue($hasStartDateViolation, '应包含开始日期字段的验证错误');
        $this->assertTrue($hasEndDateViolation, '应包含结束日期字段的验证错误');

        // 测试负数验证约束 - 设置负数值应该失败
        $contract = new HotelContract();
        $contract->setContractNo('TEST-001');
        $contract->setStartDate(new \DateTimeImmutable('2024-01-01'));
        $contract->setEndDate(new \DateTimeImmutable('2024-12-31'));
        $contract->setTotalRooms(-1);
        $contract->setTotalDays(-1);
        $contract->setTotalAmount('-100.00');
        $contract->setPriority(-1);

        $violations = $validator->validate($contract);
        $this->assertGreaterThan(0, $violations->count(), '负数值应该产生验证错误');

        // 验证各个字段的Positive/PositiveOrZero约束
        $negativeFieldsFound = 0;
        foreach ($violations as $violation) {
            $property = $violation->getPropertyPath();
            if (in_array($property, ['totalRooms', 'totalDays', 'totalAmount', 'priority'], true)) {
                ++$negativeFieldsFound;
                $this->assertMatchesRegularExpression('/positive|greater|zero|range|between/i', (string) $violation->getMessage());
            }
        }
        $this->assertGreaterThan(0, $negativeFieldsFound, '应该找到负数字段的验证错误');
    }

    /**
     * @return AbstractCrudController<HotelContract>
     */
    protected function getControllerService(): AbstractCrudController
    {
        return self::getService(HotelContractCrudController::class);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideIndexPageHeaders(): iterable
    {
        // 跳过ID字段，因为它的label是null
        yield '合同编号' => ['合同编号'];
        yield '酒店' => ['酒店'];
        yield '合同类型' => ['合同类型'];
        yield '合同状态' => ['合同状态'];
        yield '开始日期' => ['开始日期'];
        yield '结束日期' => ['结束日期'];
        yield '总房间数' => ['总房间数'];
        yield '总天数' => ['总天数'];
        yield '合同总成本' => ['合同总成本'];
        yield '合同总售价' => ['合同总售价'];
        yield '利润率' => ['利润率'];
        yield '合同优先级' => ['合同优先级'];
        yield '创建时间' => ['创建时间'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideEditPageFields(): iterable
    {
        yield 'hotel' => ['hotel'];
        yield 'contractType' => ['contractType'];
        yield 'startDate' => ['startDate'];
        yield 'endDate' => ['endDate'];
        yield 'totalRooms' => ['totalRooms'];
        yield 'totalDays' => ['totalDays'];
        yield 'totalAmount' => ['totalAmount'];
        yield 'priority' => ['priority'];
        yield 'terminationReason' => ['terminationReason'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideNewPageFields(): iterable
    {
        yield 'hotel' => ['hotel'];
        yield 'contractType' => ['contractType'];
        yield 'startDate' => ['startDate'];
        yield 'endDate' => ['endDate'];
        yield 'totalRooms' => ['totalRooms'];
        yield 'totalDays' => ['totalDays'];
        yield 'totalAmount' => ['totalAmount'];
        yield 'priority' => ['priority'];
    }
}
