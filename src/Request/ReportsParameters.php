<?php

declare(strict_types=1);

namespace AbuseIpDb\Request;

use AbuseIpDb\Support\Assert;

/**
 * Query parameters for the `reports` endpoint.
 */
final class ReportsParameters
{
    private string $ipAddress;
    private ?int $maxAgeInDays;
    private int $page;
    private int $perPage;

    /**
     * @param string $ipAddress the IPv4 or IPv6 address to look up
     * @param int|null $maxAgeInDays only consider reports within this many days (1-365)
     * @param int $page the 1-based page number to retrieve (minimum 1)
     * @param int $perPage the number of reports per page (1-100)
     */
    public function __construct(string $ipAddress, ?int $maxAgeInDays = null, int $page = 1, int $perPage = 25)
    {
        Assert::ip($ipAddress);
        Assert::range($maxAgeInDays, 1, 365, 'maxAgeInDays');
        Assert::range($page, 1, null, 'page');
        Assert::range($perPage, 1, 100, 'perPage');
        $this->ipAddress = $ipAddress;
        $this->maxAgeInDays = $maxAgeInDays;
        $this->page = $page;
        $this->perPage = $perPage;
    }

    public function getIpAddress(): string
    {
        return $this->ipAddress;
    }

    public function getMaxAgeInDays(): ?int
    {
        return $this->maxAgeInDays;
    }

    public function getPage(): int
    {
        return $this->page;
    }

    public function getPerPage(): int
    {
        return $this->perPage;
    }

    /** @return array<string, scalar> */
    public function toQuery(): array
    {
        $q = ['ipAddress' => $this->ipAddress];
        if ($this->maxAgeInDays !== null) {
            $q['maxAgeInDays'] = $this->maxAgeInDays;
        }
        $q['page'] = $this->page;
        $q['perPage'] = $this->perPage;

        return $q;
    }
}
