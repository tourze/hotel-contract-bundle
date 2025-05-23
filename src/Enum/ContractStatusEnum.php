<?php

namespace Tourze\HotelContractBundle\Enum;

use Tourze\EnumExtra\Itemable;
use Tourze\EnumExtra\ItemTrait;
use Tourze\EnumExtra\Labelable;
use Tourze\EnumExtra\Selectable;
use Tourze\EnumExtra\SelectTrait;

/**
 * 合同状态枚举
 */
enum ContractStatusEnum: string implements Labelable, Itemable, Selectable
{
    use ItemTrait;
    use SelectTrait;

    case PENDING = 'pending';
    case ACTIVE = 'active';
    case TERMINATED = 'terminated';

    public function getLabel(): string
    {
        return match($this) {
            self::PENDING => '待确认',
            self::ACTIVE => '生效',
            self::TERMINATED => '终止',
        };
    }
}
