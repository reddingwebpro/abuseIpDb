<?php

declare(strict_types=1);

namespace AbuseIpDb\Result;

use AbuseIpDb\Support\Arr;

/**
 * A single row of a `bulk-report` CSV upload that could not be saved.
 */
final class BulkReportError
{
    private string $error;
    private string $input;
    private int $rowNumber;

    public function __construct(string $error, string $input, int $rowNumber)
    {
        $this->error = $error;
        $this->input = $input;
        $this->rowNumber = $rowNumber;
    }

    /** @param array<mixed> $d */
    public static function fromArray(array $d): self
    {
        return new self(Arr::str($d, 'error') ?? '', Arr::str($d, 'input') ?? '', Arr::int($d, 'rowNumber') ?? 0);
    }

    /** Reason the row was rejected, e.g. "Duplicate IP" or "Invalid IP". */
    public function getError(): string
    {
        return $this->error;
    }

    /** The raw value from the CSV row that caused the error. */
    public function getInput(): string
    {
        return $this->input;
    }

    /** The 1-based row number in the uploaded CSV. */
    public function getRowNumber(): int
    {
        return $this->rowNumber;
    }
}
