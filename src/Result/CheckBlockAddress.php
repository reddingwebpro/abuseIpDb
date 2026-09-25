<?php

declare(strict_types=1);

namespace AbuseIpDb\Result;

use AbuseIpDb\Support\Arr;

/**
 * Abuse reports and confidence score for a single address within a checked network.
 */
final class CheckBlockAddress
{
    private string $ipAddress;
    private int $numReports;
    private ?\DateTimeImmutable $mostRecentReport;
    private int $abuseConfidenceScore;
    private ?string $countryCode;

    public function __construct(string $ipAddress, int $numReports, ?\DateTimeImmutable $mostRecentReport, int $abuseConfidenceScore, ?string $countryCode)
    {
        $this->ipAddress = $ipAddress;
        $this->numReports = $numReports;
        $this->mostRecentReport = $mostRecentReport;
        $this->abuseConfidenceScore = $abuseConfidenceScore;
        $this->countryCode = $countryCode;
    }

    /** @param array<mixed> $d */
    public static function fromArray(array $d): self
    {
        return new self(
            Arr::str($d, 'ipAddress') ?? '',
            Arr::int($d, 'numReports') ?? 0,
            Arr::date($d, 'mostRecentReport'),
            Arr::int($d, 'abuseConfidenceScore') ?? 0,
            Arr::str($d, 'countryCode')
        );
    }

    public function getIpAddress(): string
    {
        return $this->ipAddress;
    }

    /** Total number of reports filed for this address. */
    public function getNumReports(): int
    {
        return $this->numReports;
    }

    public function getMostRecentReport(): ?\DateTimeImmutable
    {
        return $this->mostRecentReport;
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
}
