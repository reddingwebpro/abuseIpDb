<?php

declare(strict_types=1);

namespace AbuseIpDb\Result;

use AbuseIpDb\Support\Arr;

/**
 * Abuse reports and confidence score for a single IP address (`check` endpoint).
 */
final class CheckResult
{
    private string $ipAddress;
    private ?bool $isPublic;
    private ?int $ipVersion;
    private ?bool $isWhitelisted;
    private int $abuseConfidenceScore;
    private ?string $countryCode;
    private ?string $countryName;
    private ?string $usageType;
    private ?string $isp;
    private ?string $domain;
    /** @var list<string> */
    private array $hostnames;
    private ?bool $isTor;
    private int $totalReports;
    private int $numDistinctUsers;
    private ?\DateTimeImmutable $lastReportedAt;
    /** @var list<Report> */
    private array $reports;

    private function __construct()
    {
    }

    /** @param array<mixed> $d The `data` member of the response. */
    public static function fromArray(array $d): self
    {
        $r = new self();
        $r->ipAddress = Arr::str($d, 'ipAddress') ?? '';
        $r->isPublic = Arr::bool($d, 'isPublic');
        $r->ipVersion = Arr::int($d, 'ipVersion');
        $r->isWhitelisted = Arr::bool($d, 'isWhitelisted');
        $r->abuseConfidenceScore = Arr::int($d, 'abuseConfidenceScore') ?? 0;
        $r->countryCode = Arr::str($d, 'countryCode');
        $r->countryName = Arr::str($d, 'countryName');
        $r->usageType = Arr::str($d, 'usageType');
        $r->isp = Arr::str($d, 'isp');
        $r->domain = Arr::str($d, 'domain');
        $r->hostnames = Arr::strings($d, 'hostnames');
        $r->isTor = Arr::bool($d, 'isTor');
        $r->totalReports = Arr::int($d, 'totalReports') ?? 0;
        $r->numDistinctUsers = Arr::int($d, 'numDistinctUsers') ?? 0;
        $r->lastReportedAt = Arr::date($d, 'lastReportedAt');
        $r->reports = array_map([Report::class, 'fromArray'], Arr::rows($d, 'reports'));

        return $r;
    }

    public function getIpAddress(): string
    {
        return $this->ipAddress;
    }

    /** Whether the address is publicly routable. */
    public function isPublic(): ?bool
    {
        return $this->isPublic;
    }

    public function getIpVersion(): ?int
    {
        return $this->ipVersion;
    }

    /** Whether the address is on AbuseIPDB's whitelist. */
    public function isWhitelisted(): ?bool
    {
        return $this->isWhitelisted;
    }

    /** Likelihood of abuse, from 0 (clean) to 100 (certainly malicious). */
    public function getAbuseConfidenceScore(): int
    {
        return $this->abuseConfidenceScore;
    }

    public function getCountryCode(): ?string
    {
        return $this->countryCode;
    }

    /** Only present in verbose mode. */
    public function getCountryName(): ?string
    {
        return $this->countryName;
    }

    public function getUsageType(): ?string
    {
        return $this->usageType;
    }

    public function getIsp(): ?string
    {
        return $this->isp;
    }

    public function getDomain(): ?string
    {
        return $this->domain;
    }

    /** @return list<string> */
    public function getHostnames(): array
    {
        return $this->hostnames;
    }

    /** Whether the address is a known Tor exit node. */
    public function isTor(): ?bool
    {
        return $this->isTor;
    }

    /** Total number of reports ever filed for this address. */
    public function getTotalReports(): int
    {
        return $this->totalReports;
    }

    /** Number of distinct reporters who have filed a report for this address. */
    public function getNumDistinctUsers(): int
    {
        return $this->numDistinctUsers;
    }

    /** Timestamp of the most recent report, or null if never reported. */
    public function getLastReportedAt(): ?\DateTimeImmutable
    {
        return $this->lastReportedAt;
    }

    /** @return list<Report> Only populated in verbose mode. */
    public function getReports(): array
    {
        return $this->reports;
    }
}
