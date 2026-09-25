<?php

declare(strict_types=1);

namespace AbuseIpDb\Request;

use AbuseIpDb\Exception\InvalidArgumentException;
use AbuseIpDb\Support\Assert;

/**
 * Query parameters for the `blacklist` endpoint.
 */
final class BlacklistParameters
{
    private ?int $confidenceMinimum;
    private ?int $limit;
    /** @var list<string> */
    private array $onlyCountries;
    /** @var list<string> */
    private array $exceptCountries;
    private ?int $ipVersion;
    private bool $plaintext;

    /**
     * @param int|null $confidenceMinimum only include addresses with at least this confidence score (25-100); requires a paid subscription
     * @param int|null $limit maximum number of addresses to return; requires a paid subscription for values above the free tier default
     * @param list<string> $onlyCountries   ISO 3166 alpha-2 codes; mutually exclusive with $exceptCountries
     * @param list<string> $exceptCountries ISO 3166 alpha-2 codes; mutually exclusive with $onlyCountries
     * @param int|null $ipVersion restrict results to 4 (IPv4) or 6 (IPv6) addresses
     * @param bool $plaintext whether to request a raw newline-separated list instead of JSON
     */
    public function __construct(
        ?int $confidenceMinimum = null,
        ?int $limit = null,
        array $onlyCountries = [],
        array $exceptCountries = [],
        ?int $ipVersion = null,
        bool $plaintext = false
    ) {
        Assert::range($confidenceMinimum, 25, 100, 'confidenceMinimum');
        Assert::range($limit, 1, null, 'limit');
        if ($ipVersion !== null && $ipVersion !== 4 && $ipVersion !== 6) {
            throw new InvalidArgumentException(sprintf('ipVersion must be 4 or 6, %d given.', $ipVersion));
        }
        $this->onlyCountries = Assert::countries($onlyCountries, 'onlyCountries');
        $this->exceptCountries = Assert::countries($exceptCountries, 'exceptCountries');
        if ($this->onlyCountries !== [] && $this->exceptCountries !== []) {
            throw new InvalidArgumentException('onlyCountries and exceptCountries are mutually exclusive.');
        }
        $this->confidenceMinimum = $confidenceMinimum;
        $this->limit = $limit;
        $this->ipVersion = $ipVersion;
        $this->plaintext = $plaintext;
    }

    /** Minimum confidence score filter, from 25 to 100. */
    public function getConfidenceMinimum(): ?int
    {
        return $this->confidenceMinimum;
    }

    public function getLimit(): ?int
    {
        return $this->limit;
    }

    /** @return list<string> */
    public function getOnlyCountries(): array
    {
        return $this->onlyCountries;
    }

    /** @return list<string> */
    public function getExceptCountries(): array
    {
        return $this->exceptCountries;
    }

    public function getIpVersion(): ?int
    {
        return $this->ipVersion;
    }

    public function isPlaintext(): bool
    {
        return $this->plaintext;
    }

    /**
     * `plaintext` is presence-based on the API side, so it is omitted when false.
     *
     * @return array<string, scalar|list<string>>
     */
    public function toQuery(): array
    {
        $q = [];
        if ($this->confidenceMinimum !== null) {
            $q['confidenceMinimum'] = $this->confidenceMinimum;
        }
        if ($this->limit !== null) {
            $q['limit'] = $this->limit;
        }
        if ($this->onlyCountries !== []) {
            $q['onlyCountries'] = $this->onlyCountries;
        }
        if ($this->exceptCountries !== []) {
            $q['exceptCountries'] = $this->exceptCountries;
        }
        if ($this->ipVersion !== null) {
            $q['ipVersion'] = $this->ipVersion;
        }
        if ($this->plaintext) {
            $q['plaintext'] = true;
        }

        return $q;
    }
}
