<?php

declare(strict_types=1);

namespace Maatify\ReturnTarget\Service;

use Maatify\Crypto\KeyRotation\KeyProviderInterface;
use Maatify\ReturnTarget\Adapter\HmacReturnTargetTokenCodec;
use Maatify\ReturnTarget\Config\ReturnTargetConfig;
use Maatify\ReturnTarget\DTO\VerifiedReturnTargetDTO;
use Maatify\ReturnTarget\Validation\CanonicalReturnTargetValidator;
use Maatify\ReturnTarget\Validation\ReturnTargetRestrictionPolicyInterface;
use Maatify\SharedCommon\Contracts\ClockInterface;

/**
 * Canonical public HMAC service composing target acceptance, Clock semantics, and the internal codec.
 *
 * The original target representation is signed and returned exactly as supplied. The
 * validator's decoded view is used only for security inspection and Host policy checks.
 * Dependency and classified codec exceptions propagate according to the codec contract.
 */
final class HmacReturnTargetService implements ReturnTargetServiceInterface
{
    private readonly CanonicalReturnTargetValidator $validator;

    private readonly HmacReturnTargetTokenCodec $codec;

    public function __construct(
        private readonly ReturnTargetConfig $config,
        KeyProviderInterface $keyProvider,
        private readonly ClockInterface $clock,
        private readonly ?ReturnTargetRestrictionPolicyInterface $restrictionPolicy = null,
    ) {
        $this->validator = new CanonicalReturnTargetValidator();
        $this->codec = new HmacReturnTargetTokenCodec($config, $keyProvider);
    }

    /**
     * Accepts only targets passing canonical validation and the optional restrict-only policy.
     */
    public function accepts(string $target): bool
    {
        return $this->acceptedInspection($target) !== null;
    }

    /**
     * Issues an expiring token for the exact accepted target, or null for normal rejection.
     */
    public function issue(string $target): ?string
    {
        if ($this->acceptedInspection($target) === null) {
            return null;
        }

        $expiresAt = $this->clock->now()->getTimestamp() + $this->config->ttlSeconds;

        return $this->codec->issue($target, $expiresAt);
    }

    /**
     * Verifies the codec payload and re-applies current canonical validation and policy.
     */
    public function verify(string $token): ?VerifiedReturnTargetDTO
    {
        $payload = $this->codec->verify($token, $this->clock->now()->getTimestamp());
        if ($payload === null || $this->acceptedInspection($payload->target) === null) {
            return null;
        }

        return new VerifiedReturnTargetDTO(
            target: $payload->target,
            expiresAt: $payload->expiresAt,
        );
    }

    private function acceptedInspection(string $target): ?string
    {
        $inspectionTarget = $this->validator->inspect($target);
        if ($inspectionTarget === null) {
            return null;
        }

        if ($this->restrictionPolicy !== null && ! $this->restrictionPolicy->allows($inspectionTarget)) {
            return null;
        }

        return $inspectionTarget;
    }
}
