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
            '/p%20x?q=1',
            '/p%3F%20x',
            '/p?q=a b',
            '/p?q=a%0Ab',
            '/p?q=a%0Db',
            '/p?q=%00',
            '/p?q=%09',
            '/p?q=%7F',
            '/p?q=a%5Cb',
            '/p?q=a%23b',
            '/p?q=%2520',
            '/p?q=%2F%2F%20%252F',
            '/a%0Ab',
            '/a%7Fb',
            '/a%25E0%25A4%25A',
            '/safe%3F/%2E%2E/admin',
            '/safe%3F/%2E/admin',
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

    public function testDecodedSpaceIsAcceptedOnlyWhenItOriginatesFromTheQuery(): void
    {
        $validator = new CanonicalReturnTargetValidator();

        self::assertSame('/p?q=two words', $validator->inspect('/p?q=two%20words'));
        self::assertSame('/p?q=a b c', $validator->inspect('/p?q=a%20b%20c'));
        self::assertSame('/p?q= ', $validator->inspect('/p?q=%20'));
        self::assertSame('/p?a=1&b=x y?z', $validator->inspect('/p?a=1&b=x%20y?z'));
        self::assertNull($validator->inspect('/p%20x?q=1'));
        self::assertNull($validator->inspect('/p%20x?q=a%20b'));
    }

    public function testPlusRemainsLiteralAndIsNotDecodedToSpace(): void
    {
        $validator = new CanonicalReturnTargetValidator();

        self::assertSame('/p?q=a+b', $validator->inspect('/p?q=a+b'));
        self::assertSame('/p?q=a+b', $validator->inspect('/p?q=a%2Bb'));
    }
}
