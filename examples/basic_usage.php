<?php

declare(strict_types=1);

use Maatify\Crypto\KeyRotation\DTO\CryptoKeyDTO;
use Maatify\Crypto\KeyRotation\KeyStatusEnum;
use Maatify\Crypto\KeyRotation\Providers\InMemoryKeyProvider;
use Maatify\ReturnTarget\Config\ReturnTargetConfig;
use Maatify\ReturnTarget\Service\HmacReturnTargetService;
use Maatify\ReturnTarget\Validation\ReturnTargetRestrictionPolicyInterface;
use Maatify\SharedCommon\Contracts\ClockInterface;

require dirname(__DIR__) . '/vendor/autoload.php';

final class ExampleClock implements ClockInterface
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

final class ExampleRestrictionPolicy implements ReturnTargetRestrictionPolicyInterface
{
    public bool $allowsTarget = true;

    public function allows(string $inspectionTarget): bool
    {
        return $this->allowsTarget && str_starts_with($inspectionTarget, '/orders/15');
    }
}

function failExample(string $message): never
{
    fwrite(STDERR, $message . "\n");
    exit(1);
}

function expectNull(mixed $value, string $message): void
{
    if ($value !== null) {
        failExample($message);
    }
}

$clock = new ExampleClock(1790000000);
$policy = new ExampleRestrictionPolicy();
$service = new HmacReturnTargetService(
    new ReturnTargetConfig('example-only', 300),
    new InMemoryKeyProvider([
        new CryptoKeyDTO(
            'example-only-key',
            'example-only-material-do-not-use-in-production',
            KeyStatusEnum::ACTIVE,
            new \DateTimeImmutable('@1'),
        ),
    ]),
    $clock,
    $policy,
);

$target = '/orders%2F15?tab=a%2Fb';
if (! $service->accepts($target)) {
    failExample('Example accepted target was rejected.');
}

if ($service->accepts('https://example.test')) {
    failExample('Example unsafe target was accepted.');
}

$token = $service->issue($target);

if ($token === null) {
    failExample('Example target did not produce a token.');
}

$verified = $service->verify($token);
if ($verified === null || $verified->target !== $target || $verified->expiresAt !== 1790000300) {
    failExample('Example token did not preserve the exact target and expiry.');
}

$policy->allowsTarget = false;
if ($service->verify($token) !== null) {
    failExample('Example current policy did not reject the token.');
}

$policy->allowsTarget = true;
$clock->timestamp = 1790000300;
expectNull($service->verify($token), 'Example expiry boundary did not reject the token.');

echo json_encode(['target' => $target, 'expiresAt' => 1790000300], JSON_THROW_ON_ERROR) . "\n";
