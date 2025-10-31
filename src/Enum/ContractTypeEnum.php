<?php

namespace Tourze\HotelContractBundle\Enum;

use Tourze\EnumExtra\Itemable;
use Tourze\EnumExtra\ItemTrait;
use Tourze\EnumExtra\Labelable;
use Tourze\EnumExtra\Selectable;
use Tourze\EnumExtra\SelectTrait;

/**
 * 合同类型枚举
 */
enum ContractTypeEnum: string implements Labelable, Itemable, Selectable
{
    use ItemTrait;
    use SelectTrait;

    case FIXED_PRICE = 'fixed_price';
    case DYNAMIC_PRICE = 'dynamic_price';

    public function getLabel(): string
    {
        return match ($this) {
            self::FIXED_PRICE => '固定总价',
            self::DYNAMIC_PRICE => '动态打包价',
        };
    }
}
