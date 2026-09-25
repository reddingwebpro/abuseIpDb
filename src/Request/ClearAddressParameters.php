<?php

declare(strict_types=1);

namespace AbuseIpDb\Request;

use AbuseIpDb\Support\Assert;

/**
 * Query parameters for the `clear-address` endpoint.
 */
final class ClearAddressParameters
{
    private string $ipAddress;

    /**
     * @param string $ipAddress the IPv4 or IPv6 address whose reports should be cleared
     */
    public function __construct(string $ipAddress)
    {
        Assert::ip($ipAddress);
        $this->ipAddress = $ipAddress;
    }

    public function getIpAddress(): string
    {
        return $this->ipAddress;
    }

    /** @return array<string, string> */
    public function toQuery(): array
    {
        return ['ipAddress' => $this->ipAddress];
    }
}
