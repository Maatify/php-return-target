<?php

declare(strict_types=1);

namespace Maatify\ReturnTarget\Service;

use Maatify\ReturnTarget\DTO\VerifiedReturnTargetDTO;

/**
 * Shared substitution boundary for implementations that handle internal return targets.
 *
 * Implementations may choose their own token, cryptographic, storage, and validation
 * internals while preserving the behavioral floor described by these operations.
 */
interface ReturnTargetServiceInterface
{
    /**
     * Reports whether the target is currently an acceptable internal return target.
     *
     * Normal target rejection returns false.
     */
    public function accepts(string $target): bool;

    /**
     * Returns an opaque transport token for an accepted target.
     *
     * Normal target rejection returns null; implementations do not use an internal
     * fallback target.
     */
    public function issue(string $target): ?string;

    /**
     * Returns a currently accepted, non-expired verified result for an acceptable token.
     *
     * The DTO expiry is the authoritative Unix expiration timestamp in seconds.
     * Normal malformed, untrusted, expired, or rejected token input returns null.
     */
    public function verify(string $token): ?VerifiedReturnTargetDTO;
}
