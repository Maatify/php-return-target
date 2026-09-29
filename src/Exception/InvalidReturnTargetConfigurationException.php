<?php

declare(strict_types=1);

namespace Maatify\ReturnTarget\Exception;

use Maatify\Exceptions\Exception\Validation\InvalidArgumentMaatifyException;

/**
 * Reports a configuration value outside the canonical return-target bounds.
 *
 * Its package-owned constructor always exposes the INVALID_ARGUMENT error code
 * inherited from the validation exception hierarchy.
 */
final class InvalidReturnTargetConfigurationException extends InvalidArgumentMaatifyException implements ReturnTargetExceptionInterface
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
}
