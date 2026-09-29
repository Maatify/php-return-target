<?php

declare(strict_types=1);

namespace Maatify\ReturnTarget\Tests\System\Config;

use Maatify\ReturnTarget\Config\ReturnTargetConfig;
use Maatify\ReturnTarget\Exception\InvalidReturnTargetConfigurationException;
use PHPUnit\Framework\TestCase;

final class ReturnTargetConfigSystemTest extends TestCase
{
    public function testPublicRuntimeConstructionPreservesExactValues(): void
    {
        $config = new ReturnTargetConfig('orders_v1', 3600);

        self::assertSame('orders_v1', $config->audience);
        self::assertSame(3600, $config->ttlSeconds);
    }

    public function testInvalidCanonicalConfigurationFailsAtPublicConstructionBoundary(): void
    {
        $this->expectException(InvalidReturnTargetConfigurationException::class);

        new ReturnTargetConfig('orders/return', 60);
    }
}
