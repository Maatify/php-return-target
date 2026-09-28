<?php

declare(strict_types=1);

namespace Maatify\ReturnTarget\Tests\Unit\Adapter;

use DateTimeImmutable;
use Maatify\Crypto\HKDF\HKDFContext;
use Maatify\Crypto\HKDF\HKDFService;
use Maatify\Crypto\KeyRotation\CryptoKeyInterface;
use Maatify\Crypto\KeyRotation\DTO\CryptoKeyDTO;
use Maatify\Crypto\KeyRotation\Exceptions\KeyNotFoundException;
use Maatify\Crypto\KeyRotation\KeyProviderInterface;
use Maatify\Crypto\KeyRotation\KeyStatusEnum;
use Maatify\Crypto\KeyRotation\Providers\InMemoryKeyProvider;
use Maatify\ReturnTarget\Adapter\HmacReturnTargetTokenCodec;
use Maatify\ReturnTarget\Config\ReturnTargetConfig;
use Maatify\ReturnTarget\DTO\VerifiedTokenPayloadDTO;
use Maatify\ReturnTarget\Exception\ReturnTargetCryptoConfigurationException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

final class HmacReturnTargetTokenCodecTest extends TestCase
{
    private const string AUDIENCE = 'admin-auth';

    private const string TARGET = '/orders/15?tab=payment';

    private const int EXPIRATION = 1790000000;

    public function testIssueUsesTheCanonicalFourSegmentWireFormatAndVerifies(): void
    {
        $codec = $this->codec($this->provider());

        $token = $codec->issue(self::TARGET, self::EXPIRATION);
        $segments = explode('.', $token);

        self::assertCount(4, $segments);
        self::assertSame('rt1', $segments[0]);
        self::assertSame('key-1', $this->decodeBase64Url($segments[1]));
        self::assertSame(
            '{"aud":"admin-auth","t":"/orders/15?tab=payment","exp":1790000000}',
            $this->decodeBase64Url($segments[2]),
        );
        self::assertSame(32, strlen($this->decodeBase64Url($segments[3])));
        self::assertNotSame('', rtrim($segments[1] . $segments[2] . $segments[3], '='));

        $verified = $codec->verify($token, self::EXPIRATION - 1);

        self::assertInstanceOf(VerifiedTokenPayloadDTO::class, $verified);
        self::assertSame(self::TARGET, $verified->target);
        self::assertSame(self::EXPIRATION, $verified->expiresAt);
    }

    public function testIssueRejectsEmptyActiveKeyId(): void
    {
        $this->expectException(ReturnTargetCryptoConfigurationException::class);

        $this->codec($this->provider($this->key(id: '')))->issue(self::TARGET, self::EXPIRATION);
    }

    public function testIssueMapsShortHkdfRootAndPreservesPrevious(): void
    {
        $this->expectException(ReturnTargetCryptoConfigurationException::class);
        $this->expectExceptionMessage('Unable to derive');

        try {
            $this->codec($this->provider($this->key(material: 'short')))->issue(self::TARGET, self::EXPIRATION);
        } catch (ReturnTargetCryptoConfigurationException $exception) {
            self::assertInstanceOf(\Maatify\Crypto\HKDF\Exceptions\HKDFException::class, $exception->getPrevious());
            throw $exception;
        }
    }

    public function testIssueMapsNoActiveAndMultipleActiveKeyState(): void
    {
        foreach ([[], [$this->key(), $this->key(id: 'key-2')]] as $keys) {
            try {
                $this->codec(new StubKeyProvider($keys))->issue(self::TARGET, self::EXPIRATION);
                self::fail('Expected crypto configuration failure.');
            } catch (ReturnTargetCryptoConfigurationException $exception) {
                self::assertThat(
                    $exception->getPrevious(),
                    self::logicalOr(
                        self::isInstanceOf(\Maatify\Crypto\KeyRotation\Exceptions\NoActiveKeyException::class),
                        self::isInstanceOf(\Maatify\Crypto\KeyRotation\Exceptions\MultipleActiveKeysException::class),
                    ),
                );
            }
        }
    }

    public function testIssueRejectsTokenLongerThan4096Bytes(): void
    {
        $this->expectException(ReturnTargetCryptoConfigurationException::class);

        $this->codec($this->provider())->issue(str_repeat('t', 4050), self::EXPIRATION);
    }

