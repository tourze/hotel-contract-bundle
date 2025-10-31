<?php

declare(strict_types=1);

namespace Tourze\HotelContractBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Routing\RouteCollection;
use Tourze\HotelContractBundle\Service\AttributeControllerLoader;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(AttributeControllerLoader::class)]
#[RunTestsInSeparateProcesses]
final class AttributeControllerLoaderTest extends AbstractIntegrationTestCase
{
    protected function onSetUp(): void
    {
        // Setup for service tests
    }

    private function getAttributeControllerLoader(): AttributeControllerLoader
    {
        return self::getService(AttributeControllerLoader::class);
    }

    public function testLoaderCanBeInstantiated(): void
    {
        $loader = $this->getAttributeControllerLoader();
        $this->assertInstanceOf(AttributeControllerLoader::class, $loader);
    }

    public function testLoadReturnsRouteCollection(): void
    {
        $result = $this->getAttributeControllerLoader()->load('test', 'attribute_controller');

        $this->assertInstanceOf(RouteCollection::class, $result);
    }

    public function testSupportsReturnsTrueForAttributeControllerType(): void
    {
        $this->assertTrue($this->getAttributeControllerLoader()->supports('test', 'attribute_controller'));
    }

    public function testSupportsReturnsFalseForOtherTypes(): void
    {
        $this->assertFalse($this->getAttributeControllerLoader()->supports('test', 'other_type'));
        $this->assertFalse($this->getAttributeControllerLoader()->supports('test', null));
    }

    public function testAutoloadMethodExists(): void
    {
        $reflection = new \ReflectionClass($this->getAttributeControllerLoader());
        $this->assertTrue($reflection->hasMethod('autoload'));
    }
}
