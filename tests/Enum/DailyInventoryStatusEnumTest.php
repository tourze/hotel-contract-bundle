<?php

namespace Tourze\HotelContractBundle\Tests\Enum;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use Tourze\HotelContractBundle\Enum\DailyInventoryStatusEnum;
use Tourze\PHPUnitEnum\AbstractEnumTestCase;

/**
 * @internal
 */
#[CoversClass(DailyInventoryStatusEnum::class)]
final class DailyInventoryStatusEnumTest extends AbstractEnumTestCase
{
    #[TestWith([DailyInventoryStatusEnum::AVAILABLE, 'available', '可售'])]
    #[TestWith([DailyInventoryStatusEnum::SOLD, 'sold', '已售'])]
    #[TestWith([DailyInventoryStatusEnum::PENDING, 'pending', '待确认'])]
    #[TestWith([DailyInventoryStatusEnum::RESERVED, 'reserved', '预留'])]
    #[TestWith([DailyInventoryStatusEnum::DISABLED, 'disabled', '禁用'])]
    #[TestWith([DailyInventoryStatusEnum::CANCELLED, 'cancelled', '已取消'])]
    #[TestWith([DailyInventoryStatusEnum::REFUNDED, 'refunded', '已退款'])]
    public function testEnumValueAndLabel(DailyInventoryStatusEnum $enum, string $expectedValue, string $expectedLabel): void
    {
        $this->assertSame($expectedValue, $enum->value);
        $this->assertSame($expectedLabel, $enum->getLabel());
    }

    public function testAllCasesExist(): void
    {
        $cases = DailyInventoryStatusEnum::cases();

        $this->assertCount(7, $cases);
        $this->assertContains(DailyInventoryStatusEnum::AVAILABLE, $cases);
        $this->assertContains(DailyInventoryStatusEnum::SOLD, $cases);
        $this->assertContains(DailyInventoryStatusEnum::PENDING, $cases);
        $this->assertContains(DailyInventoryStatusEnum::RESERVED, $cases);
        $this->assertContains(DailyInventoryStatusEnum::DISABLED, $cases);
        $this->assertContains(DailyInventoryStatusEnum::CANCELLED, $cases);
        $this->assertContains(DailyInventoryStatusEnum::REFUNDED, $cases);
    }

    #[TestWith(['available', DailyInventoryStatusEnum::AVAILABLE])]
    #[TestWith(['sold', DailyInventoryStatusEnum::SOLD])]
    #[TestWith(['pending', DailyInventoryStatusEnum::PENDING])]
    #[TestWith(['reserved', DailyInventoryStatusEnum::RESERVED])]
    #[TestWith(['disabled', DailyInventoryStatusEnum::DISABLED])]
    #[TestWith(['cancelled', DailyInventoryStatusEnum::CANCELLED])]
    #[TestWith(['refunded', DailyInventoryStatusEnum::REFUNDED])]
    public function testFromReturnsCorrectEnum(string $value, DailyInventoryStatusEnum $expectedEnum): void
    {
        $this->assertSame($expectedEnum, DailyInventoryStatusEnum::from($value));
    }

    #[TestWith(['invalid_value'])]
    #[TestWith([''])]
    #[TestWith(['null'])]
    #[TestWith(['unknown'])]
    #[TestWith(['active'])]
    #[TestWith(['inactive'])]
    #[TestWith(['processing'])]
    public function testFromThrowsValueErrorWithInvalidValue(string $invalidValue): void
    {
        $this->expectException(\ValueError::class);
        DailyInventoryStatusEnum::from($invalidValue);
    }

    #[TestWith(['available', DailyInventoryStatusEnum::AVAILABLE])]
    #[TestWith(['sold', DailyInventoryStatusEnum::SOLD])]
    #[TestWith(['pending', DailyInventoryStatusEnum::PENDING])]
    #[TestWith(['reserved', DailyInventoryStatusEnum::RESERVED])]
    #[TestWith(['disabled', DailyInventoryStatusEnum::DISABLED])]
    #[TestWith(['cancelled', DailyInventoryStatusEnum::CANCELLED])]
    #[TestWith(['refunded', DailyInventoryStatusEnum::REFUNDED])]
    public function testTryFromReturnsEnumWithValidValue(string $value, DailyInventoryStatusEnum $expectedEnum): void
    {
        $this->assertSame($expectedEnum, DailyInventoryStatusEnum::tryFrom($value));
    }

    #[TestWith(['invalid_value'])]
    #[TestWith([''])]
    #[TestWith(['null'])]
    #[TestWith(['unknown'])]
    #[TestWith(['active'])]
    #[TestWith(['inactive'])]
    #[TestWith(['processing'])]
    public function testTryFromReturnsNullWithInvalidValue(string $invalidValue): void
    {
        $this->assertNull(DailyInventoryStatusEnum::tryFrom($invalidValue));
    }

    public function testValuesAreUnique(): void
    {
        $values = array_map(fn (DailyInventoryStatusEnum $case) => $case->value, DailyInventoryStatusEnum::cases());
        $uniqueValues = array_unique($values);

        $this->assertCount(count($values), $uniqueValues, 'All enum values must be unique');
    }

    public function testLabelsAreUnique(): void
    {
        $labels = array_map(fn (DailyInventoryStatusEnum $case) => $case->getLabel(), DailyInventoryStatusEnum::cases());
        $uniqueLabels = array_unique($labels);

        $this->assertCount(count($labels), $uniqueLabels, 'All enum labels must be unique');
    }

    public function testToArray(): void
    {
        $result = DailyInventoryStatusEnum::AVAILABLE->toArray();

        // 验证返回的数组包含预期的键值对
        $this->assertArrayHasKey('value', $result);
        $this->assertArrayHasKey('label', $result);
        $this->assertSame('available', $result['value']);
        $this->assertSame('可售', $result['label']);
    }
}
