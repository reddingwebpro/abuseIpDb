<?php

declare(strict_types=1);

namespace AbuseIpDb\Result;

use AbuseIpDb\Support\Arr;

/**
 * Outcome of a `bulk-report` CSV upload.
 */
final class BulkReportResult
{
    private int $savedReports;
    /** @var list<BulkReportError> */
    private array $invalidReports;

    /** @param list<BulkReportError> $invalidReports */
    public function __construct(int $savedReports, array $invalidReports)
    {
        $this->savedReports = $savedReports;
        $this->invalidReports = $invalidReports;
    }

    /** @param array<mixed> $d */
    public static function fromArray(array $d): self
    {
        return new self(Arr::int($d, 'savedReports') ?? 0, array_map([BulkReportError::class, 'fromArray'], Arr::rows($d, 'invalidReports')));
    }

    /** Number of rows that were successfully saved as reports. */
    public function getSavedReports(): int
    {
        return $this->savedReports;
    }

    /** @return list<BulkReportError> */
    public function getInvalidReports(): array
    {
        return $this->invalidReports;
    }

    public function hasInvalidReports(): bool
    {
        return $this->invalidReports !== [];
    }
}
