<?php

declare(strict_types=1);

use Maatify\Crypto\KeyRotation\DTO\CryptoKeyDTO;
use Maatify\Crypto\KeyRotation\KeyStatusEnum;
use Maatify\Crypto\KeyRotation\Providers\InMemoryKeyProvider;
use Maatify\ReturnTarget\Config\ReturnTargetConfig;
use Maatify\ReturnTarget\Service\HmacReturnTargetService;
use Maatify\SharedCommon\Contracts\ClockInterface;

require dirname(__DIR__) . '/vendor/autoload.php';

final class ExampleClock implements ClockInterface
{
    public function __construct(private readonly int $timestamp) {}

    public function now(): \DateTimeImmutable
    {
        return (new \DateTimeImmutable('@' . $this->timestamp))->setTimezone(new \DateTimeZone('UTC'));
    }

    public function getTimezone(): \DateTimeZone
    {
        return new \DateTimeZone('UTC');
    }
}

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
    new ExampleClock(1790000000),
);

$target = '/account/orders?status=paid';
$token = $service->issue($target);

if ($token === null) {
    fwrite(STDERR, "Example target was rejected.\n");
    exit(1);
}

$verified = $service->verify($token);
if ($verified === null || $verified->target !== $target) {
    fwrite(STDERR, "Example token could not be verified.\n");
    exit(1);
}

echo json_encode($verified, JSON_THROW_ON_ERROR) . "\n";