    public function testVerifyRejectsMalformedWireRepresentationsBeforeCrypto(): void
    {
        $token = $this->codec($this->provider())->issue(self::TARGET, self::EXPIRATION);
        $segments = explode('.', $token);
        $cases = [
            'too long' => str_repeat('x', 4097),
            'wrong version' => 'rt2.' . $segments[1] . '.' . $segments[2] . '.' . $segments[3],
            'wrong segment count' => $segments[0] . '.' . $segments[1] . '.' . $segments[2],
            'empty segment' => $segments[0] . '..' . $segments[2] . '.' . $segments[3],
            'padding' => $segments[0] . '.' . $segments[1] . '=' . '.' . $segments[2] . '.' . $segments[3],
            'invalid alphabet' => $segments[0] . '.!' . $segments[2] . '.' . $segments[3],
            'noncanonical encoding' => $segments[0] . '.' . $segments[1] . '.YR.' . $segments[3],
            'short signature' => $segments[0] . '.' . $segments[1] . '.' . $segments[2] . '.AA',
        ];

        foreach ($cases as $name => $malformed) {
            self::assertNull($this->codec($this->provider())->verify($malformed, self::EXPIRATION - 1), $name);
        }
    }

    public function testVerifyRejectsSignatureMismatchUnknownKeyAudienceAndExpiryBoundary(): void
    {
        $codec = $this->codec($this->provider());
        $token = $codec->issue(self::TARGET, self::EXPIRATION);
        $segments = explode('.', $token);
        $tampered = $segments[0] . '.' . $segments[1] . '.' . $segments[2] . '.' . $this->encodeBase64Url(str_repeat('x', 32));

        self::assertNull($codec->verify($tampered, self::EXPIRATION - 1));
        self::assertNull($this->codec($this->provider())->verify($this->signedPayload('other-audience', self::TARGET, self::EXPIRATION), self::EXPIRATION - 1));
        self::assertNull($codec->verify($token, self::EXPIRATION));
        self::assertNull($codec->verify($token, self::EXPIRATION + 1));
        self::assertInstanceOf(VerifiedTokenPayloadDTO::class, $codec->verify($token, self::EXPIRATION - 1));

        $unknown = $this->signedPayload(self::AUDIENCE, self::TARGET, self::EXPIRATION, 'missing');
        self::assertNull($this->codec($this->provider())->verify($unknown, self::EXPIRATION - 1));
    }

    public function testVerifyRejectsMalformedCanonicalJsonAfterValidSignature(): void
    {
        $payloads = [
            '{',
            '{"aud":"admin-auth","t":"/orders/15?tab=payment","exp":1790000000,"extra":true}',
            '{"aud":"admin-auth","exp":1790000000}',
            '{"aud":7,"t":"/orders/15?tab=payment","exp":1790000000}',
            '{"aud":"admin-auth","t":"/orders/15?tab=payment","exp":"1790000000"}',
            '{"t":"/orders/15?tab=payment","aud":"admin-auth","exp":1790000000}',
            "{\n  \"aud\":\"admin-auth\",\n  \"t\":\"/orders/15?tab=payment\",\n  \"exp\":1790000000\n}",
        ];

        foreach ($payloads as $payload) {
            self::assertNull(
                $this->codec($this->provider())->verify($this->signedPayloadBytes($payload), self::EXPIRATION - 1),
            );
        }
    }

    public function testDirectFindKeyNotFoundReturnsNull(): void
    {
        $token = $this->codec($this->provider())->issue(self::TARGET, self::EXPIRATION);
        $provider = $this->provider(findFailure: new KeyNotFoundException('unknown'));

        self::assertNull($this->codec($provider)->verify($token, self::EXPIRATION - 1));
    }

    public function testDirectFindOtherThrowablePropagatesWithoutInspectingPrevious(): void
    {
        $token = $this->codec($this->provider())->issue(self::TARGET, self::EXPIRATION);
        $failure = new RuntimeException('provider failure', 0, new KeyNotFoundException('nested'));

        try {
            $this->codec($this->provider(findFailure: $failure))->verify($token, self::EXPIRATION - 1);
            self::fail('Expected provider failure.');
        } catch (RuntimeException $exception) {
            self::assertSame($failure, $exception);
        }
    }

