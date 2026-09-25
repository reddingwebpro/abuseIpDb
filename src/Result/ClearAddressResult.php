<?php

declare(strict_types=1);

namespace AbuseIpDb\Result;

use AbuseIpDb\Support\Arr;

final class ClearAddressResult
{
    private string $ipAddress;
    private int $numReportsDeleted;

    public function __construct(string $ipAddress, int $numReportsDeleted)
    {
        $this->ipAddress = $ipAddress;
        $this->numReportsDeleted = $numReportsDeleted;
    }

    /** @param array<mixed> $d */
    public static function fromArray(array $d): self
    {
        return new self(
            Arr::str($d, 'ipAddress') ?? '',
            Arr::int($d, 'numReportsDeleted') ?? 0
        );
    }

    /** The IP address whose reports were cleared. */
    public function getIpAddress(): string
    {
        return $this->ipAddress;
    }

    public function getNumReportsDeleted(): int
    {
        return $this->numReportsDeleted;
    }
}
