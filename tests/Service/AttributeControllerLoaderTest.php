<?php

declare(strict_types=1);

namespace Tourze\HotelContractBundle\Tests\Service;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\RouteCollection;
use Tourze\HotelContractBundle\Service\AttributeControllerLoader;

class AttributeControllerLoaderTest extends TestCase
{
    private AttributeControllerLoader $loader;

    protected function setUp(): void
    {
        $this->loader = new AttributeControllerLoader();
    }

    public function testLoaderCanBeInstantiated(): void
    {
        $this->assertInstanceOf(AttributeControllerLoader::class, $this->loader);
    }

    public function testLoadReturnsRouteCollection(): void
    {
        $result = $this->loader->load('test', 'attribute_controller');

        $this->assertInstanceOf(RouteCollection::class, $result);
    }

    public function testSupportsReturnsTrueForAttributeControllerType(): void
    {
        $this->assertTrue($this->loader->supports('test', 'attribute_controller'));
    }

    public function testSupportsReturnsFalseForOtherTypes(): void
    {
        $this->assertFalse($this->loader->supports('test', 'other_type'));
        $this->assertFalse($this->loader->supports('test', null));
    }
} 