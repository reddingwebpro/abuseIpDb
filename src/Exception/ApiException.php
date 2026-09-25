<?php

declare(strict_types=1);

namespace AbuseIpDb\Exception;

use AbuseIpDb\Http\RateLimit;

/**
 * Raised for any non-2xx API response; subclasses narrow down specific status codes.
 */
class ApiException extends \RuntimeException implements AbuseIpDbException
{
    private int $statusCode;

    /** @var list<array<string, mixed>> */
    private array $errors;

    private RateLimit $rateLimit;

    /**
     * @param list<array<string, mixed>> $errors
     */
    public function __construct(string $message, int $statusCode, array $errors, RateLimit $rateLimit, ?\Throwable $previous = null)
    {
        parent::__construct($message, $statusCode, $previous);
        $this->statusCode = $statusCode;
        $this->errors = $errors;
        $this->rateLimit = $rateLimit;
    }

    /** HTTP status code of the failed response. */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Raw `errors` entries returned by the API.
     *
     * @return list<array<string, mixed>>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /** Rate-limit metadata attached to the failed response. */
    public function getRateLimit(): RateLimit
    {
        return $this->rateLimit;
    }
}
