<?php

declare(strict_types=1);

namespace Maatify\ReturnTarget\Tests\Unit\Validation;

use Maatify\ReturnTarget\Validation\CanonicalReturnTargetValidator;
use PHPUnit\Framework\TestCase;

final class CanonicalReturnTargetValidatorTest extends TestCase
{
    public function testAcceptedTargetsReturnSingleDecodedInspectionValues(): void
    {
        $validator = new CanonicalReturnTargetValidator();

        foreach (['/', '/orders', '/orders/15', '/orders/15?tab=payment', '/orders%2F15?tab=a/b?c'] as $target) {
            self::assertNotNull($validator->inspect($target), $target);
        }

        self::assertSame('/orders/15', $validator->inspect('/orders%2F15'));
        self::assertSame('/orders/15?tab=a/b?c', $validator->inspect('/orders%2F15?tab=a%2Fb?c'));
    }

    public function testRejectsRawAndEncodedUnsafeForms(): void
    {
        $validator = new CanonicalReturnTargetValidator();
        $cases = [
            '',
            'orders',
            '//orders',
            '/' . str_repeat('a', 2048),
            '/a\\b',
            '/a#b',
            "/a\0b",
            "/a\nb",
            "/a\x7Fb",
            '/a b',
            '/a%b',
            '/a%2',
            '/a%GG',
            '/%2F%2Fevil',
            '/a%5Cb',
            '/a%23b',
            '/a%20b',
            '/a%0Ab',
            '/a%7Fb',
            '/a%25E0%25A4%25A',
            '/./orders',
            '/../orders',
            '/a/./b',
            '/a/../b',
            '/a/%2E/b',
            '/a/%2e%2e/b',
        ];

        foreach ($cases as $target) {
            self::assertNull($validator->inspect($target), bin2hex($target));
        }
    }

    public function testQueryDotValuesAreNotPathDotSegments(): void
    {
        $validator = new CanonicalReturnTargetValidator();

        self::assertSame('/orders?next=..', $validator->inspect('/orders?next=..'));
        self::assertSame('/orders?next=..', $validator->inspect('/orders?next=%2E%2E'));
    }
}
