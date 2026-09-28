<?php

declare(strict_types=1);

namespace Maatify\ReturnTarget\Validation;

/**
 * Validates canonical generic targets and returns a single-decoded inspection view.
 *
 * The inspection view is never a replacement representation: callers retain and use
 * the original input for token payloads and public output. Decoding is performed
 * exactly once, with plus signs preserved literally.
 */
final class CanonicalReturnTargetValidator
{
    private const int MAX_TARGET_BYTES = 2048;

    /**
     * Returns the validated single-decoded inspection target, or null for rejection.
     */
    public function inspect(string $target): ?string
    {
        if (! $this->isRawValid($target)) {
            return null;
        }

        $decoded = $this->decodeOnce($target);
        if (! $this->isDecodedValid($decoded)) {
            return null;
        }

        return $decoded;
    }

    private function isRawValid(string $target): bool
    {
        if ($target === '' || strlen($target) > self::MAX_TARGET_BYTES || ! str_starts_with($target, '/') || str_starts_with($target, '//')) {
            return false;
        }

        $questionPosition = strpos($target, '?');
        $path = $questionPosition === false ? $target : substr($target, 0, $questionPosition);
        $query = $questionPosition === false ? '' : substr($target, $questionPosition + 1);

        if ($this->hasDotSegment($path) || ! $this->isRawComponentValid($path, allowSlash: true)) {
            return false;
        }

        if ($questionPosition !== false && ! $this->isRawComponentValid($query, allowSlash: true, allowQuestionMark: true)) {
            return false;
        }

        return true;
    }

    private function isRawComponentValid(string $component, bool $allowSlash, bool $allowQuestionMark = false): bool
    {
        for ($i = 0, $length = strlen($component); $i < $length; $i++) {
            $byte = ord($component[$i]);
            if ($byte < 0x20 || $byte === 0x20 || $byte === 0x7f || $component[$i] === '\\' || $component[$i] === '#') {
                return false;
            }

            if ($component[$i] === '%') {
                if ($i + 2 >= $length || ! $this->isHex($component[$i + 1]) || ! $this->isHex($component[$i + 2])) {
                    return false;
                }
                $i += 2;
                continue;
            }

            if ($this->isUnreserved($component[$i]) || $this->isSubDelimiter($component[$i]) || $component[$i] === ':' || $component[$i] === '@') {
                continue;
            }

            if (($allowSlash && $component[$i] === '/') || ($allowQuestionMark && $component[$i] === '?')) {
                continue;
            }

            return false;
        }

        return true;
    }

    private function decodeOnce(string $target): string
    {
        $decoded = '';
        for ($i = 0, $length = strlen($target); $i < $length; $i++) {
            if ($target[$i] !== '%') {
                $decoded .= $target[$i];
                continue;
            }

            $decoded .= chr($this->hexValue($target[$i + 1]) * 16 + $this->hexValue($target[$i + 2]));
            $i += 2;
        }

        return $decoded;
    }

    private function isDecodedValid(string $target): bool
    {
        if (! str_starts_with($target, '/') || str_starts_with($target, '//') || $this->hasDotSegment($this->pathPart($target))) {
            return false;
        }

        for ($i = 0, $length = strlen($target); $i < $length; $i++) {
            $byte = ord($target[$i]);
            if ($byte < 0x20 || $byte === 0x20 || $byte === 0x7f || $target[$i] === '\\' || $target[$i] === '#' || ($target[$i] === '%' && $i + 2 < $length && $this->isHex($target[$i + 1]) && $this->isHex($target[$i + 2]))) {
                return false;
            }
        }

        return true;
    }

    private function pathPart(string $target): string
    {
        $questionPosition = strpos($target, '?');

        return $questionPosition === false ? $target : substr($target, 0, $questionPosition);
    }

    private function hasDotSegment(string $path): bool
    {
        foreach (explode('/', $path) as $segment) {
            if ($segment === '.' || $segment === '..') {
                return true;
            }
        }

        return false;
    }

    private function isHex(string $character): bool
    {
        return ($character >= '0' && $character <= '9') || ($character >= 'a' && $character <= 'f') || ($character >= 'A' && $character <= 'F');
    }

    private function hexValue(string $character): int
    {
        $byte = ord($character);

        return $byte <= ord('9') ? $byte - ord('0') : ($byte <= ord('F') ? $byte - ord('A') + 10 : $byte - ord('a') + 10);
    }

    private function isUnreserved(string $character): bool
    {
        $byte = ord($character);

        return ($byte >= 0x41 && $byte <= 0x5a) || ($byte >= 0x61 && $byte <= 0x7a) || ($byte >= 0x30 && $byte <= 0x39) || str_contains('-._~', $character);
    }

    private function isSubDelimiter(string $character): bool
    {
        return str_contains("!$&'()*+,;=", $character);
    }
}
