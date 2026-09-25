<?php

declare(strict_types=1);

namespace AbuseIpDb\Tests\Unit\Http;

use AbuseIpDb\Exception\AbuseIpDbException;
use AbuseIpDb\Exception\ApiException;
use AbuseIpDb\Exception\AuthenticationException;
use AbuseIpDb\Exception\InvalidArgumentException;
use AbuseIpDb\Exception\NetworkException;
use AbuseIpDb\Exception\PaymentRequiredException;
use AbuseIpDb\Exception\RateLimitExceededException;
use AbuseIpDb\Exception\ServerException;
use AbuseIpDb\Exception\UnexpectedResponseException;
use AbuseIpDb\Exception\ValidationException;
use AbuseIpDb\Http\HttpTransport;
use Http\Client\Exception\TransferException;
use Http\Mock\Client as MockClient;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

final class HttpTransportTest extends TestCase
{
    private MockClient $client;
    private HttpTransport $transport;

    protected function setUp(): void
    {
        $factory = new Psr17Factory();
        $this->client = new MockClient($factory);
        $this->transport = new HttpTransport('secret-key', $this->client, $factory, $factory);
    }

    public function testGetSendsAuthHeadersAndQuery(): void
    {
        $this->client->addResponse(self::json(200, ['data' => ['ipAddress' => '1.2.3.4']]));

        $response = $this->transport->get('check', ['ipAddress' => '1.2.3.4', 'maxAgeInDays' => 30, 'verbose' => true, 'skip' => null]);

        $request = $this->lastRequest();
        self::assertSame('GET', $request->getMethod());
        self::assertSame('https://api.abuseipdb.com/api/v2/check?ipAddress=1.2.3.4&maxAgeInDays=30&verbose=true', (string) $request->getUri());
        self::assertSame('secret-key', $request->getHeaderLine('Key'));
        self::assertSame('application/json', $request->getHeaderLine('Accept'));
        self::assertSame(['ipAddress' => '1.2.3.4'], $response->getData());
        self::assertSame(200, $response->getStatusCode());
    }

    public function testCustomBaseUriAndListQuery(): void
    {
        $factory = new Psr17Factory();
        $transport = new HttpTransport('k', $this->client, $factory, $factory, 'https://example.test/v2');
        $this->client->addResponse(self::json(200, ['data' => []]));

        $transport->get('/blacklist', ['onlyCountries' => ['US', 'DE'], 'plaintext' => false]);

        self::assertSame('https://example.test/v2/blacklist?onlyCountries=US%2CDE&plaintext=false', (string) $this->lastRequest()->getUri());
    }

    public function testGetPlaintextReturnsRawBody(): void
    {
        $this->client->addResponse(new Response(200, ['Content-Type' => 'text/plain'], "1.2.3.4\n5.6.7.8"));

        $response = $this->transport->get('blacklist', ['plaintext' => true], false);

        self::assertSame("1.2.3.4\n5.6.7.8", $response->getBody());
        self::assertSame([], $response->getJson());
    }

    public function testDelete(): void
    {
        $this->client->addResponse(self::json(200, ['data' => ['numReportsDeleted' => 2]]));

        $response = $this->transport->delete('clear-address', ['ipAddress' => '1.2.3.4']);

        self::assertSame('DELETE', $this->lastRequest()->getMethod());
        self::assertStringEndsWith('clear-address?ipAddress=1.2.3.4', (string) $this->lastRequest()->getUri());
        self::assertSame(['numReportsDeleted' => 2], $response->getData());
    }

    public function testPostForm(): void
    {
        $this->client->addResponse(self::json(200, ['data' => ['abuseConfidenceScore' => 52]]));

        $this->transport->postForm('report', ['ip' => '1.2.3.4', 'categories' => [18, 22], 'comment' => 'ssh brute force']);

        $request = $this->lastRequest();
        self::assertSame('POST', $request->getMethod());
        self::assertSame('application/x-www-form-urlencoded', $request->getHeaderLine('Content-Type'));
        self::assertSame('ip=1.2.3.4&categories=18%2C22&comment=ssh%20brute%20force', (string) $request->getBody());
        self::assertSame('secret-key', $request->getHeaderLine('Key'));
    }

    public function testPostJson(): void
    {
        $this->client->addResponse(self::json(200, ['data' => []]));

        $this->transport->postJson('report', ['ip' => '1.2.3.4']);

        self::assertSame('application/json', $this->lastRequest()->getHeaderLine('Content-Type'));
        self::assertSame('{"ip":"1.2.3.4"}', (string) $this->lastRequest()->getBody());
    }

    public function testPostMultipart(): void
    {
        $this->client->addResponse(self::json(200, ['data' => ['savedReports' => 1, 'invalidReports' => []]]));

        $this->transport->postMultipart('bulk-report', 'csv', "IP,Categories\n1.2.3.4,18", 'report.csv');

        $request = $this->lastRequest();
        self::assertSame(1, preg_match('/^multipart\/form-data; boundary=([0-9a-f]{32})$/', $request->getHeaderLine('Content-Type'), $m));
        $boundary = $m[1] ?? '';
        $body = (string) $request->getBody();
        self::assertStringStartsWith('--' . $boundary . "\r\n", $body);
        self::assertStringContainsString('Content-Disposition: form-data; name="csv"; filename="report.csv"', $body);
        self::assertStringContainsString("Content-Type: text/csv\r\n\r\nIP,Categories\n1.2.3.4,18\r\n", $body);
        self::assertStringEndsWith('--' . $boundary . "--\r\n", $body);
    }

