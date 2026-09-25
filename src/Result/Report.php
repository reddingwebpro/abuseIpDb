<?php

declare(strict_types=1);

namespace AbuseIpDb\Result;

use AbuseIpDb\Support\Arr;

/**
 * A single abuse report (verbose check and reports endpoints).
 */
final class Report
{
    private ?\DateTimeImmutable $reportedAt;
    private ?string $comment;
    /** @var list<int> */
    private array $categories;
    private ?int $reporterId;
    private ?string $reporterCountryCode;
    private ?string $reporterCountryName;

    /** @param list<int> $categories */
    public function __construct(?\DateTimeImmutable $reportedAt, ?string $comment, array $categories, ?int $reporterId, ?string $reporterCountryCode, ?string $reporterCountryName)
    {
        $this->reportedAt = $reportedAt;
        $this->comment = $comment;
        $this->categories = $categories;
        $this->reporterId = $reporterId;
        $this->reporterCountryCode = $reporterCountryCode;
        $this->reporterCountryName = $reporterCountryName;
    }

    /** @param array<mixed> $d */
    public static function fromArray(array $d): self
    {
        return new self(
            Arr::date($d, 'reportedAt'),
            Arr::str($d, 'comment'),
            Arr::ints($d, 'categories'),
            Arr::int($d, 'reporterId'),
            Arr::str($d, 'reporterCountryCode'),
            Arr::str($d, 'reporterCountryName')
        );
    }

    public function getReportedAt(): ?\DateTimeImmutable
    {
        return $this->reportedAt;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    /** @return list<int> */
    public function getCategories(): array
    {
        return $this->categories;
    }

    /** Only present in verbose mode. */
    public function getReporterId(): ?int
    {
        return $this->reporterId;
    }

    /** Only present in verbose mode. */
    public function getReporterCountryCode(): ?string
    {
        return $this->reporterCountryCode;
    }

    /** Only present in verbose mode. */
    public function getReporterCountryName(): ?string
    {
        return $this->reporterCountryName;
    }
}
