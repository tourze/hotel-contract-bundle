<?php

declare(strict_types=1);

namespace Tourze\HotelContractBundle\Exception;

/**
 * 库存不足异常
 * 当指定日期范围内没有足够的可用库存时抛出
 */
class InsufficientInventoryException extends \RuntimeException
{
}
