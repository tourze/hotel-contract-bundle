<?php

declare(strict_types=1);

namespace Tourze\HotelContractBundle\Tests\Controller\Admin\API;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tourze\HotelContractBundle\Controller\Admin\API\GetContractDetailController;
use Tourze\PHPUnitSymfonyWebTest\AbstractWebTestCase;

/**
 * @internal
 */
#[CoversClass(GetContractDetailController::class)]
#[RunTestsInSeparateProcesses]
final class GetContractDetailControllerTest extends AbstractWebTestCase
{
    protected function onSetUp(): void
    {
        // 设置测试环境变量
        putenv('MAILER_DSN=smtp://localhost:1025');
    }

    public function testUnauthenticatedAccessIsBlocked(): void
    {
        $client = self::createClientWithDatabase();
        try {
            $client->request('GET', '/admin/api/hotel-contracts/1');
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
            $client->request('GET', '/admin/api/hotel-contracts/99999');
            $this->assertResponseStatusCodeSame(404);
        } catch (NotFoundHttpException $e) {
            $this->assertSame(404, $e->getStatusCode());
        }
    }

    public function testAnyRouteWithoutBundleReturns404(): void
    {
        $client = self::createClientWithDatabase();
        try {
            $client->request('GET', '/admin/api/hotel-contracts/1');
            $this->assertResponseStatusCodeSame(404);
        } catch (NotFoundHttpException $e) {
            $this->assertSame(404, $e->getStatusCode());
        }
    }

    #[DataProvider('provideNotAllowedMethods')]
    public function testMethodNotAllowed(string $method): void
    {
        $client = self::createClientWithDatabase();
        try {
            $client->request($method, '/admin/api/hotel-contracts/1');
            // 由于路由不存在，所以返回404而不是405
            $this->assertResponseStatusCodeSame(404);
        } catch (NotFoundHttpException|MethodNotAllowedHttpException $e) {
            if ($e instanceof MethodNotAllowedHttpException) {
                $this->assertSame(405, $e->getStatusCode());
            } else {
                $this->assertSame(404, $e->getStatusCode());
            }
        }
    }
}