    public function testSuccessfulDirectFindFollowedByKeyNotFoundBecomesConfigurationFailure(): void
    {
        $token = $this->codec($this->provider())->issue(self::TARGET, self::EXPIRATION);
        $provider = $this->provider(findFailure: new KeyNotFoundException('decryption lookup failure'), failAfterFinds: 1);

        try {
            $this->codec($provider)->verify($token, self::EXPIRATION - 1);
            self::fail('Expected crypto configuration failure.');
        } catch (ReturnTargetCryptoConfigurationException $exception) {
            self::assertInstanceOf(KeyNotFoundException::class, $exception->getPrevious());
        }
    }

    private function provider(?CryptoKeyInterface $key = null, ?Throwable $findFailure = null, int $failAfterFinds = 0): InMemoryKeyProvider|StubKeyProvider
    {
        if ($findFailure !== null) {
            return new StubKeyProvider([$key ?? $this->key()], $findFailure, $failAfterFinds);
        }

        return new InMemoryKeyProvider([$key ?? $this->key()]);
    }

    private function codec(KeyProviderInterface $provider): HmacReturnTargetTokenCodec
    {
        return new HmacReturnTargetTokenCodec(new ReturnTargetConfig(self::AUDIENCE, 3600), $provider);
    }

    private function key(
        string $id = 'key-1',
        string $material = '01234567890123456789012345678901',
        KeyStatusEnum $status = KeyStatusEnum::ACTIVE,
    ): CryptoKeyDTO {
        return new CryptoKeyDTO($id, $material, $status, new DateTimeImmutable('@1'));
    }

    private function signedPayload(string $audience, string $target, int $expiresAt, string $keyId = 'key-1'): string
    {
        return $this->signedPayloadBytes(
            json_encode(
                ['aud' => $audience, 't' => $target, 'exp' => $expiresAt],
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
            ),
            $keyId,
        );
    }

    private function signedPayloadBytes(string $payloadBytes, string $keyId = 'key-1'): string
    {
        $kidSegment = $this->encodeBase64Url($keyId);
        $payloadSegment = $this->encodeBase64Url($payloadBytes);
        $signingInput = 'rt1.' . $kidSegment . '.' . $payloadSegment;
        $derivedKey = (new HKDFService())->deriveKey(
            '01234567890123456789012345678901',
            new HKDFContext('return-target:token:v1'),
            32,
        );

        return $signingInput . '.' . $this->encodeBase64Url(hash_hmac('sha256', $signingInput, $derivedKey, true));
    }

    private function encodeBase64Url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    private function decodeBase64Url(string $segment): string
    {
        $decoded = base64_decode(
            strtr($segment, '-_', '+/') . str_repeat('=', (4 - strlen($segment) % 4) % 4),
            true,
        );
        if ($decoded === false) {
            throw new RuntimeException('Test token contains invalid Base64URL.');
        }

        return $decoded;
    }
}

final class StubKeyProvider implements KeyProviderInterface
{
    /** @var list<CryptoKeyInterface> */
    private array $keys = [];

    private int $findCount = 0;

    /**
     * @param iterable<CryptoKeyInterface> $keys
     */
    public function __construct(
        iterable $keys,
        private readonly ?Throwable $findFailure = null,
        private readonly int $failAfterFinds = 0,
    ) {
        foreach ($keys as $key) {
            $this->keys[] = $key;
        }
    }

    public function all(): iterable
    {
        return $this->keys;
    }

    public function active(): CryptoKeyInterface
    {
        foreach ($this->keys as $key) {
            if ($key->status() === KeyStatusEnum::ACTIVE) {
                return $key;
            }
        }

        throw new \Maatify\Crypto\KeyRotation\Exceptions\NoActiveKeyException('No active key.');
    }

    public function find(string $keyId): CryptoKeyInterface
    {
        $this->findCount++;
        if ($this->findFailure !== null && $this->findCount > $this->failAfterFinds) {
            throw $this->findFailure;
        }

        foreach ($this->keys as $key) {
            if ($key->id() === $keyId) {
                return $key;
            }
        }

        throw new KeyNotFoundException('Unknown key.');
    }

    public function promote(string $keyId): void
    {
        unset($keyId);
    }
}
