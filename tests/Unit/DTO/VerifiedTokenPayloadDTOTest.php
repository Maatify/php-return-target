<?php

declare(strict_types=1);

namespace Maatify\ReturnTarget\Tests\Unit\DTO;

use Maatify\ReturnTarget\DTO\VerifiedTokenPayloadDTO;
use PHPUnit\Framework\TestCase;

final class VerifiedTokenPayloadDTOTest extends TestCase
{
    public function testJsonSerializationPreservesTheExactInternalShape(): void
    {
        $dto = new VerifiedTokenPayloadDTO('/orders/15', 1790000000);

        self::assertSame(['target' => '/orders/15', 'expiresAt' => 1790000000], $dto->jsonSerialize());
    }
}
