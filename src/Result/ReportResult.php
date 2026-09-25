<?php

declare(strict_types=1);

namespace AbuseIpDb\Result;

use AbuseIpDb\Support\Arr;

/**
 * Outcome of submitting a report for an IP address (`report` endpoint).
 */
final class ReportResult
{
    private string $ipAddress;
    private int $abuseConfidenceScore;

    public function __construct(string $ipAddress, int $abuseConfidenceScore)
    {
        $this->ipAddress = $ipAddress;
        $this->abuseConfidenceScore = $abuseConfidenceScore;
    }

    /** @param array<mixed> $d */
    public static function fromArray(array $d): self
    {
        return new self(Arr::str($d, 'ipAddress') ?? '', Arr::int($d, 'abuseConfidenceScore') ?? 0);
    }

    public function getIpAddress(): string
    {
        return $this->ipAddress;
    }

    /** Likelihood of abuse after this report, from 0 (clean) to 100 (certainly malicious). */
    public function getAbuseConfidenceScore(): int
    {
        return $this->abuseConfidenceScore;
    }
}
