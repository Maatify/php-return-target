<?php

declare(strict_types=1);

use Maatify\Crypto\KeyRotation\DTO\CryptoKeyDTO;
use Maatify\Crypto\KeyRotation\KeyStatusEnum;
use Maatify\Crypto\KeyRotation\Providers\InMemoryKeyProvider;
use Maatify\ReturnTarget\Config\ReturnTargetConfig;
use Maatify\ReturnTarget\DTO\VerifiedReturnTargetDTO;
use Maatify\ReturnTarget\Service\HmacReturnTargetService;
use Maatify\ReturnTarget\Validation\ReturnTargetRestrictionPolicyInterface;
use Maatify\SharedCommon\Contracts\ClockInterface;

require __DIR__ . '/vendor/autoload.php';

final class ConsumerFixedClock implements ClockInterface
{
    public function __construct(public int $timestamp) {}

    public function now(): \DateTimeImmutable
    {
        return (new \DateTimeImmutable('@' . $this->timestamp))->setTimezone(new \DateTimeZone('UTC'));
    }

    public function getTimezone(): \DateTimeZone
    {
        return new \DateTimeZone('UTC');
    }
}

final class ConsumerRestrictionPolicy implements ReturnTargetRestrictionPolicyInterface
{
    public bool $allowsTarget = true;

    public function allows(string $inspectionTarget): bool
    {
        return $this->allowsTarget && str_starts_with($inspectionTarget, '/orders/15');
    }
}

function expect(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

$clock = new ConsumerFixedClock(1790000000);
$policy = new ConsumerRestrictionPolicy();
$service = new HmacReturnTargetService(
    new ReturnTargetConfig('consumer-demo', 60),
    new InMemoryKeyProvider([
        new CryptoKeyDTO(
            'consumer-example-key',
            '01234567890123456789012345678901',
            KeyStatusEnum::ACTIVE,
            new \DateTimeImmutable('@1'),
        ),
    ]),
    $clock,
    $policy,
);

$target = '/orders%2F15?tab=a%2Fb';
expect($service->accepts($target), 'Accepted target was rejected.');
expect(! $service->accepts('https://example.test'), 'Unsafe target was accepted.');

$token = $service->issue($target);
if (! is_string($token) || $token === '') {
    throw new RuntimeException('Accepted target did not produce an opaque token.');
}

$verified = $service->verify($token);
if (! $verified instanceof VerifiedReturnTargetDTO) {
    throw new RuntimeException('Token did not produce the public DTO.');
}
expect($verified->target === $target, 'The exact original target was not preserved.');
expect($verified->expiresAt === 1790000060, 'Expiry was not derived from the Host clock.');
$policy->allowsTarget = false;
expect($service->verify($token) === null, 'Current policy did not reject a previously issued token.');

$policy->allowsTarget = true;
$clock->timestamp = 1790000060;
expect($service->verify($token) === null, 'Expired token was accepted at the expiry boundary.');

echo "Consumer verification passed: public issue/verify, exact target, policy, safety, and expiry.\n";
