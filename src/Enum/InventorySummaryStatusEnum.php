<?php

namespace Tourze\HotelContractBundle\Enum;

use Tourze\EnumExtra\Itemable;
use Tourze\EnumExtra\ItemTrait;
use Tourze\EnumExtra\Labelable;
use Tourze\EnumExtra\Selectable;
use Tourze\EnumExtra\SelectTrait;

/**
 * 库存统计状态枚举
 */
enum InventorySummaryStatusEnum: string implements Labelable, Itemable, Selectable
{
    use ItemTrait;
    use SelectTrait;

    case NORMAL = 'normal';
    case WARNING = 'warning';
    case SOLD_OUT = 'sold_out';

    public function getLabel(): string
    {
        return match ($this) {
            self::NORMAL => '正常',
            self::WARNING => '预警',
            self::SOLD_OUT => '售罄',
        };
    }
}
