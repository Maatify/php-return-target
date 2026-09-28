<?php

declare(strict_types=1);

namespace Maatify\ReturnTarget\Service;

use Maatify\ReturnTarget\DTO\VerifiedReturnTargetDTO;

/**
 * Public substitution boundary for issuing and verifying signed return targets.
 */
interface ReturnTargetServiceInterface
{
    /**
     * Reports whether the target passes canonical validation and any configured restriction policy.
     */
    public function accepts(string $target): bool;

    /**
     * Issues a token for an accepted exact target, or returns null for normal target rejection.
     */
    public function issue(string $target): ?string;

    /**
     * Verifies a token against current time and current target policy, returning null on rejection.
     */
    public function verify(string $token): ?VerifiedReturnTargetDTO;
}
