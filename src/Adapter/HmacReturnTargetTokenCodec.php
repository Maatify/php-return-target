<?php

declare(strict_types=1);

namespace Maatify\ReturnTarget\Adapter;

use JsonException;
use Maatify\Crypto\HKDF\HKDFContext;
use Maatify\Crypto\HKDF\HKDFService;
use Maatify\Crypto\HKDF\Exceptions\HKDFException;
use Maatify\Crypto\KeyRotation\Exceptions\DecryptionKeyNotAllowedException;
use Maatify\Crypto\KeyRotation\Exceptions\KeyNotFoundException;
use Maatify\Crypto\KeyRotation\Exceptions\MultipleActiveKeysException;
use Maatify\Crypto\KeyRotation\Exceptions\NoActiveKeyException;
use Maatify\Crypto\KeyRotation\KeyProviderInterface;
use Maatify\Crypto\KeyRotation\KeyRotationService;
use Maatify\Crypto\KeyRotation\Policy\StrictSingleActiveKeyPolicy;
use Maatify\ReturnTarget\Config\ReturnTargetConfig;
use Maatify\ReturnTarget\DTO\VerifiedTokenPayloadDTO;
use Maatify\ReturnTarget\Exception\ReturnTargetCryptoConfigurationException;

/**
 * @internal
 *
 * Canonical internal rt1 token and HMAC codec. Hosts cannot replace or configure
 * its key-rotation, HKDF, encoding, parsing, or signing composition.
 */
final class HmacReturnTargetTokenCodec
{
    private const string VERSION = 'rt1';

    private const string HKDF_CONTEXT = 'return-target:token:v1';

    private const int DERIVED_KEY_LENGTH = 32;

    private const int MAX_TOKEN_LENGTH = 4096;

    private readonly StrictSingleActiveKeyPolicy $keyPolicy;

    private readonly KeyRotationService $keyRotationService;

    private readonly HKDFService $hkdfService;

    /**
     * Creates the codec with package-owned strict key-rotation and HKDF composition.
     *
     * The configuration supplies the canonical audience, while the provider supplies
     * Host-owned key material without allowing replacement of the crypto composition.
     */
    public function __construct(
        private readonly ReturnTargetConfig $config,
        private readonly KeyProviderInterface $keyProvider,
    ) {
        $this->keyPolicy = new StrictSingleActiveKeyPolicy();
        $this->keyRotationService = new KeyRotationService($keyProvider, $this->keyPolicy);
        $this->hkdfService = new HKDFService();
    }

    /**
     * Issues a canonical signed rt1 token for the exact target and expiration.
     *
     * @throws ReturnTargetCryptoConfigurationException when key state, key material,
     *     the active key ID, or the generated token violates the canonical contract.
     * @throws JsonException when canonical payload encoding fails.
     * Unclassified external or provider failures propagate unchanged.
     */
    public function issue(string $target, int $expiresAt): string
    {
        $this->validateKeyStateForConfiguration();
        $key = $this->resolveActiveKey();

        if ($key->id() === '') {
            throw new ReturnTargetCryptoConfigurationException('The active key ID must not be empty.');
        }

        $derivedKey = $this->deriveSigningKey($key->material());
        $payload = [
            'aud' => $this->config->audience,
            't' => $target,
            'exp' => $expiresAt,
        ];
        $payloadBytes = $this->encodePayload($payload);
        $kidSegment = $this->encodeBase64Url($key->id());
        $payloadSegment = $this->encodeBase64Url($payloadBytes);
        $signingInput = self::VERSION . '.' . $kidSegment . '.' . $payloadSegment;
        $signatureSegment = $this->encodeBase64Url(hash_hmac('sha256', $signingInput, $derivedKey, true));
        $token = $signingInput . '.' . $signatureSegment;

        if (strlen($token) > self::MAX_TOKEN_LENGTH) {
            throw new ReturnTargetCryptoConfigurationException('The generated token exceeds the maximum length.');
        }

        return $token;
    }

