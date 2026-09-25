<?php

declare(strict_types=1);

namespace AbuseIpDb\Http;

use AbuseIpDb\Exception\ApiException;
use AbuseIpDb\Exception\AuthenticationException;
use AbuseIpDb\Exception\InvalidArgumentException;
use AbuseIpDb\Exception\NetworkException;
use AbuseIpDb\Exception\PaymentRequiredException;
use AbuseIpDb\Exception\RateLimitExceededException;
use AbuseIpDb\Exception\ServerException;
use AbuseIpDb\Exception\UnexpectedResponseException;
use AbuseIpDb\Exception\ValidationException;
use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * @internal Low-level pipeline: builds PSR-7 requests, authenticates, sends and maps responses/errors.
 */
final class HttpTransport
{
    public const DEFAULT_BASE_URI = 'https://api.abuseipdb.com/api/v2/';

    private string $apiKey;
    private string $baseUri;
    private ClientInterface $client;
    private RequestFactoryInterface $requestFactory;
    private StreamFactoryInterface $streamFactory;
    private LoggerInterface $logger;

    public function __construct(
        string $apiKey,
        ?ClientInterface $client = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
        string $baseUri = self::DEFAULT_BASE_URI,
        ?LoggerInterface $logger = null
    ) {
        if ('' === trim($apiKey)) {
            throw new InvalidArgumentException('The AbuseIPDB API key must not be empty.');
        }
        $this->apiKey = $apiKey;
        $this->baseUri = rtrim($baseUri, '/') . '/';
        $this->client = $client ?? Psr18ClientDiscovery::find();
        $this->requestFactory = $requestFactory ?? Psr17FactoryDiscovery::findRequestFactory();
        $this->streamFactory = $streamFactory ?? Psr17FactoryDiscovery::findStreamFactory();
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * @param array<string, scalar|list<scalar>|null> $query
     */
    public function get(string $path, array $query = [], bool $expectJson = true): ApiResponse
    {
        return $this->send($this->createRequest('GET', $path, $query), $expectJson);
    }

    /**
     * @param array<string, scalar|list<scalar>|null> $query
     */
    public function delete(string $path, array $query = []): ApiResponse
    {
        return $this->send($this->createRequest('DELETE', $path, $query));
    }

    /**
     * @param array<string, scalar|list<scalar>|null> $form
     */
    public function postForm(string $path, array $form): ApiResponse
    {
        $request = $this->createRequest('POST', $path)
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withBody($this->streamFactory->createStream(self::buildQuery($form)));

        return $this->send($request);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function postJson(string $path, array $payload): ApiResponse
    {
        $request = $this->createRequest('POST', $path)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->streamFactory->createStream((string) json_encode($payload, JSON_THROW_ON_ERROR)));

        return $this->send($request);
    }

    /**
     * Uploads a single file as multipart/form-data.
     */
    public function postMultipart(string $path, string $field, string $contents, string $filename, string $contentType = 'text/csv'): ApiResponse
    {
        $boundary = bin2hex(random_bytes(16));
        $body = sprintf(
            "--%s\r\nContent-Disposition: form-data; name=\"%s\"; filename=\"%s\"\r\nContent-Type: %s\r\n\r\n%s\r\n--%s--\r\n",
            $boundary,
            addcslashes($field, '"\\'),
            addcslashes($filename, '"\\'),
            $contentType,
            $contents,
            $boundary
        );
        $request = $this->createRequest('POST', $path)
            ->withHeader('Content-Type', 'multipart/form-data; boundary=' . $boundary)
            ->withBody($this->streamFactory->createStream($body));

        return $this->send($request);
    }

    /**
     * @param array<string, scalar|list<scalar>|null> $query
     */
    private function createRequest(string $method, string $path, array $query = []): RequestInterface
    {
        $uri = $this->baseUri . ltrim($path, '/');
        $qs = self::buildQuery($query);
        if ('' !== $qs) {
            $uri .= '?' . $qs;
        }

        return $this->requestFactory->createRequest($method, $uri)
            ->withHeader('Key', $this->apiKey)
            ->withHeader('Accept', 'application/json');
    }

    private function send(RequestInterface $request, bool $expectJson = true): ApiResponse
    {
        $this->logger->debug('AbuseIPDB request {method} {uri}', ['method' => $request->getMethod(), 'uri' => (string) $request->getUri()]);

        try {
            $response = $this->client->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            $this->logger->error('AbuseIPDB transport failure: {message}', ['message' => $e->getMessage()]);
            throw new NetworkException('AbuseIPDB request failed: ' . $e->getMessage(), 0, $e);
        }

        $status = $response->getStatusCode();
        $rateLimit = RateLimit::fromResponse($response);
        $body = (string) $response->getBody();

        if ($status < 200 || $status >= 300) {
            throw $this->createApiException($response, $body, $rateLimit);
        }

        if (!$expectJson) {
            return new ApiResponse($status, [], $body, $rateLimit);
        }

        $json = self::decode($body);
        if (null === $json) {
            throw new UnexpectedResponseException(sprintf('AbuseIPDB returned a non-JSON response (HTTP %d).', $status));
        }

        return new ApiResponse($status, $json, $body, $rateLimit);
    }

    private function createApiException(ResponseInterface $response, string $body, RateLimit $rateLimit): ApiException
    {
        $status = $response->getStatusCode();
        $errors = [];
        foreach ((array) (self::decode($body)['errors'] ?? []) as $error) {
            if (is_array($error)) {
                $errors[] = $error;
            }
        }
        $details = array_filter(array_map(static fn (array $e) => is_string($e['detail'] ?? null) ? $e['detail'] : null, $errors));
        $message = sprintf('AbuseIPDB API error (HTTP %d %s)', $status, $response->getReasonPhrase());
        if ([] !== $details) {
            $message .= ': ' . implode(' ', $details);
        }
        $this->logger->warning($message);

        switch (true) {
            case 401 === $status:
                return new AuthenticationException($message, $status, $errors, $rateLimit);
            case 402 === $status:
                return new PaymentRequiredException($message, $status, $errors, $rateLimit);
            case 422 === $status:
                return new ValidationException($message, $status, $errors, $rateLimit);
            case 429 === $status:
                return new RateLimitExceededException($message, $status, $errors, $rateLimit);
            case $status >= 500:
                return new ServerException($message, $status, $errors, $rateLimit);
            default:
                return new ApiException($message, $status, $errors, $rateLimit);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function decode(string $body): ?array
    {
        $json = json_decode($body, true);

        return is_array($json) ? $json : null;
    }

    /**
     * Nulls are dropped, booleans become "true"/"false" and lists are comma-joined.
     *
     * @param array<string, scalar|list<scalar>|null> $params
     */
    private static function buildQuery(array $params): string
    {
        $normalized = [];
        foreach ($params as $key => $value) {
            if (null === $value) {
                continue;
            }
            if (is_array($value)) {
                $value = implode(',', array_map(static fn ($v) => is_bool($v) ? ($v ? 'true' : 'false') : (string) $v, $value));
            } elseif (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            }
            $normalized[$key] = (string) $value;
        }

        return http_build_query($normalized, '', '&', PHP_QUERY_RFC3986);
    }
}
