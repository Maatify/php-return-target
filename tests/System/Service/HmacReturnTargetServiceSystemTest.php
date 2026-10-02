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
use RuntimeException;
use Throwable;

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

    public function testPublicWorkflowRejectsTamperedMalformedAndOversizedOpaqueTokens(): void
    {
        $service = $this->service(new SystemFixedClock(1790000000), null, 60);
        $token = $service->issue('/orders/15');
        self::assertNotNull($token);

        $tamperedToken = $token . 'x';

        self::assertNull($service->verify($tamperedToken));
        self::assertNull($service->verify('malformed-opaque-token'));
        self::assertNull($service->verify(str_repeat('x', 4097)));
    }

    public function testPublicWorkflowIsolatesTokensByAudience(): void
    {
        $clock = new SystemFixedClock(1790000000);
        $provider = new InMemoryKeyProvider([
            new CryptoKeyDTO('key-1', '01234567890123456789012345678901', KeyStatusEnum::ACTIVE, new DateTimeImmutable('@1')),
        ]);
        $audienceA = new HmacReturnTargetService(
            new ReturnTargetConfig('audience-a', 60),
            $provider,
            $clock,
        );
        $audienceB = new HmacReturnTargetService(
            new ReturnTargetConfig('audience-b', 60),
            $provider,
            $clock,
        );

        $token = $audienceA->issue('/orders/15');
        self::assertNotNull($token);

        self::assertNotNull($audienceA->verify($token));
        self::assertNull($audienceB->verify($token));
    }

    public function testPublicWorkflowPreservesTokensAcrossSuccessfulKeyRotation(): void
    {
        $clock = new SystemFixedClock(1790000000);
        $provider = new InMemoryKeyProvider([
            new CryptoKeyDTO('key-a', '01234567890123456789012345678901', KeyStatusEnum::ACTIVE, new DateTimeImmutable('@1')),
            new CryptoKeyDTO('key-b', 'abcdefghijklmnopqrstuvwxyz123456', KeyStatusEnum::INACTIVE, new DateTimeImmutable('@1')),
        ]);
        $service = new HmacReturnTargetService(
            new ReturnTargetConfig('admin-auth', 60),
            $provider,
            $clock,
        );

        self::assertSame(KeyStatusEnum::ACTIVE, $provider->find('key-a')->status());
        self::assertSame(KeyStatusEnum::INACTIVE, $provider->find('key-b')->status());

        $preRotationToken = $service->issue('/orders/15?rotation=before');
        self::assertNotNull($preRotationToken);

        $provider->promote('key-b');

        self::assertSame(KeyStatusEnum::INACTIVE, $provider->find('key-a')->status());
        self::assertSame(KeyStatusEnum::ACTIVE, $provider->find('key-b')->status());
        self::assertInstanceOf(VerifiedReturnTargetDTO::class, $service->verify($preRotationToken));

        $postRotationToken = $service->issue('/orders/15?rotation=after');
        self::assertNotNull($postRotationToken);
        self::assertNotSame($preRotationToken, $postRotationToken);
        self::assertInstanceOf(VerifiedReturnTargetDTO::class, $service->verify($postRotationToken));
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

    #[DataProvider('queryEncodedSpaceTargets')]
    public function testQueryEncodedSpaceIsAcceptedAndExactOriginalRepresentationIsPreserved(string $target, string $unexpectedRewrite): void
    {
        $service = $this->service(new SystemFixedClock(1790000000), null, 60);

        self::assertTrue($service->accepts($target));
        $token = $service->issue($target);
        self::assertNotNull($token);

        $verified = $service->verify($token);
        self::assertInstanceOf(VerifiedReturnTargetDTO::class, $verified);
        self::assertSame($target, $verified->target);
        self::assertNotSame($unexpectedRewrite, $verified->target);
        self::assertNotSame(str_replace('%20', '+', $target), $verified->target);
        self::assertSame(1790000060, $verified->expiresAt);
    }

    /**
     * @return iterable<string, array{target: string, unexpectedRewrite: string}>
     */
    public static function queryEncodedSpaceTargets(): iterable
    {
        yield 'two words' => ['target' => '/p?q=two%20words', 'unexpectedRewrite' => '/p?q=two words'];
        yield 'three words' => ['target' => '/p?q=a%20b%20c', 'unexpectedRewrite' => '/p?q=a b c'];
    }

    public function testHostPolicyReceivesSingleDecodedQuerySpaceExactlyOncePerOperation(): void
    {
        $policy = new RecordingSystemPolicy();
        $service = $this->service(new SystemFixedClock(1790000000), $policy, 60);
        $target = '/p?q=two%20words';

        self::assertTrue($service->accepts($target));
        self::assertSame(['/p?q=two words'], $policy->inspectionTargets);

        $policy->reset();
        $token = $service->issue($target);
        self::assertNotNull($token);
        self::assertSame(['/p?q=two words'], $policy->inspectionTargets);

        $policy->reset();
        $verified = $service->verify($token);
        self::assertInstanceOf(VerifiedReturnTargetDTO::class, $verified);
        self::assertSame($target, $verified->target);
        self::assertSame(['/p?q=two words'], $policy->inspectionTargets);
    }

    public function testRestrictOnlyPolicyCanStillRejectAcceptedQuerySpaceTarget(): void
    {
        $policy = new SystemPolicy(false);
        $service = $this->service(new SystemFixedClock(1790000000), $policy, 60);

        self::assertFalse($service->accepts('/orders/15?q=two%20words'));
        self::assertSame(1, $policy->calls);
    }

    public function testPathEncodedSpaceDoesNotReachHostPolicy(): void
    {
        $policy = new RecordingSystemPolicy();
        $service = $this->service(new SystemFixedClock(1790000000), $policy, 60);

        self::assertFalse($service->accepts('/p%20x?q=1'));
        self::assertNull($service->issue('/p%20x?q=1'));
        self::assertSame([], $policy->inspectionTargets);
    }

    public function testLiteralPlusIsNotDecodedToSpaceAndIsPreservedExactly(): void
    {
        $policy = new RecordingSystemPolicy();
        $service = $this->service(new SystemFixedClock(1790000000), $policy, 60);
        $target = '/p?q=a+b';

        $token = $service->issue($target);
        self::assertNotNull($token);
        self::assertSame([$target], $policy->inspectionTargets);

        $verified = $service->verify($token);
        self::assertInstanceOf(VerifiedReturnTargetDTO::class, $verified);
        self::assertSame($target, $verified->target);
    }

    public function testPublicServiceEvaluatesHostPolicyOncePerPublicOperationWithDecodedInspectionValue(): void
    {
        $policy = new RecordingSystemPolicy();
        $service = $this->service(new SystemFixedClock(1790000000), $policy, 60);
        $target = '/orders%2F15';

        self::assertTrue($service->accepts($target));
        self::assertSame(['/orders/15'], $policy->inspectionTargets);

        $policy->reset();
        $token = $service->issue($target);
        self::assertNotNull($token);
        self::assertSame(['/orders/15'], $policy->inspectionTargets);

        $policy->reset();
        self::assertInstanceOf(VerifiedReturnTargetDTO::class, $service->verify($token));
        self::assertSame(['/orders/15'], $policy->inspectionTargets);
    }

    public function testCanonicallyInvalidTargetDoesNotReachHostPolicy(): void
    {
        $policy = new RecordingSystemPolicy();
        $service = $this->service(new SystemFixedClock(1790000000), $policy, 60);

        self::assertFalse($service->accepts('/safe%3F/%2E%2E/admin'));
        self::assertNull($service->issue('/safe%3F/%2E%2E/admin'));
        self::assertSame([], $policy->inspectionTargets);
    }

    public function testHostPolicyRejectionMakesIssueReturnNullAfterOnePolicyCall(): void
    {
        $policy = new SystemPolicy(false);
        $service = $this->service(new SystemFixedClock(1790000000), $policy, 60);

        self::assertNull($service->issue('/orders%2F15'));
        self::assertSame(1, $policy->calls);
    }

    public function testHostPolicyThrowableIdentityPropagatesThroughPublicServiceBoundary(): void
    {
        $exception = new RuntimeException('preconstructed host policy failure');
        $service = $this->service(
            new SystemFixedClock(1790000000),
            new ThrowingSystemPolicy($exception),
            60,
        );

        try {
            $service->accepts('/orders%2F15');
            self::fail('The preconstructed Host policy exception must propagate.');
        } catch (Throwable $actual) {
            self::assertSame($exception, $actual);
        }
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
        yield 'path whitespace' => ['target' => '/orders/%20item', 'case' => 'decoded path SPACE must be rejected'];
        yield 'path whitespace with query' => ['target' => '/p%20x?q=1', 'case' => 'decoded path SPACE must be rejected even with a query'];
        yield 'query LF' => ['target' => '/p?q=a%0Ab', 'case' => 'decoded query LF must be rejected'];
        yield 'query CR' => ['target' => '/p?q=a%0Db', 'case' => 'decoded query CR must be rejected'];
        yield 'query NUL' => ['target' => '/p?q=%00', 'case' => 'decoded query NUL must be rejected'];
        yield 'query DEL' => ['target' => '/p?q=%7F', 'case' => 'decoded query DEL must be rejected'];
        yield 'query backslash' => ['target' => '/p?q=a%5Cb', 'case' => 'decoded query backslash must be rejected'];
        yield 'query fragment marker' => ['target' => '/p?q=a%23b', 'case' => 'decoded query fragment marker must be rejected'];
        yield 'query second-stage escape' => ['target' => '/p?q=%2520', 'case' => 'second-stage percent escape in the query must be rejected'];
        yield 'raw query SPACE' => ['target' => '/p?q=two words', 'case' => 'raw SPACE in the query must be rejected'];
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
        yield 'decoded question boundary dot-dot segment' => ['target' => '/safe%3F/%2E%2E/admin', 'case' => 'decoded raw-path dot-dot segment must be rejected'];
        yield 'decoded question boundary dot segment' => ['target' => '/safe%3F/%2E/admin', 'case' => 'decoded raw-path dot segment must be rejected'];
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
    public int $calls = 0;

    public function __construct(public bool $allowed) {}

    public function allows(string $inspectionTarget): bool
    {
        $this->calls++;

        return $this->allowed && str_starts_with($inspectionTarget, '/orders/15');
    }
}

final class RecordingSystemPolicy implements ReturnTargetRestrictionPolicyInterface
{
    /**
     * @var list<string>
     */
    public array $inspectionTargets = [];

    public function reset(): void
    {
        $this->inspectionTargets = [];
    }

    public function allows(string $inspectionTarget): bool
    {
        $this->inspectionTargets[] = $inspectionTarget;

        return true;
    }
}

final class ThrowingSystemPolicy implements ReturnTargetRestrictionPolicyInterface
{
    public function __construct(private readonly RuntimeException $exception) {}

    public function allows(string $inspectionTarget): bool
    {
        throw $this->exception;
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
