<?php

declare(strict_types=1);

namespace AbuseIpDb\Result;

use AbuseIpDb\Support\Arr;

/**
 * Abuse reports and confidence scores for every address in a CIDR network (`check-block` endpoint).
 */
final class CheckBlockResult
{
    private string $networkAddress;
    private string $netmask;
    private string $minAddress;
    private string $maxAddress;
    private int $numPossibleHosts;
    private ?string $addressSpaceDesc;
    /** @var list<CheckBlockAddress> */
    private array $reportedAddresses;

    private function __construct()
    {
    }

    /** @param array<mixed> $d The `data` member of the response. */
    public static function fromArray(array $d): self
    {
        $r = new self();
        $r->networkAddress = Arr::str($d, 'networkAddress') ?? '';
        $r->netmask = Arr::str($d, 'netmask') ?? '';
        $r->minAddress = Arr::str($d, 'minAddress') ?? '';
        $r->maxAddress = Arr::str($d, 'maxAddress') ?? '';
        $r->numPossibleHosts = Arr::int($d, 'numPossibleHosts') ?? 0;
        $r->addressSpaceDesc = Arr::str($d, 'addressSpaceDesc');
        $r->reportedAddresses = array_map([CheckBlockAddress::class, 'fromArray'], Arr::rows($d, 'reportedAddress'));

        return $r;
    }

    public function getNetworkAddress(): string
    {
        return $this->networkAddress;
    }

    public function getNetmask(): string
    {
        return $this->netmask;
    }

    public function getMinAddress(): string
    {
        return $this->minAddress;
    }

    public function getMaxAddress(): string
    {
        return $this->maxAddress;
    }

    /** The total number of addresses in the network. */
    public function getNumPossibleHosts(): int
    {
        return $this->numPossibleHosts;
    }

    /** Human-readable description of the address space, e.g. "Loopback". */
    public function getAddressSpaceDesc(): ?string
    {
        return $this->addressSpaceDesc;
    }

    /** @return list<CheckBlockAddress> only addresses within the network that have been reported */
    public function getReportedAddresses(): array
    {
        return $this->reportedAddresses;
    }
}
