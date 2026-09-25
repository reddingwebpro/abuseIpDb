<?php

declare(strict_types=1);

namespace AbuseIpDb\Request;

use AbuseIpDb\Exception\InvalidArgumentException;

/**
 * File upload parameters for the `bulk-report` endpoint.
 */
final class BulkReportParameters
{
    public const FIELD = 'csv';
    public const MAX_BYTES = 2 * 1024 * 1024;

    private string $contents;
    private string $filename;

    /**
     * @param string $contents raw CSV contents, up to self::MAX_BYTES
     * @param string $filename the filename sent to the API as part of the multipart upload
     */
    public function __construct(string $contents, string $filename = 'report.csv')
    {
        if (trim($contents) === '') {
            throw new InvalidArgumentException('Bulk report CSV must not be empty.');
        }
        if (strlen($contents) > self::MAX_BYTES) {
            throw new InvalidArgumentException('Bulk report CSV must not exceed 2 MB.');
        }
        $this->contents = $contents;
        $this->filename = $filename;
    }

    /**
     * @param string $path path to a CSV file on disk, up to self::MAX_BYTES
     */
    public static function fromFile(string $path): self
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new InvalidArgumentException(sprintf('CSV file "%s" does not exist or is not readable.', $path));
        }
        if ((int) filesize($path) > self::MAX_BYTES) {
            throw new InvalidArgumentException('Bulk report CSV must not exceed 2 MB.');
        }
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new InvalidArgumentException(sprintf('Unable to read CSV file "%s".', $path));
        }

        return new self($contents, basename($path));
    }

    /** Treats the argument as a file path when it points to an existing file, else as CSV contents. */
    public static function fromPathOrContents(string $csv): self
    {
        return strpos($csv, "\n") === false && is_file($csv) ? self::fromFile($csv) : new self($csv);
    }

    /** The raw CSV contents to upload. */
    public function getContents(): string
    {
        return $this->contents;
    }

    /** The filename sent to the API as part of the multipart upload. */
    public function getFilename(): string
    {
        return $this->filename;
    }
}
