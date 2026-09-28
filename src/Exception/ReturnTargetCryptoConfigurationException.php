<?php

declare(strict_types=1);

namespace Maatify\ReturnTarget\Exception;

use Maatify\Exceptions\Contracts\ErrorCodeInterface;
use Maatify\Exceptions\Enum\ErrorCodeEnum;
use Maatify\Exceptions\Exception\System\SystemMaatifyException;

/**
 * Reports a canonical cryptographic or key-configuration failure.
 */
final class ReturnTargetCryptoConfigurationException extends SystemMaatifyException implements ReturnTargetExceptionInterface
{
    protected function defaultErrorCode(): ErrorCodeInterface
    {
        return ErrorCodeEnum::MAATIFY_ERROR;
    }
}
