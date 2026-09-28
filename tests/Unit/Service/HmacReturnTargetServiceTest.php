<?php

declare(strict_types=1);

namespace Maatify\ReturnTarget\Tests\Unit\Service;

use DateTimeImmutable;
use DateTimeZone;
use Maatify\Crypto\KeyRotation\DTO\CryptoKeyDTO;
use Maatify\Crypto\KeyRotation\CryptoKeyInterface;
use Maatify\Crypto\KeyRotation\KeyProviderInterface;
use Maatify\Crypto\KeyRotation\KeyStatusEnum;
use Maatify\Crypto\KeyRotation\Providers\InMemoryKeyProvider;
use Maatify\ReturnTarget\Config\ReturnTargetConfig;
use Maatify\ReturnTarget\DTO\VerifiedReturnTargetDTO;
use Maatify\ReturnTarget\Service\HmacReturnTargetService;
use Maatify\ReturnTarget\Validation\ReturnTargetRestrictionPolicyInterface;
use Maatify\SharedCommon\Contracts\ClockInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class HmacReturnTargetServiceTest extends TestCase
{
    public function testAcceptsWithoutPolicyAndRejectsUnsafeTarget(): void
    {
        $service = $this->service();

        self::assertTrue($service->accepts('/orders/15'));
        self::assertFalse($service->accepts('https://example.test'));
        self::assertNull($service->issue('https://example.test'));
    }

    public function testPolicyRunsOnceOnDecodedInspectionTarget(): void
    {
        $policy = new RecordingPolicy(true);
        $service = $this->service($policy);

        self::assertTrue($service->accepts('/orders%2F15'));
        self::assertSame(['/orders/15'], $policy->targets);

        $policy->targets = [];
        self::assertFalse($service->accepts('/%2F%2Fevil'));
        self::assertSame([], $policy->targets);
    }

    public function testPolicyRunsExactlyOnceForAcceptsIssueAndSuccessfulVerify(): void
    {
        $policy = new RecordingPolicy(true);
        $service = $this->service($policy, new FixedClock(1790000000), 60);

        self::assertTrue($service->accepts('/orders%2F15'));
        self::assertSame(['/orders/15'], $policy->targets);

        $policy->targets = [];
        $token = $service->issue('/orders%2F15');
        self::assertNotNull($token);
        self::assertSame(['/orders/15'], $policy->targets);

        $policy->targets = [];
        self::assertNotNull($service->verify($token));
        self::assertSame(['/orders/15'], $policy->targets);
    }

    public function testMalformedOrUntrustedTokenReturnsNullThroughPublicService(): void
    {
        self::assertNull($this->service()->verify('not-a-valid-return-target-token'));
    }

    public function testCurrentRejectingPolicyInvalidatesPreviouslyIssuedToken(): void
    {
        $policy = new RecordingPolicy(true);
        $service = $this->service($policy, new FixedClock(1790000000), 60);
        $token = $service->issue('/orders/15');
        self::assertNotNull($token);

        $policy->allowed = false;

        self::assertNull($service->verify($token));
    }

    public function testPolicyRejectionAndExpiryBoundaryReturnNull(): void
    {
        $clock = new FixedClock(1790000000);
        $policy = new RecordingPolicy(false);
        $service = $this->service($policy, $clock);

        self::assertNull($service->issue('/orders/15'));

        $allowingService = $this->service(new RecordingPolicy(true), $clock);
        $token = $allowingService->issue('/orders/15');
        self::assertNotNull($token);

        $clock->timestamp = 1790003600;
        self::assertNull($allowingService->verify($token));
    }

    public function testIssueAndVerifyPreserveExactOriginalRepresentationAndClockExpiry(): void
    {
        $clock = new FixedClock(1790000000);
        $service = $this->service(null, $clock, 60);
        $original = '/orders%2F15?tab=a%2Fb';

        $token = $service->issue($original);
        self::assertNotNull($token);
        $verified = $service->verify($token);

        self::assertInstanceOf(VerifiedReturnTargetDTO::class, $verified);
        self::assertSame($original, $verified->target);
        self::assertSame(1790000060, $verified->expiresAt);
    }

    public function testUnknownProviderThrowablePropagates(): void
    {
        $key = new CryptoKeyDTO('key-1', '01234567890123456789012345678901', KeyStatusEnum::ACTIVE, new DateTimeImmutable('@1'));
        $goodService = new HmacReturnTargetService(new ReturnTargetConfig('admin-auth', 60), new InMemoryKeyProvider([$key]), new FixedClock(1790000000));
        $token = $goodService->issue('/orders/15');
        self::assertNotNull($token);
        $provider = new class ($key) implements KeyProviderInterface {
            public function __construct(private readonly CryptoKeyInterface $key) {}

            public function all(): iterable
            {
                return [$this->key];
            }

            public function active(): CryptoKeyInterface
            {
                return $this->key;
            }

            public function find(string $keyId): \Maatify\Crypto\KeyRotation\CryptoKeyInterface
            {
                throw new RuntimeException('provider failure');
            }

            public function promote(string $keyId): void
            {
                unset($keyId);
            }
        };
        $service = new HmacReturnTargetService(new ReturnTargetConfig('admin-auth', 60), $provider, new FixedClock(1790000000));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('provider failure');
        $service->verify($token);
    }

    private function service(?ReturnTargetRestrictionPolicyInterface $policy = null, ?FixedClock $clock = null, int $ttl = 3600): HmacReturnTargetService
    {
        return new HmacReturnTargetService(
            new ReturnTargetConfig('admin-auth', $ttl),
            new InMemoryKeyProvider([new CryptoKeyDTO('key-1', '01234567890123456789012345678901', KeyStatusEnum::ACTIVE, new DateTimeImmutable('@1'))]),
            $clock ?? new FixedClock(1790000000),
            $policy,
        );
    }
}

final class RecordingPolicy implements ReturnTargetRestrictionPolicyInterface
{
    /** @var list<string> */
    public array $targets = [];

    public function __construct(public bool $allowed) {}

    public function allows(string $inspectionTarget): bool
    {
        $this->targets[] = $inspectionTarget;

        return $this->allowed;
    }
}

final class FixedClock implements ClockInterface
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
