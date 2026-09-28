<?php

declare(strict_types=1);

namespace Maatify\ReturnTarget\Config;

use Maatify\ReturnTarget\Exception\InvalidReturnTargetConfigurationException;

/**
 * Immutable canonical configuration for the return-target runtime.
 *
 * The audience is preserved exactly, is 1..64 bytes long, and may contain
 * only ASCII letters, digits, dot, underscore, and hyphen. The TTL is
 * expressed in seconds and must be within 1..3600.
 */
final readonly class ReturnTargetConfig
{
    /**
     * Creates validated canonical configuration without normalization or coercion.
     *
     * @throws InvalidReturnTargetConfigurationException When the audience or TTL violates the canonical contract.
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
