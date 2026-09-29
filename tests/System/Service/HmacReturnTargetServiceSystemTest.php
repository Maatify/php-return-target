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
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function testPublicServiceAcceptsThe2048ByteTargetAndRejects2049Bytes(): void
    {
        $service = $this->service(new SystemFixedClock(1790000000), null, 60);
        $maximum = '/' . str_repeat('a', 2047);
        $oversized = $maximum . 'a';

        self::assertSame(2048, strlen($maximum));
        self::assertTrue($service->accepts($maximum));
        self::assertNotNull($service->issue($maximum));

        self::assertSame(2049, strlen($oversized));
        self::assertFalse($service->accepts($oversized));
        self::assertNull($service->issue($oversized));
    }

    public function testPublicServicePreservesLiteralPlusInPolicyInspectionValue(): void
    {
        $policy = new RecordingSystemPolicy();
        $service = $this->service(new SystemFixedClock(1790000000), $policy, 60);
        $target = '/orders/15?query=a+b';

        self::assertTrue($service->accepts($target));
        self::assertSame([$target], $policy->inspectionTargets);
    }

    public function testPublicServiceRejectsMalformedPercentSyntax(): void
    {
        $service = $this->service(new SystemFixedClock(1790000000), null, 60);

        self::assertFalse($service->accepts('/orders/%GG'));
        self::assertNull($service->issue('/orders/%GG'));
    }

    #[DataProvider('decodedUnsafeValues')]
    public function testPublicServiceRejectsDecodedUnsafeValues(string $target, string $case): void
    {
        $service = $this->service(new SystemFixedClock(1790000000), null, 60);

        self::assertFalse($service->accepts($target), $case);
        self::assertNull($service->issue($target), $case);
    }

    /**
     * @return iterable<string, array{target: string, case: string}>
     */
    public static function decodedUnsafeValues(): iterable
    {
        yield 'NUL' => ['target' => '/orders/%00', 'case' => 'decoded NUL must be rejected'];
        yield 'control byte' => ['target' => '/orders/%01', 'case' => 'decoded control byte must be rejected'];
        yield 'backslash' => ['target' => '/orders/%5C', 'case' => 'decoded backslash must be rejected'];
        yield 'fragment marker' => ['target' => '/orders/%23fragment', 'case' => 'decoded fragment marker must be rejected'];
        yield 'whitespace' => ['target' => '/orders/%20item', 'case' => 'decoded whitespace must be rejected'];
    }

    #[DataProvider('rejectedPathTargets')]
    public function testPublicServiceRejectsAuthorityAndDotPathTargets(string $target, string $case): void
    {
        $service = $this->service(new SystemFixedClock(1790000000), null, 60);

        self::assertFalse($service->accepts($target), $case);
        self::assertNull($service->issue($target), $case);
    }

    /**
     * @return iterable<string, array{target: string, case: string}>
     */
    public static function rejectedPathTargets(): iterable
    {
        yield 'encoded authority exposure' => ['target' => '/%2F%2Fevil.example', 'case' => 'decoded authority exposure must be rejected'];
        yield 'raw dot segment' => ['target' => '/orders/./15', 'case' => 'raw dot segment must be rejected'];
        yield 'raw dot-dot segment' => ['target' => '/orders/../15', 'case' => 'raw dot-dot segment must be rejected'];
        yield 'encoded dot segment' => ['target' => '/orders/%2E/15', 'case' => 'encoded dot segment must be rejected'];
        yield 'encoded dot-dot segment' => ['target' => '/orders/%2E%2E/15', 'case' => 'encoded dot-dot segment must be rejected'];
        yield 'second-stage percent escape' => ['target' => '/orders/%252F%252Fevil.example', 'case' => 'second-stage percent escape must be rejected'];
    }

    public function testPublicServiceKeepsQueryDotDotAsData(): void
    {
        $service = $this->service(new SystemFixedClock(1790000000), null, 60);
        $target = '/orders/15?next=..';

        self::assertTrue($service->accepts($target));
        self::assertNotNull($service->issue($target));
    }

    private function service(SystemFixedClock $clock, ?ReturnTargetRestrictionPolicyInterface $policy, int $ttl): HmacReturnTargetService
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

final class RecordingSystemPolicy implements ReturnTargetRestrictionPolicyInterface
{
    /**
     * @var list<string>
     */
    public array $inspectionTargets = [];

    public function allows(string $inspectionTarget): bool
    {
        $this->inspectionTargets[] = $inspectionTarget;

        return true;
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
