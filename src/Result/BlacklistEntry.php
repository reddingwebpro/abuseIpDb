<?php

declare(strict_types=1);

namespace AbuseIpDb\Result;

use AbuseIpDb\Support\Arr;

/**
 * A single entry of the blacklist (most-reported IP addresses).
 */
final class BlacklistEntry
{
    private string $ipAddress;
    private ?string $countryCode;
    private int $abuseConfidenceScore;
    private ?\DateTimeImmutable $lastReportedAt;

    public function __construct(string $ipAddress, ?string $countryCode, int $abuseConfidenceScore, ?\DateTimeImmutable $lastReportedAt)
    {
        $this->ipAddress = $ipAddress;
        $this->countryCode = $countryCode;
        $this->abuseConfidenceScore = $abuseConfidenceScore;
        $this->lastReportedAt = $lastReportedAt;
    }

    /** @param array<mixed> $d */
    public static function fromArray(array $d): self
    {
        return new self(
            Arr::str($d, 'ipAddress') ?? '',
            Arr::str($d, 'countryCode'),
            Arr::int($d, 'abuseConfidenceScore') ?? 0,
            Arr::date($d, 'lastReportedAt')
        );
    }

    public function getIpAddress(): string
    {
        return $this->ipAddress;
    }

    public function getCountryCode(): ?string
    {
        return $this->countryCode;
    }

    /** Likelihood of abuse, from 0 (clean) to 100 (certainly malicious). */
    public function getAbuseConfidenceScore(): int
    {
        return $this->abuseConfidenceScore;
    }

    public function getLastReportedAt(): ?\DateTimeImmutable
    {
        return $this->lastReportedAt;
    }
}
