<?php

declare(strict_types=1);

namespace AbuseIpDb\Request;

use AbuseIpDb\Support\Assert;

/**
 * Query parameters for the `check-block` endpoint.
 */
final class CheckBlockParameters
{
    private string $network;
    private ?int $maxAgeInDays;

    /**
     * @param string $network the network in CIDR notation (e.g. `127.0.0.1/24`)
     * @param int|null $maxAgeInDays only consider reports within this many days (1-365)
     */
    public function __construct(string $network, ?int $maxAgeInDays = null)
    {
        Assert::cidr($network);
        Assert::range($maxAgeInDays, 1, 365, 'maxAgeInDays');
        $this->network = $network;
        $this->maxAgeInDays = $maxAgeInDays;
    }

    public function getNetwork(): string
    {
        return $this->network;
    }

    public function getMaxAgeInDays(): ?int
    {
        return $this->maxAgeInDays;
    }

    /** @return array<string, scalar> */
    public function toQuery(): array
    {
        $q = ['network' => $this->network];
        if ($this->maxAgeInDays !== null) {
            $q['maxAgeInDays'] = $this->maxAgeInDays;
        }

        return $q;
    }
}
