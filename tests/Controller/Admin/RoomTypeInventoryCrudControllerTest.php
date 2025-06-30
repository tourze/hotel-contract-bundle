<?php

declare(strict_types=1);

namespace Tourze\HotelContractBundle\Tests\Controller\Admin;

use PHPUnit\Framework\TestCase;
use Tourze\HotelContractBundle\Controller\Admin\RoomTypeInventoryCrudController;

class RoomTypeInventoryCrudControllerTest extends TestCase
{
    public function testControllerClassExists(): void
    {
        $this->assertTrue(class_exists(RoomTypeInventoryCrudController::class));
    }

    public function testControllerHasRequiredMethods(): void
    {
        $reflection = new \ReflectionClass(RoomTypeInventoryCrudController::class);
        
        $this->assertTrue($reflection->hasMethod('getEntityFqcn'));
        $this->assertTrue($reflection->hasMethod('configureCrud'));
        $this->assertTrue($reflection->hasMethod('configureActions'));
        $this->assertTrue($reflection->hasMethod('configureFilters'));
        $this->assertTrue($reflection->hasMethod('configureFields'));
    }
} 