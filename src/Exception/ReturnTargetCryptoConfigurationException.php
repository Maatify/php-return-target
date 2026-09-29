<?php

declare(strict_types=1);

namespace Maatify\ReturnTarget\Exception;

use Maatify\Exceptions\Contracts\ErrorCodeInterface;
use Maatify\Exceptions\Enum\ErrorCodeEnum;
use Maatify\Exceptions\Exception\System\SystemMaatifyException;

/**
 * Reports a canonical cryptographic or key-configuration failure.
 *
 * Its package-owned constructor always exposes the MAATIFY_ERROR error code.
 */
final class ReturnTargetCryptoConfigurationException extends SystemMaatifyException implements ReturnTargetExceptionInterface
{
    /**
     * Keeps the package-owned constructor intentionally narrow while preserving
     * the previous throwable through the shared exception implementation.
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Selects the stable package error code for cryptographic configuration
     * failures.
     */
    protected function defaultErrorCode(): ErrorCodeInterface
    {
        return ErrorCodeEnum::MAATIFY_ERROR;
    }
}
