<?php

declare(strict_types=1);

namespace Tourze\HotelContractBundle\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\HotelContractBundle\HotelContractBundle;
use Tourze\PHPUnitSymfonyKernelTest\AbstractBundleTestCase;

/**
 * @internal
 */
#[CoversClass(HotelContractBundle::class)]
#[RunTestsInSeparateProcesses]
final class HotelContractBundleTest extends AbstractBundleTestCase
{
}
