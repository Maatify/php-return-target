<?php

declare(strict_types=1);

namespace Maatify\ReturnTarget\Tests\Unit\Config;

use Maatify\Exceptions\Exception\Validation\InvalidArgumentMaatifyException;
use Maatify\Exceptions\Enum\ErrorCodeEnum;
use Maatify\ReturnTarget\Config\ReturnTargetConfig;
use Maatify\ReturnTarget\Exception\InvalidReturnTargetConfigurationException;
use Maatify\ReturnTarget\Exception\ReturnTargetExceptionInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ReturnTargetConfigTest extends TestCase
{
    public function testMaximumAudienceLengthIsAccepted(): void
    {
        $audience = str_repeat('a', 64);

        $config = new ReturnTargetConfig($audience, 60);

        self::assertSame($audience, $config->audience);
    }

    public function testValidAudienceAndTtlAreAccepted(): void
    {
        $config = new ReturnTargetConfig('admin-auth_v1.2', 120);

        self::assertSame('admin-auth_v1.2', $config->audience);
        self::assertSame(120, $config->ttlSeconds);
    }

    public function testMinimumAndMaximumTtlAreAccepted(): void
    {
        self::assertSame(1, (new ReturnTargetConfig('a', 1))->ttlSeconds);
        self::assertSame(3600, (new ReturnTargetConfig('a', 3600))->ttlSeconds);
    }

    #[DataProvider('invalidConfigurationProvider')]
    public function testInvalidConfigurationIsRejected(string $audience, int $ttlSeconds): void
    {
        $this->expectException(InvalidReturnTargetConfigurationException::class);

        new ReturnTargetConfig($audience, $ttlSeconds);
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function invalidConfigurationProvider(): iterable
    {
        yield 'empty audience' => ['', 60];
        yield '65 byte audience' => [str_repeat('a', 65), 60];
        yield 'space' => ['admin auth', 60];
        yield 'slash' => ['admin/auth', 60];
        yield 'non-ascii audience' => ['é', 60];
        yield 'zero ttl' => ['a', 0];
        yield 'ttl above maximum' => ['a', 3601];
    }

    public function testConfigurationExceptionHasThePackageMarkerAndStableBase(): void
    {
        $exception = new InvalidReturnTargetConfigurationException('invalid');

        self::assertInstanceOf(ReturnTargetExceptionInterface::class, $exception);
        self::assertInstanceOf(InvalidArgumentMaatifyException::class, $exception);
        self::assertSame(ErrorCodeEnum::INVALID_ARGUMENT, $exception->getErrorCode());
    }
}
