<?php

declare(strict_types=1);

namespace Maatify\ReturnTarget\Validation;

/**
 * Optional Host-owned, restrict-only policy evaluated after canonical validation.
 */
interface ReturnTargetRestrictionPolicyInterface
{
    /**
     * Receives the single-decoded inspection representation and may only further reject it.
     */
    public function allows(string $inspectionTarget): bool;
}
