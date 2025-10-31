<?php

namespace Tourze\HotelContractBundle\Tests\Enum;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use Tourze\HotelContractBundle\Enum\ContractStatusEnum;
use Tourze\PHPUnitEnum\AbstractEnumTestCase;

/**
 * @internal
 */
#[CoversClass(ContractStatusEnum::class)]
final class ContractStatusEnumTest extends AbstractEnumTestCase
{
    #[TestWith([ContractStatusEnum::PENDING, 'pending', '待确认'])]
    #[TestWith([ContractStatusEnum::ACTIVE, 'active', '生效'])]
    #[TestWith([ContractStatusEnum::TERMINATED, 'terminated', '终止'])]
    public function testEnumValueAndLabel(ContractStatusEnum $enum, string $expectedValue, string $expectedLabel): void
    {
        $this->assertSame($expectedValue, $enum->value);
        $this->assertSame($expectedLabel, $enum->getLabel());
    }

    public function testAllCasesExist(): void
    {
        $cases = ContractStatusEnum::cases();

        $this->assertCount(3, $cases);
        $this->assertContains(ContractStatusEnum::PENDING, $cases);
        $this->assertContains(ContractStatusEnum::ACTIVE, $cases);
        $this->assertContains(ContractStatusEnum::TERMINATED, $cases);
    }

    #[TestWith(['pending', ContractStatusEnum::PENDING])]
    #[TestWith(['active', ContractStatusEnum::ACTIVE])]
    #[TestWith(['terminated', ContractStatusEnum::TERMINATED])]
    public function testFromReturnsCorrectEnum(string $value, ContractStatusEnum $expectedEnum): void
    {
        $this->assertSame($expectedEnum, ContractStatusEnum::from($value));
    }

    #[TestWith(['invalid_value'])]
    #[TestWith([''])]
    #[TestWith(['null'])]
    #[TestWith(['unknown'])]
    public function testFromThrowsValueErrorWithInvalidValue(string $invalidValue): void
    {
        $this->expectException(\ValueError::class);
        ContractStatusEnum::from($invalidValue);
    }

    #[TestWith(['pending', ContractStatusEnum::PENDING])]
    #[TestWith(['active', ContractStatusEnum::ACTIVE])]
    #[TestWith(['terminated', ContractStatusEnum::TERMINATED])]
    public function testTryFromReturnsEnumWithValidValue(string $value, ContractStatusEnum $expectedEnum): void
    {
        $this->assertSame($expectedEnum, ContractStatusEnum::tryFrom($value));
    }

    #[TestWith(['invalid_value'])]
    #[TestWith([''])]
    #[TestWith(['null'])]
    #[TestWith(['unknown'])]
    public function testTryFromReturnsNullWithInvalidValue(string $invalidValue): void
    {
        $this->assertNull(ContractStatusEnum::tryFrom($invalidValue));
    }

    public function testValuesAreUnique(): void
    {
        $values = array_map(fn (ContractStatusEnum $case) => $case->value, ContractStatusEnum::cases());
        $uniqueValues = array_unique($values);

        $this->assertCount(count($values), $uniqueValues, 'All enum values must be unique');
    }

    public function testLabelsAreUnique(): void
    {
        $labels = array_map(fn (ContractStatusEnum $case) => $case->getLabel(), ContractStatusEnum::cases());
        $uniqueLabels = array_unique($labels);

        $this->assertCount(count($labels), $uniqueLabels, 'All enum labels must be unique');
    }

    public function testToArray(): void
    {
        $result = ContractStatusEnum::PENDING->toArray();

        // 验证返回的数组包含预期的键值对
        $this->assertArrayHasKey('value', $result);
        $this->assertArrayHasKey('label', $result);
        $this->assertSame('pending', $result['value']);
        $this->assertSame('待确认', $result['label']);
    }
}
