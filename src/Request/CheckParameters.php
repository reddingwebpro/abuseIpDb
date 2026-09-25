<?php

declare(strict_types=1);

namespace AbuseIpDb\Request;

use AbuseIpDb\Support\Assert;

/**
 * Query parameters for the `check` endpoint.
 */
final class CheckParameters
{
    private string $ipAddress;
    private ?int $maxAgeInDays;
    private bool $verbose;

    /**
     * @param string $ipAddress the IPv4 or IPv6 address to check
     * @param int|null $maxAgeInDays only consider reports within this many days (1-365)
     * @param bool $verbose whether to include the reporter country and the last 10 reports in the response
     */
    public function __construct(string $ipAddress, ?int $maxAgeInDays = null, bool $verbose = false)
    {
        Assert::ip($ipAddress);
        Assert::range($maxAgeInDays, 1, 365, 'maxAgeInDays');
        $this->ipAddress = $ipAddress;
        $this->maxAgeInDays = $maxAgeInDays;
        $this->verbose = $verbose;
    }

    public function getIpAddress(): string
    {
        return $this->ipAddress;
    }

    public function getMaxAgeInDays(): ?int
    {
        return $this->maxAgeInDays;
    }

    public function isVerbose(): bool
    {
        return $this->verbose;
    }

    /**
     * The API treats the mere presence of `verbose` as true, so it is omitted when false.
     *
     * @return array<string, scalar>
     */
    public function toQuery(): array
    {
        $q = ['ipAddress' => $this->ipAddress];
        if ($this->maxAgeInDays !== null) {
            $q['maxAgeInDays'] = $this->maxAgeInDays;
        }
        if ($this->verbose) {
            $q['verbose'] = true;
        }

        return $q;
    }
}
