<?php

declare(strict_types=1);

namespace Maatify\ReturnTarget\Tests\Unit\Exception;

use Maatify\Exceptions\Enum\ErrorCodeEnum;
use Maatify\ReturnTarget\Exception\InvalidReturnTargetConfigurationException;
use Maatify\ReturnTarget\Exception\ReturnTargetCryptoConfigurationException;
use Maatify\ReturnTarget\Exception\ReturnTargetExceptionInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;

final class ReturnTargetExceptionContractTest extends TestCase
{
    public function testInvalidConfigurationExceptionContract(): void
    {
        $previous = new RuntimeException('previous failure');
        $exception = new InvalidReturnTargetConfigurationException(
            'invalid configuration',
            17,
            $previous,
        );

        self::assertInstanceOf(ReturnTargetExceptionInterface::class, $exception);
        self::assertSame(ErrorCodeEnum::INVALID_ARGUMENT, $exception->getErrorCode());
        self::assertSame('invalid configuration', $exception->getMessage());
        self::assertSame(17, $exception->getCode());
        self::assertSame($previous, $exception->getPrevious());

        $this->assertPackageOwnedConstructorSurface(InvalidReturnTargetConfigurationException::class);
    }

    public function testCryptoConfigurationExceptionContract(): void
    {
        $previous = new RuntimeException('previous failure');
        $exception = new ReturnTargetCryptoConfigurationException(
            'crypto configuration failure',
            23,
            $previous,
        );

        self::assertInstanceOf(ReturnTargetExceptionInterface::class, $exception);
        self::assertSame(ErrorCodeEnum::MAATIFY_ERROR, $exception->getErrorCode());
        self::assertSame('crypto configuration failure', $exception->getMessage());
        self::assertSame(23, $exception->getCode());
        self::assertSame($previous, $exception->getPrevious());

        $this->assertPackageOwnedConstructorSurface(ReturnTargetCryptoConfigurationException::class);
    }

    /**
     * @param class-string<\Throwable> $exceptionClass
     */
    private function assertPackageOwnedConstructorSurface(string $exceptionClass): void
    {
        $constructor = (new ReflectionClass($exceptionClass))->getConstructor();

        self::assertNotNull($constructor);
        self::assertSame($exceptionClass, $constructor->getDeclaringClass()->getName());
        self::assertSame(
            ['message', 'code', 'previous'],
            array_map(
                static fn($parameter): string => $parameter->getName(),
                $constructor->getParameters(),
            ),
        );
    }
}
