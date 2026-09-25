<?php

declare(strict_types=1);

namespace AbuseIpDb;

use AbuseIpDb\Exception\AuthenticationException;
use AbuseIpDb\Exception\NetworkException;
use AbuseIpDb\Exception\PaymentRequiredException;
use AbuseIpDb\Exception\RateLimitExceededException;
use AbuseIpDb\Exception\ServerException;
use AbuseIpDb\Exception\UnexpectedResponseException;
use AbuseIpDb\Exception\ValidationException;
use AbuseIpDb\Http\HttpTransport;
use AbuseIpDb\Http\RateLimit;
use AbuseIpDb\Request\BlacklistParameters;
use AbuseIpDb\Request\BulkReportParameters;
use AbuseIpDb\Request\CheckBlockParameters;
use AbuseIpDb\Request\CheckParameters;
use AbuseIpDb\Request\ClearAddressParameters;
use AbuseIpDb\Request\ReportParameters;
use AbuseIpDb\Request\ReportsParameters;
use AbuseIpDb\Result\BlacklistResult;
use AbuseIpDb\Result\BulkReportResult;
use AbuseIpDb\Result\CheckBlockResult;
use AbuseIpDb\Result\CheckResult;
use AbuseIpDb\Result\ClearAddressResult;
use AbuseIpDb\Result\ReportResult;
use AbuseIpDb\Result\ReportsResult;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Public entry point for the AbuseIPDB APIv2.
 */
final class AbuseIpDbClient
{
    private HttpTransport $transport;
    private ?RateLimit $lastRateLimit = null;
    private ?int $defaultMaxAgeInDays;

    /**
     * @param string $apiKey your AbuseIPDB API key, sent on every request via the `Key` header
     * @param ClientInterface|null $httpClient a PSR-18 HTTP client, auto-discovered via `Http\Discovery` when null
     * @param RequestFactoryInterface|null $requestFactory a PSR-17 request factory, auto-discovered via `Http\Discovery` when null
     * @param StreamFactoryInterface|null $streamFactory a PSR-17 stream factory, auto-discovered via `Http\Discovery` when null
     * @param array{base_uri?: string, logger?: LoggerInterface, default_max_age_in_days?: int|null} $options `base_uri` overrides the API base URL, `logger` receives request/error logs, `default_max_age_in_days` is used by `check`/`reports`/`checkBlock` when no explicit value is given
     */
    public function __construct(
        string $apiKey,
        ?ClientInterface $httpClient = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
        array $options = []
    ) {
        $this->transport = new HttpTransport(
            $apiKey,
            $httpClient,
            $requestFactory,
            $streamFactory,
            $options['base_uri'] ?? HttpTransport::DEFAULT_BASE_URI,
            $options['logger'] ?? null
        );
        // Validated through the parameter objects on first use.
        $this->defaultMaxAgeInDays = $options['default_max_age_in_days'] ?? null;
    }

    /**
     * Checks an IP address's abuse reports and confidence score.
     *
     * @param string $ip the IPv4 or IPv6 address to check
     * @param int|null $maxAgeInDays only consider reports within this many days (1-365); falls back to the client's `default_max_age_in_days` option, then the API default
     * @param bool $verbose whether to include the reporter country and the last 10 reports in the response
     *
     * @throws ValidationException if the IP address or parameters are invalid
     * @throws AuthenticationException if the API key is missing or invalid
     * @throws PaymentRequiredException if the account's subscription does not allow this call
     * @throws RateLimitExceededException if the API rate limit has been exceeded
     * @throws ServerException if the AbuseIPDB API returns a server-side error
     * @throws NetworkException if the underlying HTTP request fails
     * @throws UnexpectedResponseException if the API response cannot be parsed as JSON
     */
    public function check(string $ip, ?int $maxAgeInDays = null, bool $verbose = false): CheckResult
    {
        $params = new CheckParameters($ip, $maxAgeInDays ?? $this->defaultMaxAgeInDays, $verbose);

        return CheckResult::fromArray($this->track($this->transport->get('check', $params->toQuery()))->getData());
    }

    /**
     * Retrieves the paginated list of reports filed against an IP address.
     *
     * @param string $ip the IPv4 or IPv6 address to look up
     * @param int|null $maxAgeInDays only consider reports within this many days (1-365); falls back to the client's `default_max_age_in_days` option, then the API default
     * @param int $page the 1-based page number to retrieve
     * @param int $perPage the number of reports per page
     *
     * @throws ValidationException if the IP address or parameters are invalid
     * @throws AuthenticationException if the API key is missing or invalid
     * @throws PaymentRequiredException if the account's subscription does not allow this call
     * @throws RateLimitExceededException if the API rate limit has been exceeded
     * @throws ServerException if the AbuseIPDB API returns a server-side error
     * @throws NetworkException if the underlying HTTP request fails
     * @throws UnexpectedResponseException if the API response cannot be parsed as JSON
     */
    public function reports(string $ip, ?int $maxAgeInDays = null, int $page = 1, int $perPage = 25): ReportsResult
    {
        $params = new ReportsParameters($ip, $maxAgeInDays ?? $this->defaultMaxAgeInDays, $page, $perPage);

        return ReportsResult::fromArray($this->track($this->transport->get('reports', $params->toQuery()))->getData());
    }

