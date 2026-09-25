<?php

declare(strict_types=1);

namespace AbuseIpDb\Result;

use AbuseIpDb\Support\Arr;

/**
 * Structured (JSON) blacklist. Plaintext mode is returned as a raw string by the client.
 *
 * @implements \IteratorAggregate<int, BlacklistEntry>
 */
final class BlacklistResult implements \Countable, \IteratorAggregate
{
    private ?\DateTimeImmutable $generatedAt;
    /** @var list<BlacklistEntry> */
    private array $entries;

    /** @param list<BlacklistEntry> $entries */
    public function __construct(?\DateTimeImmutable $generatedAt, array $entries)
    {
        $this->generatedAt = $generatedAt;
        $this->entries = $entries;
    }

    /** @param array<mixed> $json The full JSON document (`meta` + `data`). */
    public static function fromArray(array $json): self
    {
        $meta = isset($json['meta']) && is_array($json['meta']) ? $json['meta'] : [];

        return new self(Arr::date($meta, 'generatedAt'), array_map([BlacklistEntry::class, 'fromArray'], Arr::rows($json, 'data')));
    }

    public function getGeneratedAt(): ?\DateTimeImmutable
    {
        return $this->generatedAt;
    }

    /** @return list<BlacklistEntry> */
    public function getEntries(): array
    {
        return $this->entries;
    }

    /**
     * Convenience accessor returning only the IP addresses, in the same order as `getEntries()`.
     *
     * @return list<string>
     */
    public function getIpAddresses(): array
    {
        return array_map(static fn (BlacklistEntry $e): string => $e->getIpAddress(), $this->entries);
    }

    public function count(): int
    {
        return count($this->entries);
    }

    /** @return \ArrayIterator<int, BlacklistEntry> */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->entries);
    }
}
