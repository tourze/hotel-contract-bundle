<?php

declare(strict_types=1);

namespace Tourze\HotelContractBundle\Tests\Controller\Admin;

use PHPUnit\Framework\TestCase;
use Tourze\HotelContractBundle\Controller\Admin\BatchCreateInventoryProcessController;

class BatchCreateInventoryProcessControllerTest extends TestCase
{
    public function testControllerClassExists(): void
    {
        $this->assertTrue(class_exists(BatchCreateInventoryProcessController::class));
    }
} 