<?php

declare(strict_types=1);

namespace AbuseIpDb\Support;

/**
 * Typed, lenient accessors for decoded JSON arrays.
 *
 * @internal
 */
final class Arr
{
    /** @param array<mixed> $a */
    public static function str(array $a, string $k): ?string
    {
        return isset($a[$k]) && is_scalar($a[$k]) ? (string) $a[$k] : null;
    }

    /** @param array<mixed> $a */
    public static function int(array $a, string $k): ?int
    {
        return isset($a[$k]) && is_numeric($a[$k]) ? (int) $a[$k] : null;
    }

    /** @param array<mixed> $a */
    public static function bool(array $a, string $k): ?bool
    {
        return isset($a[$k]) && is_scalar($a[$k]) ? (bool) $a[$k] : null;
    }

    /** @param array<mixed> $a */
    public static function date(array $a, string $k): ?\DateTimeImmutable
    {
        $v = self::str($a, $k);
        if ($v === null || $v === '') {
            return null;
        }
        try {
            return new \DateTimeImmutable($v);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * @param array<mixed> $a
     *
     * @return list<array<mixed>>
     */
    public static function rows(array $a, string $k): array
    {
        $v = $a[$k] ?? [];

        return is_array($v) ? array_values(array_filter($v, 'is_array')) : [];
    }

    /**
     * @param array<mixed> $a
     *
     * @return list<string>
     */
    public static function strings(array $a, string $k): array
    {
        $v = $a[$k] ?? [];

        return is_array($v) ? array_values(array_map('strval', array_filter($v, 'is_scalar'))) : [];
    }

    /**
     * @param array<mixed> $a
     *
     * @return list<int>
     */
    public static function ints(array $a, string $k): array
    {
        $v = $a[$k] ?? [];

        return is_array($v) ? array_values(array_map('intval', array_filter($v, 'is_numeric'))) : [];
    }
}
