<?php

declare(strict_types=1);

namespace AbuseIpDb\Support;

use AbuseIpDb\Exception\InvalidArgumentException;

/**
 * Input validation helpers; throw before any HTTP call.
 *
 * @internal
 */
final class Assert
{
    public static function ip(string $ip, string $name = 'ipAddress'): void
    {
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            throw new InvalidArgumentException(sprintf('Invalid %s "%s".', $name, $ip));
        }
    }

    public static function range(?int $value, int $min, ?int $max, string $name): void
    {
        if ($value !== null && ($value < $min || ($max !== null && $value > $max))) {
            $bound = $max === null ? sprintf('>= %d', $min) : sprintf('between %d and %d', $min, $max);
            throw new InvalidArgumentException(sprintf('%s must be %s, %d given.', $name, $bound, $value));
        }
    }

    public static function cidr(string $network): void
    {
        $parts = explode('/', $network);
        if (count($parts) !== 2 || filter_var($parts[0], FILTER_VALIDATE_IP) === false || !ctype_digit($parts[1])) {
            throw new InvalidArgumentException(sprintf('Invalid CIDR network "%s".', $network));
        }
        $max = strpos($parts[0], ':') !== false ? 128 : 32;
        if ((int) $parts[1] > $max) {
            throw new InvalidArgumentException(sprintf('Invalid CIDR prefix in "%s".', $network));
        }
    }

    /**
     * @param list<string> $codes
     *
     * @return list<string>
     */
    public static function countries(array $codes, string $name): array
    {
        $out = [];
        foreach ($codes as $code) {
            $code = strtoupper(trim($code));
            if (!preg_match('/^[A-Z]{2}$/', $code)) {
                throw new InvalidArgumentException(sprintf('%s must contain ISO 3166 alpha-2 codes, "%s" given.', $name, $code));
            }
            $out[] = $code;
        }

        return $out;
    }
}
