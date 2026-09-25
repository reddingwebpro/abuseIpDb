<?php

declare(strict_types=1);

namespace AbuseIpDb\Exception;

/**
 * 429 Too Many Requests - the daily quota for the endpoint is exhausted.
 */
final class RateLimitExceededException extends ApiException
{
    /**
     * Seconds to wait before retrying, from the `Retry-After` header.
     */
    public function getRetryAfter(): ?int
    {
        return $this->getRateLimit()->getRetryAfter();
    }
}
