<?php

declare(strict_types=1);

namespace Maatify\ReturnTarget\DTO;

/**
 * @internal
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
