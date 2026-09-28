<?php

declare(strict_types=1);

namespace Maatify\ReturnTarget\Config;

use Maatify\ReturnTarget\Exception\InvalidReturnTargetConfigurationException;

/**
 * Immutable canonical configuration for the return-target runtime.
 */
final readonly class ReturnTargetConfig
{
    /**
     * @throws InvalidReturnTargetConfigurationException when the audience or TTL is outside the canonical bounds.
     */
    public function __construct(
        public string $audience,
        public int $ttlSeconds,
    ) {
        if (
            strlen($audience) < 1
            || strlen($audience) > 64
            || preg_match('/^[A-Za-z0-9._-]+$/', $audience) !== 1
            || $ttlSeconds < 1
            || $ttlSeconds > 3600
        ) {
            throw new InvalidReturnTargetConfigurationException('Invalid return-target configuration.');
        }
    }
}
