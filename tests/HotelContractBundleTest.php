<?php

namespace Tourze\HotelContractBundle\Tests;

use PHPUnit\Framework\TestCase;
use Tourze\BundleDependency\BundleDependencyInterface;
use Tourze\HotelContractBundle\HotelContractBundle;
use Tourze\HotelProfileBundle\HotelProfileBundle;

class HotelContractBundleTest extends TestCase
{
    private HotelContractBundle $bundle;

    protected function setUp(): void
    {
        $this->bundle = new HotelContractBundle();
    }

    public function test_implementsBundleDependencyInterface(): void
    {
        $this->assertInstanceOf(BundleDependencyInterface::class, $this->bundle);
    }

    public function test_getBundleDependencies_returnsCorrectDependencies(): void
    {
        $dependencies = HotelContractBundle::getBundleDependencies();
        
        $this->assertIsArray($dependencies);
        $this->assertArrayHasKey(HotelProfileBundle::class, $dependencies);
        $this->assertSame(['all' => true], $dependencies[HotelProfileBundle::class]);
    }

    public function test_getBundleDependencies_isStaticMethod(): void
    {
        $this->assertTrue(method_exists(HotelContractBundle::class, 'getBundleDependencies'));
        
        $reflection = new \ReflectionMethod(HotelContractBundle::class, 'getBundleDependencies');
        $this->assertTrue($reflection->isStatic());
    }

    public function test_getBundleDependencies_returnsNonEmptyArray(): void
    {
        $dependencies = HotelContractBundle::getBundleDependencies();
        
        $this->assertNotEmpty($dependencies);
        $this->assertCount(2, $dependencies);
    }
} 