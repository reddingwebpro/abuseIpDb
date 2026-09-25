<?php

declare(strict_types=1);

namespace AbuseIpDb\Http;

use Psr\Http\Message\ResponseInterface;

/**
 * Throttling metadata from `X-RateLimit-*` / `Retry-After` headers; fields are null when absent.
 */
final class RateLimit
{
    private ?int $limit;
    private ?int $remaining;
    private ?\DateTimeImmutable $resetAt;
    private ?int $retryAfter;

    public function __construct(?int $limit = null, ?int $remaining = null, ?\DateTimeImmutable $resetAt = null, ?int $retryAfter = null)
    {
        $this->limit = $limit;
        $this->remaining = $remaining;
        $this->resetAt = $resetAt;
        $this->retryAfter = $retryAfter;
    }

    public static function fromResponse(ResponseInterface $response): self
    {
        $reset = self::intHeader($response, 'X-RateLimit-Reset');

        return new self(
            self::intHeader($response, 'X-RateLimit-Limit'),
            self::intHeader($response, 'X-RateLimit-Remaining'),
            null === $reset ? null : (new \DateTimeImmutable('@' . $reset)),
            self::intHeader($response, 'Retry-After')
        );
    }

    /** Maximum number of requests allowed in the current window, or null when the header is absent. */
    public function getLimit(): ?int
    {
        return $this->limit;
    }

    /** Number of requests remaining in the current window, or null when the header is absent. */
    public function getRemaining(): ?int
    {
        return $this->remaining;
    }

    /** When the current rate-limit window resets, or null when the header is absent. */
    public function getResetAt(): ?\DateTimeImmutable
    {
        return $this->resetAt;
    }

    /** Seconds to wait before retrying, from the `Retry-After` header (typically present after a 429), or null when absent. */
    public function getRetryAfter(): ?int
    {
        return $this->retryAfter;
    }

    private static function intHeader(ResponseInterface $response, string $name): ?int
    {
        $value = trim($response->getHeaderLine($name));

        return '' !== $value && ctype_digit($value) ? (int) $value : null;
    }
}
