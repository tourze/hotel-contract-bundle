<?php

namespace Tourze\HotelContractBundle\Tests\Enum;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use Tourze\HotelContractBundle\Enum\ContractTypeEnum;
use Tourze\PHPUnitEnum\AbstractEnumTestCase;

/**
 * @internal
 */
#[CoversClass(ContractTypeEnum::class)]
final class ContractTypeEnumTest extends AbstractEnumTestCase
{
    #[TestWith([ContractTypeEnum::FIXED_PRICE, 'fixed_price', '固定总价'])]
    #[TestWith([ContractTypeEnum::DYNAMIC_PRICE, 'dynamic_price', '动态打包价'])]
    public function testEnumValueAndLabel(ContractTypeEnum $enum, string $expectedValue, string $expectedLabel): void
    {
        $this->assertSame($expectedValue, $enum->value);
        $this->assertSame($expectedLabel, $enum->getLabel());
    }

    public function testAllCasesExist(): void
    {
        $cases = ContractTypeEnum::cases();

        $this->assertCount(2, $cases);
        $this->assertContains(ContractTypeEnum::FIXED_PRICE, $cases);
        $this->assertContains(ContractTypeEnum::DYNAMIC_PRICE, $cases);
    }

    #[TestWith(['fixed_price', ContractTypeEnum::FIXED_PRICE])]
    #[TestWith(['dynamic_price', ContractTypeEnum::DYNAMIC_PRICE])]
    public function testFromReturnsCorrectEnum(string $value, ContractTypeEnum $expectedEnum): void
    {
        $this->assertSame($expectedEnum, ContractTypeEnum::from($value));
    }

    #[TestWith(['invalid_value'])]
    #[TestWith([''])]
    #[TestWith(['null'])]
    #[TestWith(['unknown'])]
    #[TestWith(['fixed'])]
    #[TestWith(['dynamic'])]
    public function testFromThrowsValueErrorWithInvalidValue(string $invalidValue): void
    {
        $this->expectException(\ValueError::class);
        ContractTypeEnum::from($invalidValue);
    }

    #[TestWith(['fixed_price', ContractTypeEnum::FIXED_PRICE])]
    #[TestWith(['dynamic_price', ContractTypeEnum::DYNAMIC_PRICE])]
    public function testTryFromReturnsEnumWithValidValue(string $value, ContractTypeEnum $expectedEnum): void
    {
        $this->assertSame($expectedEnum, ContractTypeEnum::tryFrom($value));
    }

    #[TestWith(['invalid_value'])]
    #[TestWith([''])]
    #[TestWith(['null'])]
    #[TestWith(['unknown'])]
    #[TestWith(['fixed'])]
    #[TestWith(['dynamic'])]
    public function testTryFromReturnsNullWithInvalidValue(string $invalidValue): void
    {
        $this->assertNull(ContractTypeEnum::tryFrom($invalidValue));
    }

    public function testValuesAreUnique(): void
    {
        $values = array_map(fn (ContractTypeEnum $case) => $case->value, ContractTypeEnum::cases());
        $uniqueValues = array_unique($values);

        $this->assertCount(count($values), $uniqueValues, 'All enum values must be unique');
    }

    public function testLabelsAreUnique(): void
    {
        $labels = array_map(fn (ContractTypeEnum $case) => $case->getLabel(), ContractTypeEnum::cases());
        $uniqueLabels = array_unique($labels);

        $this->assertCount(count($labels), $uniqueLabels, 'All enum labels must be unique');
    }

    public function testToArray(): void
    {
        $result = ContractTypeEnum::FIXED_PRICE->toArray();

        // 验证返回的数组包含预期的键值对
        $this->assertArrayHasKey('value', $result);
        $this->assertArrayHasKey('label', $result);
        $this->assertSame('fixed_price', $result['value']);
        $this->assertSame('固定总价', $result['label']);
    }
}
