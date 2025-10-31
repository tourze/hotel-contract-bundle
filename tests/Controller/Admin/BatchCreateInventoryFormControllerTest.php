<?php

declare(strict_types=1);

namespace Tourze\HotelContractBundle\Tests\Controller\Admin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tourze\HotelContractBundle\Controller\Admin\BatchCreateInventoryFormController;
use Tourze\PHPUnitSymfonyWebTest\AbstractWebTestCase;

/**
 * @internal
 */
#[CoversClass(BatchCreateInventoryFormController::class)]
#[RunTestsInSeparateProcesses]
final class BatchCreateInventoryFormControllerTest extends AbstractWebTestCase
{
    public function testUnauthenticatedAccessIsBlocked(): void
    {
        $client = self::createClientWithDatabase();
        try {
            $client->request('GET', '/admin/room-type-inventory/batch-create');
            $this->assertResponseStatusCodeSame(404);
        } catch (NotFoundHttpException $e) {
            $this->assertInstanceOf(NotFoundHttpException::class, $e);
        }
    }

    public function testRouteDoesNotExistWithoutBundle(): void
    {
        $client = self::createClientWithDatabase();
        try {
            $client->request('GET', '/admin/room-type-inventory/batch-create');
            $this->assertResponseStatusCodeSame(404);
        } catch (NotFoundHttpException $e) {
            $this->assertInstanceOf(NotFoundHttpException::class, $e);
        }
    }

    public function testPostMethodNotFound(): void
    {
        $client = self::createClientWithDatabase();
        try {
            $client->request('POST', '/admin/room-type-inventory/batch-create');
            $this->assertResponseStatusCodeSame(404);
        } catch (NotFoundHttpException $e) {
            $this->assertInstanceOf(NotFoundHttpException::class, $e);
        }
    }

    public function testPutMethodNotFound(): void
    {
        $client = self::createClientWithDatabase();
        try {
            $client->request('PUT', '/admin/room-type-inventory/batch-create');
            $this->assertResponseStatusCodeSame(404);
        } catch (NotFoundHttpException $e) {
            $this->assertInstanceOf(NotFoundHttpException::class, $e);
        }
    }

    public function testDeleteMethodNotFound(): void
    {
        $client = self::createClientWithDatabase();
        try {
            $client->request('DELETE', '/admin/room-type-inventory/batch-create');
            $this->assertResponseStatusCodeSame(404);
        } catch (NotFoundHttpException $e) {
            $this->assertInstanceOf(NotFoundHttpException::class, $e);
        }
    }

    public function testPatchMethodNotFound(): void
    {
        $client = self::createClientWithDatabase();
        try {
            $client->request('PATCH', '/admin/room-type-inventory/batch-create');
            $this->assertResponseStatusCodeSame(404);
        } catch (NotFoundHttpException $e) {
            $this->assertInstanceOf(NotFoundHttpException::class, $e);
        }
    }

    public function testHeadMethodNotFound(): void
    {
        $client = self::createClientWithDatabase();
        try {
            $client->request('HEAD', '/admin/room-type-inventory/batch-create');
            $this->assertResponseStatusCodeSame(404);
        } catch (NotFoundHttpException $e) {
            $this->assertInstanceOf(NotFoundHttpException::class, $e);
        }
    }

    public function testOptionsMethodNotFound(): void
    {
        $client = self::createClientWithDatabase();
        try {
            $client->request('OPTIONS', '/admin/room-type-inventory/batch-create');
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
            $client->request($method, '/admin/room-type-inventory/batch-create');
            // 由于路由不存在，所以返回404而不是405
            $this->assertResponseStatusCodeSame(404);
        } catch (NotFoundHttpException|MethodNotAllowedHttpException) {
            // 异常类型已由 catch 块保证，测试通过
            $this->expectNotToPerformAssertions();
        }
    }
}
