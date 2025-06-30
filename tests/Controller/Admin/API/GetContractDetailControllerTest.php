<?php

declare(strict_types=1);

namespace Tourze\HotelContractBundle\Tests\Controller\Admin\API;

use PHPUnit\Framework\TestCase;
use Tourze\HotelContractBundle\Controller\Admin\API\GetContractDetailController;

class GetContractDetailControllerTest extends TestCase
{
    public function testControllerClassExists(): void
    {
        $this->assertTrue(class_exists(GetContractDetailController::class));
    }
} 