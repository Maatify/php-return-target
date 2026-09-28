<?php

declare(strict_types=1);

namespace Maatify\ReturnTarget\DTO;

/**
 * @internal
 *
 * Represents successful canonical token, signature, audience, and expiry
 * verification before final target validation or Host policy acceptance.
 */
final readonly class VerifiedTokenPayloadDTO implements \JsonSerializable
{
    public function __construct(
        public string $target,
        public int $expiresAt,
    ) {}

    public function jsonSerialize(): mixed
    {
        return [
            'target' => $this->target,
            'expiresAt' => $this->expiresAt,
        ];
    }
}
