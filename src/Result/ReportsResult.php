<?php

declare(strict_types=1);

namespace AbuseIpDb\Result;

use AbuseIpDb\Support\Arr;

/**
 * A paginated page of reports filed against an IP address (`reports` endpoint).
 */
final class ReportsResult
{
    private int $total;
    private int $page;
    private int $count;
    private int $perPage;
    private int $lastPage;
    private ?string $nextPageUrl;
    private ?string $previousPageUrl;
    /** @var list<Report> */
    private array $results;

    private function __construct()
    {
    }

    /** @param array<mixed> $d The `data` member of the response. */
    public static function fromArray(array $d): self
    {
        $r = new self();
        $r->results = array_map([Report::class, 'fromArray'], Arr::rows($d, 'results'));
        $r->total = Arr::int($d, 'total') ?? 0;
        $r->page = Arr::int($d, 'page') ?? 1;
        $r->count = Arr::int($d, 'count') ?? count($r->results);
        $r->perPage = Arr::int($d, 'perPage') ?? 25;
        $r->lastPage = Arr::int($d, 'lastPage') ?? $r->page;
        $r->nextPageUrl = Arr::str($d, 'nextPageUrl');
        $r->previousPageUrl = Arr::str($d, 'previousPageUrl');

        return $r;
    }

    /** Total number of reports across all pages. */
    public function getTotal(): int
    {
        return $this->total;
    }

    /** The 1-based page number of this result. */
    public function getPage(): int
    {
        return $this->page;
    }

    /** Number of reports on this page. */
    public function getCount(): int
    {
        return $this->count;
    }

    /** Number of reports requested per page. */
    public function getPerPage(): int
    {
        return $this->perPage;
    }

    /** The last available page number. */
    public function getLastPage(): int
    {
        return $this->lastPage;
    }

    public function hasNextPage(): bool
    {
        return $this->page < $this->lastPage;
    }

    public function getNextPageUrl(): ?string
    {
        return $this->nextPageUrl;
    }

    public function getPreviousPageUrl(): ?string
    {
        return $this->previousPageUrl;
    }

    /** @return list<Report> */
    public function getResults(): array
    {
        return $this->results;
    }
}
