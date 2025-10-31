<?php

namespace Tourze\HotelContractBundle\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Tourze\HotelContractBundle\DependencyInjection\HotelContractExtension;
use Tourze\PHPUnitSymfonyUnitTest\AbstractDependencyInjectionExtensionTestCase;

/**
 * @internal
 */
#[CoversClass(HotelContractExtension::class)]
final class HotelContractExtensionTest extends AbstractDependencyInjectionExtensionTestCase
{
    private HotelContractExtension $extension;

    private ContainerBuilder $container;

    protected function setUp(): void
    {
        parent::setUp();
        $this->extension = new HotelContractExtension();
        $this->container = new ContainerBuilder();
        $this->container->setParameter('kernel.environment', 'test');
    }

    public function testLoadDoesNotThrowExceptionWithEmptyConfigs(): void
    {
        $this->expectNotToPerformAssertions();
        $this->extension->load([], $this->container);
    }

    public function testLoadDoesNotThrowExceptionWithConfigs(): void
    {
        $configs = [
            ['some_config' => 'value'],
        ];

        $this->expectNotToPerformAssertions();
        $this->extension->load($configs, $this->container);
    }

    public function testLoadReturnsVoid(): void
    {
        $this->extension->load([], $this->container);
        $this->expectNotToPerformAssertions();
    }

    public function testLoadAcceptsArrayOfConfigs(): void
    {
        $configs = [
            ['config1' => 'value1'],
            ['config2' => 'value2'],
        ];

        $this->expectNotToPerformAssertions();
        $this->extension->load($configs, $this->container);
    }

    public function testExtensionAlias(): void
    {
        $this->assertEquals('hotel_contract', $this->extension->getAlias());
    }
}
