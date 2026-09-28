<?php

declare(strict_types=1);

namespace Maatify\ReturnTarget\DTO;

/**
 * Public result containing the exact recovered target and its absolute expiry timestamp.
 */
final readonly class VerifiedReturnTargetDTO implements \JsonSerializable
{
    public function __construct(
        public string $target,
        public int $expiresAt,
    ) {}

    /**
     * Serializes the public result with its exact two-key contract.
     *
     * @return array{target: string, expiresAt: int}
     */
    public function jsonSerialize(): mixed
    {
        return [
            'target' => $this->target,
            'expiresAt' => $this->expiresAt,
        ];
    }
}
