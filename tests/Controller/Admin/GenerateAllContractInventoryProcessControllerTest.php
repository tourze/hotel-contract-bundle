<?php

declare(strict_types=1);

namespace Tourze\HotelContractBundle\Tests\Controller\Admin;

use PHPUnit\Framework\TestCase;
use Tourze\HotelContractBundle\Controller\Admin\GenerateAllContractInventoryProcessController;

class GenerateAllContractInventoryProcessControllerTest extends TestCase
{
    public function testControllerClassExists(): void
    {
        $this->assertTrue(class_exists(GenerateAllContractInventoryProcessController::class));
    }
} 