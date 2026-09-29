<?php

declare(strict_types=1);

namespace Maatify\ReturnTarget\Tests\System\Exception;

use Maatify\Exceptions\Enum\ErrorCodeEnum;
use Maatify\Exceptions\Exception\System\SystemMaatifyException;
use Maatify\ReturnTarget\Exception\ReturnTargetCryptoConfigurationException;
use Maatify\ReturnTarget\Exception\ReturnTargetExceptionInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ReturnTargetCryptoConfigurationExceptionSystemTest extends TestCase
{
    public function testPublicExceptionContractPreservesIdentityCodeAndPreviousThrowable(): void
    {
        $previous = new RuntimeException('crypto failure');
        $exception = new ReturnTargetCryptoConfigurationException('configuration failure', previous: $previous);

        self::assertInstanceOf(ReturnTargetExceptionInterface::class, $exception);
        self::assertInstanceOf(SystemMaatifyException::class, $exception);
        self::assertSame(ErrorCodeEnum::MAATIFY_ERROR, $exception->getErrorCode());
        self::assertSame($previous, $exception->getPrevious());
    }
}
