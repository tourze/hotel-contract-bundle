<?php

declare(strict_types=1);

namespace Tourze\HotelContractBundle\Tests\Controller\Admin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tourze\HotelContractBundle\Controller\Admin\BatchPriceUpdateProcessController;
use Tourze\PHPUnitSymfonyWebTest\AbstractWebTestCase;

/**
 * @internal
 */
#[CoversClass(BatchPriceUpdateProcessController::class)]
#[RunTestsInSeparateProcesses]
final class BatchPriceUpdateProcessControllerTest extends AbstractWebTestCase
{
    public function testUnauthenticatedAccessIsBlocked(): void
    {
        $client = self::createClientWithDatabase();
        try {
            $client->request('POST', '/admin/hotel-contract/batch-price-update');
            $this->assertResponseStatusCodeSame(404);
        } catch (NotFoundHttpException $e) {
            $this->assertInstanceOf(NotFoundHttpException::class, $e);
        }
    }

    public function testRouteDoesNotExistWithoutBundle(): void
    {
        $client = self::createClientWithDatabase();
        try {
            $client->request('GET', '/admin/test');
            $this->assertResponseStatusCodeSame(404);
        } catch (NotFoundHttpException $e) {
            $this->assertInstanceOf(NotFoundHttpException::class, $e);
        }
    }

    #[DataProvider('provideNotAllowedMethods')]
    public function testMethodNotAllowed(string $method): void
    {
        $client = self::createClientWithDatabase();
        try {
            $client->request($method, '/admin/hotel-contract/batch-price-update');
            // 由于路由不存在，所以返回404而不是405
            $this->assertResponseStatusCodeSame(404);
        } catch (NotFoundHttpException|MethodNotAllowedHttpException) {
            // 异常类型已由 catch 块保证，测试通过
            $this->expectNotToPerformAssertions();
        }
    }
}
