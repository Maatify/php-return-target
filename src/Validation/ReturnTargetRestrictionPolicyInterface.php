<?php

declare(strict_types=1);

namespace Maatify\ReturnTarget\Validation;

/**
 * Optional Host-owned, restrict-only policy evaluated after canonical validation.
 *
 * It cannot make a canonically rejected target acceptable. The canonical service calls
 * it exactly once per acceptance evaluation and passes only the validated single-decoded
 * inspection representation.
 */
interface ReturnTargetRestrictionPolicyInterface
{
    /**
     * Receives the single-decoded inspection representation and may only further reject it.
     */
    public function allows(string $inspectionTarget): bool;
}
