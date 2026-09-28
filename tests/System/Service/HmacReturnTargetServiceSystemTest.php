<?php

declare(strict_types=1);

namespace Maatify\ReturnTarget\Tests\System\Service;

use DateTimeImmutable;
use DateTimeZone;
use Maatify\Crypto\KeyRotation\DTO\CryptoKeyDTO;
use Maatify\Crypto\KeyRotation\KeyStatusEnum;
use Maatify\Crypto\KeyRotation\Providers\InMemoryKeyProvider;
use Maatify\ReturnTarget\Config\ReturnTargetConfig;
use Maatify\ReturnTarget\DTO\VerifiedReturnTargetDTO;
use Maatify\ReturnTarget\Service\HmacReturnTargetService;
use Maatify\ReturnTarget\Validation\ReturnTargetRestrictionPolicyInterface;
use Maatify\SharedCommon\Contracts\ClockInterface;
use PHPUnit\Framework\TestCase;

final class HmacReturnTargetServiceSystemTest extends TestCase
{
    public function testPublicWorkflowPreservesTargetAndUsesClockExpiry(): void
    {
        $clock = new SystemFixedClock(1790000000);
        $service = $this->service($clock, new SystemPolicy(true), 60);
        $original = '/orders%2F15?tab=a%2Fb';

        $token = $service->issue($original);
        self::assertNotNull($token);
        $verified = $service->verify($token);

        self::assertInstanceOf(VerifiedReturnTargetDTO::class, $verified);
        self::assertSame($original, $verified->target);
        self::assertSame(1790000060, $verified->expiresAt);
    }

    public function testCurrentPolicyAndExpiryBoundaryRejectOtherwiseValidToken(): void
    {
        $clock = new SystemFixedClock(1790000000);
        $policy = new SystemPolicy(true);
        $service = $this->service($clock, $policy, 60);
        $token = $service->issue('/orders/15');
        self::assertNotNull($token);

        $policy->allowed = false;
        self::assertNull($service->verify($token));

        $policy->allowed = true;
        $clock->timestamp = 1790000060;
        self::assertNull($service->verify($token));
        self::assertNull($service->issue('https://example.test'));
    }

    private function service(SystemFixedClock $clock, SystemPolicy $policy, int $ttl): HmacReturnTargetService
    {
        return new HmacReturnTargetService(
            new ReturnTargetConfig('admin-auth', $ttl),
            new InMemoryKeyProvider([new CryptoKeyDTO('key-1', '01234567890123456789012345678901', KeyStatusEnum::ACTIVE, new DateTimeImmutable('@1'))]),
            $clock,
            $policy,
        );
    }
}

final class SystemPolicy implements ReturnTargetRestrictionPolicyInterface
{
    public function __construct(public bool $allowed) {}

    public function allows(string $inspectionTarget): bool
    {
        return $this->allowed && str_starts_with($inspectionTarget, '/orders/15');
    }
}

final class SystemFixedClock implements ClockInterface
{
    public function __construct(public int $timestamp) {}

    public function now(): DateTimeImmutable
    {
        return (new DateTimeImmutable('@' . $this->timestamp))->setTimezone(new DateTimeZone('UTC'));
    }

    public function getTimezone(): DateTimeZone
    {
        return new DateTimeZone('UTC');
    }
}
