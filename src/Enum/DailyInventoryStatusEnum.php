<?php

namespace Tourze\HotelContractBundle\Enum;

use Tourze\EnumExtra\Itemable;
use Tourze\EnumExtra\ItemTrait;
use Tourze\EnumExtra\Labelable;
use Tourze\EnumExtra\Selectable;
use Tourze\EnumExtra\SelectTrait;

/**
 * 日库存状态枚举
 */
enum DailyInventoryStatusEnum: string implements Labelable, Itemable, Selectable
{
    use ItemTrait;
    use SelectTrait;

    case AVAILABLE = 'available';       // 可售
    case SOLD = 'sold';                 // 已售
    case PENDING = 'pending';           // 待确认
    case RESERVED = 'reserved';         // 预留
    case DISABLED = 'disabled';         // 禁用
    case CANCELLED = 'cancelled';       // 已取消
    case REFUNDED = 'refunded';         // 已退款

    public function getLabel(): string
    {
        return match($this) {
            self::AVAILABLE => '可售',
            self::SOLD => '已售',
            self::PENDING => '待确认',
            self::RESERVED => '预留',
            self::DISABLED => '禁用',
            self::CANCELLED => '已取消',
            self::REFUNDED => '已退款',
        };
    }
}
