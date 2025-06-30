<?php

declare(strict_types=1);

namespace Tourze\HotelContractBundle\Exception;

/**
 * 合同状态无效异常
 * 当尝试对不符合要求的合同状态进行操作时抛出
 */
class ContractStatusInvalidException extends \InvalidArgumentException
{
} 