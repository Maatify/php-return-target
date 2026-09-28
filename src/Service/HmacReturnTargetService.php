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

    /**
     * Constructs the canonical service from canonical audience/TTL configuration,
     * Host-owned key material, the source of current time, and an optional restrict-only
     * policy. The service owns composition of its internal validator and codec; neither
     * is a Host-pluggable strategy.
     */
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
     * Normal target rejection returns false. Classified canonical crypto failures do not
     * arise from this operation; policy throwables propagate unchanged.
     */
    public function accepts(string $target): bool
    {
        return $this->acceptedInspection($target) !== null;
    }

    /**
     * Issues an expiring token for the exact accepted target, or null for normal rejection.
     * Classified canonical crypto/key configuration failures may propagate as
     * ReturnTargetCryptoConfigurationException. Unclassified provider and policy
     * throwables propagate unchanged; the service does not catch or blanket-wrap them.
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
     * Normal malformed, untrusted, expired, or rejected tokens return null. Classified
     * canonical crypto/key configuration failures may propagate as
     * ReturnTargetCryptoConfigurationException; unclassified provider and policy
     * throwables propagate unchanged.
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

    /**
     * Produces acceptance evidence through canonical validation first and an optional
     * policy second. The policy is called once at most and receives only the validated
     * inspection representation; this value never replaces the original target.
     */
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
