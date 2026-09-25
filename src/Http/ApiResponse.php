<?php

declare(strict_types=1);

namespace AbuseIpDb\Http;

/**
 * Decoded successful API response: JSON payload (or raw text) plus rate-limit metadata.
 */
final class ApiResponse
{
    private int $statusCode;

    /** @var array<string, mixed> */
    private array $json;

    private string $body;

    private RateLimit $rateLimit;

    /**
     * @param array<string, mixed> $json
     */
    public function __construct(int $statusCode, array $json, string $body, RateLimit $rateLimit)
    {
        $this->statusCode = $statusCode;
        $this->json = $json;
        $this->body = $body;
        $this->rateLimit = $rateLimit;
    }

    /** HTTP status code of the response. */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Full decoded JSON document (empty for plaintext responses).
     *
     * @return array<string, mixed>
     */
    public function getJson(): array
    {
        return $this->json;
    }

    /**
     * The `data` member of the JSON document.
     *
     * @return array<mixed>
     */
    public function getData(): array
    {
        $data = $this->json['data'] ?? [];

        return is_array($data) ? $data : [];
    }

    /** Raw response body, as returned by the server. */
    public function getBody(): string
    {
        return $this->body;
    }

    /** Rate-limit metadata attached to this response. */
    public function getRateLimit(): RateLimit
    {
        return $this->rateLimit;
    }
}
