<?php

namespace Tourze\HotelContractBundle\Tests\Enum;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use Tourze\HotelContractBundle\Enum\InventorySummaryStatusEnum;
use Tourze\PHPUnitEnum\AbstractEnumTestCase;

/**
 * @internal
 */
#[CoversClass(InventorySummaryStatusEnum::class)]
final class InventorySummaryStatusEnumTest extends AbstractEnumTestCase
{
    #[TestWith([InventorySummaryStatusEnum::NORMAL, 'normal', '正常'])]
    #[TestWith([InventorySummaryStatusEnum::WARNING, 'warning', '预警'])]
    #[TestWith([InventorySummaryStatusEnum::SOLD_OUT, 'sold_out', '售罄'])]
    public function testEnumValueAndLabel(InventorySummaryStatusEnum $enum, string $expectedValue, string $expectedLabel): void
    {
        $this->assertSame($expectedValue, $enum->value);
        $this->assertSame($expectedLabel, $enum->getLabel());
    }

    public function testAllCasesExist(): void
    {
        $cases = InventorySummaryStatusEnum::cases();

        $this->assertCount(3, $cases);
        $this->assertContains(InventorySummaryStatusEnum::NORMAL, $cases);
        $this->assertContains(InventorySummaryStatusEnum::WARNING, $cases);
        $this->assertContains(InventorySummaryStatusEnum::SOLD_OUT, $cases);
    }

    #[TestWith(['normal', InventorySummaryStatusEnum::NORMAL])]
    #[TestWith(['warning', InventorySummaryStatusEnum::WARNING])]
    #[TestWith(['sold_out', InventorySummaryStatusEnum::SOLD_OUT])]
    public function testFromReturnsCorrectEnum(string $value, InventorySummaryStatusEnum $expectedEnum): void
    {
        $this->assertSame($expectedEnum, InventorySummaryStatusEnum::from($value));
    }

    #[TestWith(['invalid_value'])]
    #[TestWith([''])]
    #[TestWith(['null'])]
    #[TestWith(['unknown'])]
    #[TestWith(['error'])]
    #[TestWith(['danger'])]
    public function testFromThrowsValueErrorWithInvalidValue(string $invalidValue): void
    {
        $this->expectException(\ValueError::class);
        InventorySummaryStatusEnum::from($invalidValue);
    }

    #[TestWith(['normal', InventorySummaryStatusEnum::NORMAL])]
    #[TestWith(['warning', InventorySummaryStatusEnum::WARNING])]
    #[TestWith(['sold_out', InventorySummaryStatusEnum::SOLD_OUT])]
    public function testTryFromReturnsEnumWithValidValue(string $value, InventorySummaryStatusEnum $expectedEnum): void
    {
        $this->assertSame($expectedEnum, InventorySummaryStatusEnum::tryFrom($value));
    }

    #[TestWith(['invalid_value'])]
    #[TestWith([''])]
    #[TestWith(['null'])]
    #[TestWith(['unknown'])]
    #[TestWith(['error'])]
    #[TestWith(['danger'])]
    public function testTryFromReturnsNullWithInvalidValue(string $invalidValue): void
    {
        $this->assertNull(InventorySummaryStatusEnum::tryFrom($invalidValue));
    }

    public function testValuesAreUnique(): void
    {
        $values = array_map(fn (InventorySummaryStatusEnum $case) => $case->value, InventorySummaryStatusEnum::cases());
        $uniqueValues = array_unique($values);

        $this->assertCount(count($values), $uniqueValues, 'All enum values must be unique');
    }

    public function testLabelsAreUnique(): void
    {
        $labels = array_map(fn (InventorySummaryStatusEnum $case) => $case->getLabel(), InventorySummaryStatusEnum::cases());
        $uniqueLabels = array_unique($labels);

        $this->assertCount(count($labels), $uniqueLabels, 'All enum labels must be unique');
    }

    public function testToArray(): void
    {
        $result = InventorySummaryStatusEnum::NORMAL->toArray();

        // 验证返回的数组包含预期的键值对
        $this->assertArrayHasKey('value', $result);
        $this->assertArrayHasKey('label', $result);
        $this->assertSame('normal', $result['value']);
        $this->assertSame('正常', $result['label']);
    }
}