    /**
     * Verifies a canonical token and returns its authenticated payload when valid.
     *
     * Normal malformed, untrusted, expired, or policy-rejected tokens return null.
     * Key-state, HKDF, and inconsistent decryption-key failures throw
     * ReturnTargetCryptoConfigurationException with the original failure preserved.
     * Infrastructure and unclassified provider failures propagate unchanged.
     */
    public function verify(string $token, int $nowTimestamp): ?VerifiedTokenPayloadDTO
    {
        if (strlen($token) > self::MAX_TOKEN_LENGTH) {
            return null;
        }

        $segments = explode('.', $token);
        if (count($segments) !== 4 || in_array('', $segments, true) || $segments[0] !== self::VERSION) {
            return null;
        }

        $kid = $this->decodeBase64Url($segments[1]);
        $payloadBytes = $this->decodeBase64Url($segments[2]);
        $signature = $this->decodeBase64Url($segments[3]);
        if ($kid === null || $payloadBytes === null || $signature === null || strlen($signature) !== self::DERIVED_KEY_LENGTH) {
            return null;
        }

        $this->validateKeyStateForConfiguration();

        try {
            $this->keyProvider->find($kid);
        } catch (KeyNotFoundException) {
            return null;
        }

        $key = $this->resolveDecryptionKey($kid);
        if ($key === null) {
            return null;
        }
        $derivedKey = $this->deriveSigningKey($key->material());
        $signingInput = $segments[0] . '.' . $segments[1] . '.' . $segments[2];
        $expectedSignature = hash_hmac('sha256', $signingInput, $derivedKey, true);
        if (! hash_equals($expectedSignature, $signature)) {
            return null;
        }

        try {
            /** @var mixed $decoded */
            $decoded = json_decode($payloadBytes, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (
            ! is_array($decoded)
            || array_keys($decoded) !== ['aud', 't', 'exp']
            || ! is_string($decoded['aud'])
            || ! is_string($decoded['t'])
            || ! is_int($decoded['exp'])
        ) {
            return null;
        }

        $canonicalPayload = [
            'aud' => $decoded['aud'],
            't' => $decoded['t'],
            'exp' => $decoded['exp'],
        ];
        try {
            if ($this->encodePayload($canonicalPayload) !== $payloadBytes) {
                return null;
            }
        } catch (JsonException) {
            return null;
        }

        if ($decoded['aud'] !== $this->config->audience || $nowTimestamp >= $decoded['exp']) {
            return null;
        }

        return new VerifiedTokenPayloadDTO(
            target: $decoded['t'],
            expiresAt: $decoded['exp'],
        );
    }

    /**
     * @param array{aud: string, t: string, exp: int} $payload
     */
    private function encodePayload(array $payload): string
    {
        return json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );
    }

    /**
     * Derives the fixed 32-byte signing key and classifies HKDF failures.
     *
     * @throws ReturnTargetCryptoConfigurationException with the HKDF failure as previous.
     */
    private function deriveSigningKey(string $rootKey): string
    {
        try {
            return $this->hkdfService->deriveKey(
                $rootKey,
                new HKDFContext(self::HKDF_CONTEXT),
                self::DERIVED_KEY_LENGTH,
            );
        } catch (HKDFException $exception) {
            throw new ReturnTargetCryptoConfigurationException(
                'Unable to derive the canonical signing key.',
                previous: $exception,
            );
        }
    }

    /**
     * Enforces exactly one active key and maps invariant failures to configuration errors.
     *
     * @throws ReturnTargetCryptoConfigurationException when the active-key invariant fails.
     */
    private function validateKeyStateForConfiguration(): void
    {
        try {
            $this->keyPolicy->validate($this->keyProvider);
        } catch (NoActiveKeyException|MultipleActiveKeysException $exception) {
            throw new ReturnTargetCryptoConfigurationException(
                'The key provider does not have exactly one active key.',
                previous: $exception,
            );
        }
    }

    /**
     * Resolves the active encryption key after strict state validation.
     *
     * @throws ReturnTargetCryptoConfigurationException for classified active-key failures.
     */
    private function resolveActiveKey(): \Maatify\Crypto\KeyRotation\CryptoKeyInterface
    {
        try {
            return $this->keyRotationService->activeEncryptionKey();
        } catch (NoActiveKeyException|MultipleActiveKeysException|KeyNotFoundException $exception) {
            throw new ReturnTargetCryptoConfigurationException(
                'Unable to resolve the active encryption key.',
                previous: $exception,
            );
        }
    }

    /**
     * Resolves a key after the direct existence probe and classifies policy outcomes.
     *
     * Decryption disallowance returns null; a post-probe missing key becomes a
     * configuration exception preserving the crypto failure as previous.
     *
     * @return \Maatify\Crypto\KeyRotation\CryptoKeyInterface|null
     * @throws ReturnTargetCryptoConfigurationException for inconsistent key state.
     */
    private function resolveDecryptionKey(string $keyId): ?\Maatify\Crypto\KeyRotation\CryptoKeyInterface
    {
        try {
            return $this->keyRotationService->decryptionKey($keyId);
        } catch (DecryptionKeyNotAllowedException) {
            return null;
        } catch (KeyNotFoundException $exception) {
            throw new ReturnTargetCryptoConfigurationException(
                'The key provider returned an inconsistent decryption key state.',
                previous: $exception,
            );
        }
    }

    private function encodeBase64Url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    /**
     * Strictly decodes one required canonical unpadded Base64URL segment.
     *
     * Invalid alphabet, padding, decoding, length, or re-encoding returns null.
     */
    private function decodeBase64Url(string $segment): ?string
    {
        if ($segment === '' || str_contains($segment, '=') || strlen($segment) % 4 === 1) {
            return null;
        }

        $translated = strtr($segment, '-_', '+/');
        if (strspn($translated, 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/') !== strlen($translated)) {
            return null;
        }

        $paddingLength = (4 - strlen($translated) % 4) % 4;
        $decoded = base64_decode($translated . str_repeat('=', $paddingLength), true);
        if ($decoded === false || $this->encodeBase64Url($decoded) !== $segment) {
            return null;
        }

        return $decoded;
    }
}
