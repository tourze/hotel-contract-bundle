<?php

namespace Tourze\HotelContractBundle\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Tourze\HotelContractBundle\DependencyInjection\HotelContractExtension;

class HotelContractExtensionTest extends TestCase
{
    private HotelContractExtension $extension;
    private ContainerBuilder $container;

    protected function setUp(): void
    {
        $this->extension = new HotelContractExtension();
        $this->container = new ContainerBuilder();
    }

    public function test_extendsSymfonyExtension(): void
    {
        $this->assertInstanceOf(Extension::class, $this->extension);
    }

    public function test_load_doesNotThrowException_withEmptyConfigs(): void
    {
        $this->expectNotToPerformAssertions();
        
        $this->extension->load([], $this->container);
    }

    public function test_load_doesNotThrowException_withConfigs(): void
    {
        $configs = [
            ['some_config' => 'value']
        ];
        
        $this->expectNotToPerformAssertions();
        
        $this->extension->load($configs, $this->container);
    }

    public function test_load_returnsVoid(): void
    {
        $this->extension->load([], $this->container);
        
        // load() method returns void, so we just check it doesn't throw exception
        $this->expectNotToPerformAssertions();
    }

    public function test_load_acceptsArrayOfConfigs(): void
    {
        $configs = [
            ['config1' => 'value1'],
            ['config2' => 'value2']
        ];
        
        $this->expectNotToPerformAssertions();
        
        $this->extension->load($configs, $this->container);
    }
} 