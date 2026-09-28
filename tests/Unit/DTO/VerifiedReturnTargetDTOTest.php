<?php

declare(strict_types=1);

namespace Maatify\ReturnTarget\Tests\Unit\DTO;

use Maatify\ReturnTarget\DTO\VerifiedReturnTargetDTO;
use PHPUnit\Framework\TestCase;

final class VerifiedReturnTargetDTOTest extends TestCase
{
    public function testJsonShapeContainsExactlyTargetAndExpiry(): void
    {
        $dto = new VerifiedReturnTargetDTO('/orders/15', 1790000000);

        self::assertSame(
            ['target' => '/orders/15', 'expiresAt' => 1790000000],
            $dto->jsonSerialize(),
        );
    }
}