    public function testRateLimitHeadersAreExposed(): void
    {
        $this->client->addResponse(self::json(200, ['data' => []], [
            'X-RateLimit-Limit' => '1000',
            'X-RateLimit-Remaining' => '999',
            'X-RateLimit-Reset' => '1700000000',
        ]));

        $rateLimit = $this->transport->get('check')->getRateLimit();

        self::assertSame(1000, $rateLimit->getLimit());
        self::assertSame(999, $rateLimit->getRemaining());
        self::assertNotNull($rateLimit->getResetAt());
        self::assertSame(1700000000, $rateLimit->getResetAt()->getTimestamp());
        self::assertNull($rateLimit->getRetryAfter());
    }

    public function testMissingRateLimitHeadersDegradeToNull(): void
    {
        $this->client->addResponse(self::json(200, ['data' => []], ['X-RateLimit-Limit' => 'abc']));

        $rateLimit = $this->transport->get('check')->getRateLimit();

        self::assertNull($rateLimit->getLimit());
        self::assertNull($rateLimit->getRemaining());
        self::assertNull($rateLimit->getResetAt());
    }

    /**
     * @return iterable<string, array{int, class-string<ApiException>}>
     */
    public function statusProvider(): iterable
    {
        yield '401' => [401, AuthenticationException::class];
        yield '402' => [402, PaymentRequiredException::class];
        yield '422' => [422, ValidationException::class];
        yield '429' => [429, RateLimitExceededException::class];
        yield '500' => [500, ServerException::class];
        yield '503' => [503, ServerException::class];
        yield '404' => [404, ApiException::class];
    }

    /**
     * @dataProvider statusProvider
     *
     * @param class-string<ApiException> $class
     */
    public function testStatusCodesMapToExceptions(int $status, string $class): void
    {
        $this->client->addResponse(self::json($status, ['errors' => [['detail' => 'Something failed.', 'status' => $status]]]));

        try {
            $this->transport->get('check');
            self::fail('Exception expected');
        } catch (ApiException $e) {
            self::assertSame($class, get_class($e));
            self::assertInstanceOf(AbuseIpDbException::class, $e);
            self::assertSame($status, $e->getStatusCode());
            self::assertSame($status, $e->getCode());
            self::assertStringContainsString('Something failed.', $e->getMessage());
            self::assertCount(1, $e->getErrors());
        }
    }

    public function testValidationExceptionExtractsFieldErrors(): void
    {
        $this->client->addResponse(self::json(422, ['errors' => [
            ['detail' => 'The max age in days must be between 1 and 365.', 'status' => 422, 'source' => ['parameter' => 'maxAgeInDays']],
            ['detail' => 'The ip address must be a valid IPv4 or IPv6 address.', 'status' => 422, 'source' => ['parameter' => 'ipAddress']],
            ['detail' => 'General failure.', 'status' => 422],
        ]]));

        try {
            $this->transport->get('check');
            self::fail('Exception expected');
        } catch (ValidationException $e) {
            self::assertSame([
                'maxAgeInDays' => ['The max age in days must be between 1 and 365.'],
                'ipAddress' => ['The ip address must be a valid IPv4 or IPv6 address.'],
                '_' => ['General failure.'],
            ], $e->getFieldErrors());
            self::assertStringContainsString('between 1 and 365. The ip address', $e->getMessage());
        }
    }

    public function testRateLimitExceededCarriesRetryInfo(): void
    {
        $this->client->addResponse(self::json(429, ['errors' => [['detail' => 'Daily rate limit of 1000 requests exceeded for this endpoint.']]], [
            'X-RateLimit-Limit' => '1000',
            'X-RateLimit-Remaining' => '0',
            'Retry-After' => '29642',
        ]));

        try {
            $this->transport->get('check');
            self::fail('Exception expected');
        } catch (RateLimitExceededException $e) {
            self::assertSame(29642, $e->getRetryAfter());
            self::assertSame(0, $e->getRateLimit()->getRemaining());
            self::assertSame(1000, $e->getRateLimit()->getLimit());
        }
    }

    public function testErrorWithNonJsonBodyStillMaps(): void
    {
        $this->client->addResponse(new Response(502, [], '<html>Bad gateway</html>'));

        $this->expectException(ServerException::class);
        $this->expectExceptionMessage('AbuseIPDB API error (HTTP 502 Bad Gateway)');

        $this->transport->get('check');
    }

    public function testTransportFailureIsWrapped(): void
    {
        $previous = new TransferException('Connection refused');
        $this->client->addException($previous);

        try {
            $this->transport->get('check');
            self::fail('Exception expected');
        } catch (NetworkException $e) {
            self::assertSame($previous, $e->getPrevious());
            self::assertStringContainsString('Connection refused', $e->getMessage());
        }
    }

    public function testMalformedJsonThrows(): void
    {
        $this->client->addResponse(new Response(200, [], '{not json'));

        $this->expectException(UnexpectedResponseException::class);

        $this->transport->get('check');
    }

    public function testEmptyApiKeyIsRejected(): void
    {
        $factory = new Psr17Factory();

        $this->expectException(InvalidArgumentException::class);

        new HttpTransport(' ', $this->client, $factory, $factory);
    }

    public function testFactoriesAreDiscoveredWhenOmitted(): void
    {
        $this->client->addResponse(self::json(200, ['data' => []]));

        $transport = new HttpTransport('k', $this->client);
        $transport->get('check');

        self::assertSame('k', $this->lastRequest()->getHeaderLine('Key'));
    }

    /**
     * @param array<string, mixed>  $payload
     * @param array<string, string> $headers
     */
    private static function json(int $status, array $payload, array $headers = []): Response
    {
        return new Response($status, ['Content-Type' => 'application/json'] + $headers, (string) json_encode($payload));
    }

    private function lastRequest(): RequestInterface
    {
        $request = $this->client->getLastRequest();
        self::assertInstanceOf(RequestInterface::class, $request);

        return $request;
    }
}