    /**
     * Fetches the blacklist of the most-reported IP addresses.
     *
     * Returns the raw newline-separated list when $params->isPlaintext() is true.
     *
     * @param BlacklistParameters|null $params filtering/pagination options; defaults to a plain `BlacklistParameters` instance when null
     *
     * @return BlacklistResult|string
     *
     * @throws ValidationException if the request parameters are invalid
     * @throws AuthenticationException if the API key is missing or invalid
     * @throws PaymentRequiredException if the account's subscription does not allow this call (e.g. confidence/limit filters require a paid plan)
     * @throws RateLimitExceededException if the API rate limit has been exceeded
     * @throws ServerException if the AbuseIPDB API returns a server-side error
     * @throws NetworkException if the underlying HTTP request fails
     * @throws UnexpectedResponseException if the JSON response cannot be parsed
     */
    public function blacklist(?BlacklistParameters $params = null)
    {
        $params = $params ?? new BlacklistParameters();
        $plaintext = $params->isPlaintext();
        $response = $this->track($this->transport->get('blacklist', $params->toQuery(), !$plaintext));

        return $plaintext ? $response->getBody() : BlacklistResult::fromArray($response->getJson());
    }

    /**
     * Submits a report for an IP address.
     *
     * @param string $ip the IPv4 or IPv6 address being reported
     * @param list<int> $categories one or more category IDs, see ReportCategory constants
     * @param string|null $comment optional free-text details about the abuse
     * @param \DateTimeInterface|null $timestamp optional time the abuse occurred; defaults to now when null
     *
     * @throws ValidationException if the IP address, categories or other parameters are invalid
     * @throws AuthenticationException if the API key is missing or invalid
     * @throws PaymentRequiredException if the account's subscription does not allow this call
     * @throws RateLimitExceededException if the API rate limit has been exceeded
     * @throws ServerException if the AbuseIPDB API returns a server-side error
     * @throws NetworkException if the underlying HTTP request fails
     * @throws UnexpectedResponseException if the API response cannot be parsed as JSON
     */
    public function report(string $ip, array $categories, ?string $comment = null, ?\DateTimeInterface $timestamp = null): ReportResult
    {
        $params = new ReportParameters($ip, $categories, $comment, $timestamp);

        return ReportResult::fromArray($this->track($this->transport->postForm('report', $params->toBody()))->getData());
    }

    /**
     * Checks every address in a CIDR network for abuse reports and confidence scores.
     *
     * @param string $network the network in CIDR notation (e.g. `127.0.0.1/24`)
     * @param int|null $maxAgeInDays only consider reports within this many days (1-365); falls back to the client's `default_max_age_in_days` option, then the API default
     *
     * @throws ValidationException if the network or parameters are invalid
     * @throws AuthenticationException if the API key is missing or invalid
     * @throws PaymentRequiredException if the account's subscription does not allow this call
     * @throws RateLimitExceededException if the API rate limit has been exceeded
     * @throws ServerException if the AbuseIPDB API returns a server-side error
     * @throws NetworkException if the underlying HTTP request fails
     * @throws UnexpectedResponseException if the API response cannot be parsed as JSON
     */
    public function checkBlock(string $network, ?int $maxAgeInDays = null): CheckBlockResult
    {
        $params = new CheckBlockParameters($network, $maxAgeInDays ?? $this->defaultMaxAgeInDays);

        return CheckBlockResult::fromArray($this->track($this->transport->get('check-block', $params->toQuery()))->getData());
    }

    /**
     * Submits multiple reports at once from a CSV file.
     *
     * @param string|BulkReportParameters $csv file path, raw CSV contents or a prepared parameter object
     *
     * @throws ValidationException if the CSV contents or parameters are invalid
     * @throws AuthenticationException if the API key is missing or invalid
     * @throws PaymentRequiredException if the account's subscription does not allow this call
     * @throws RateLimitExceededException if the API rate limit has been exceeded
     * @throws ServerException if the AbuseIPDB API returns a server-side error
     * @throws NetworkException if the underlying HTTP request fails
     * @throws UnexpectedResponseException if the API response cannot be parsed as JSON
     */
    public function bulkReport($csv): BulkReportResult
    {
        $params = $csv instanceof BulkReportParameters ? $csv : BulkReportParameters::fromPathOrContents($csv);
        $response = $this->transport->postMultipart('bulk-report', BulkReportParameters::FIELD, $params->getContents(), $params->getFilename());

        return BulkReportResult::fromArray($this->track($response)->getData());
    }

    /**
     * Deletes all reports submitted by this account for an IP address.
     *
     * @param string $ip the IPv4 or IPv6 address whose reports should be cleared
     *
     * @throws ValidationException if the IP address is invalid
     * @throws AuthenticationException if the API key is missing or invalid
     * @throws PaymentRequiredException if the account's subscription does not allow this call
     * @throws RateLimitExceededException if the API rate limit has been exceeded
     * @throws ServerException if the AbuseIPDB API returns a server-side error
     * @throws NetworkException if the underlying HTTP request fails
     * @throws UnexpectedResponseException if the API response cannot be parsed as JSON
     */
    public function clearAddress(string $ip): ClearAddressResult
    {
        $params = new ClearAddressParameters($ip);

        return ClearAddressResult::fromArray($this->track($this->transport->delete('clear-address', $params->toQuery()))->getData());
    }

    /**
     * Rate-limit metadata of the most recent successful call, or null before any call.
     */
    public function getLastRateLimit(): ?RateLimit
    {
        return $this->lastRateLimit;
    }

    private function track(Http\ApiResponse $response): Http\ApiResponse
    {
        $this->lastRateLimit = $response->getRateLimit();

        return $response;
    }
}
