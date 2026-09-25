<?php

declare(strict_types=1);

namespace AbuseIpDb\Tests\Unit;

use AbuseIpDb\AbuseIpDbClient;
use AbuseIpDb\Enum\ReportCategory;
use AbuseIpDb\Exception\AuthenticationException;
use AbuseIpDb\Exception\InvalidArgumentException;
use AbuseIpDb\Exception\RateLimitExceededException;
use AbuseIpDb\Exception\ValidationException;
use AbuseIpDb\Request\BlacklistParameters;
use AbuseIpDb\Result\BlacklistResult;
use Http\Mock\Client as MockClient;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

final class AbuseIpDbClientTest extends TestCase
{
    private const BASE = 'https://api.abuseipdb.com/api/v2/';

    private MockClient $http;
    private AbuseIpDbClient $client;

    protected function setUp(): void
    {
        $factory = new Psr17Factory();
        $this->http = new MockClient($factory);
        $this->client = new AbuseIpDbClient('secret', $this->http, $factory, $factory);
    }

    public function testCheck(): void
    {
        $this->respond('check.json', ['X-RateLimit-Limit' => '1000', 'X-RateLimit-Remaining' => '999']);

        $result = $this->client->check('118.25.6.39', 90, true);

        $request = $this->lastRequest();
        self::assertSame('GET', $request->getMethod());
        self::assertSame(self::BASE . 'check?ipAddress=118.25.6.39&maxAgeInDays=90&verbose=true', (string) $request->getUri());
        self::assertSame('secret', $request->getHeaderLine('Key'));
        self::assertSame('application/json', $request->getHeaderLine('Accept'));
        self::assertSame(100, $result->getAbuseConfidenceScore());
        self::assertSame('CN', $result->getCountryCode());
        self::assertNotEmpty($result->getReports());
        $rateLimit = $this->client->getLastRateLimit();
        self::assertNotNull($rateLimit);
        self::assertSame(999, $rateLimit->getRemaining());
    }

    public function testCheckUsesDefaultMaxAgeInDays(): void
    {
        $factory = new Psr17Factory();
        $client = new AbuseIpDbClient('secret', $this->http, $factory, $factory, ['default_max_age_in_days' => 45]);
        $this->respond('check.json');

        $client->check('118.25.6.39');

        $request = $this->lastRequest();
        self::assertStringContainsString('maxAgeInDays=45', (string) $request->getUri());
    }

    public function testReports(): void
    {
        $this->respond('reports.json');

        $result = $this->client->reports('118.25.6.39', 30, 1, 2);

        self::assertSame(self::BASE . 'reports?ipAddress=118.25.6.39&maxAgeInDays=30&page=1&perPage=2', (string) $this->lastRequest()->getUri());
        self::assertSame(8, $result->getTotal());
        self::assertTrue($result->hasNextPage());
        self::assertCount(2, $result->getResults());
    }

    public function testBlacklistJson(): void
    {
        $this->respond('blacklist.json');

        $result = $this->client->blacklist(new BlacklistParameters(90, 100, ['us', 'de'], [], 4));

        $uri = (string) $this->lastRequest()->getUri();
        self::assertStringStartsWith(self::BASE . 'blacklist?', $uri);
        parse_str((string) parse_url($uri, PHP_URL_QUERY), $query);
        self::assertSame('90', $query['confidenceMinimum']);
        self::assertSame('100', $query['limit']);
        self::assertSame('US,DE', $query['onlyCountries']);
        self::assertSame('4', $query['ipVersion']);
        self::assertInstanceOf(BlacklistResult::class, $result);
        self::assertSame('5.188.10.179', $result->getIpAddresses()[0]);
        self::assertNotNull($result->getGeneratedAt());
    }

    public function testBlacklistDefaultsToJson(): void
    {
        $this->respond('blacklist.json');

        self::assertInstanceOf(BlacklistResult::class, $this->client->blacklist());
        self::assertSame(self::BASE . 'blacklist', rtrim((string) $this->lastRequest()->getUri(), '?'));
    }

    public function testBlacklistPlaintext(): void
    {
        $body = (string) file_get_contents(__DIR__ . '/../Fixtures/blacklist.txt');
        $this->http->addResponse(new Response(200, ['Content-Type' => 'text/plain'], $body));

        $result = $this->client->blacklist(new BlacklistParameters(null, null, [], [], null, true));

        self::assertStringContainsString('plaintext=true', (string) $this->lastRequest()->getUri());
        self::assertSame($body, $result);
    }

    public function testReport(): void
    {
        $this->respond('report.json');
        $ts = new \DateTimeImmutable('2023-10-18T11:25:11+00:00');

        $result = $this->client->report('127.0.0.1', [ReportCategory::BRUTE_FORCE, ReportCategory::SSH], 'SSH login attempts', $ts);

        $request = $this->lastRequest();
        self::assertSame('POST', $request->getMethod());
        self::assertSame(self::BASE . 'report', (string) $request->getUri());
        parse_str((string) $request->getBody(), $body);
        self::assertSame('127.0.0.1', $body['ip']);
        self::assertSame('18,22', $body['categories']);
        self::assertSame('SSH login attempts', $body['comment']);
        self::assertSame('2023-10-18T11:25:11+00:00', $body['timestamp']);
        self::assertSame(52, $result->getAbuseConfidenceScore());
    }

    public function testCheckBlock(): void
    {
        $this->respond('check-block.json');

        $result = $this->client->checkBlock('127.0.0.1/24', 15);

        self::assertSame(self::BASE . 'check-block?network=127.0.0.1%2F24&maxAgeInDays=15', (string) $this->lastRequest()->getUri());
        self::assertSame(254, $result->getNumPossibleHosts());
        self::assertCount(2, $result->getReportedAddresses());
    }

    public function testBulkReportFromFile(): void
    {
        $this->respond('bulk-report.json');
        $path = __DIR__ . '/../Fixtures/bulk-report.csv';

        $result = $this->client->bulkReport($path);

        $request = $this->lastRequest();
        self::assertSame('POST', $request->getMethod());
        self::assertSame(self::BASE . 'bulk-report', (string) $request->getUri());
        self::assertStringStartsWith('multipart/form-data; boundary=', $request->getHeaderLine('Content-Type'));
        $body = (string) $request->getBody();
        self::assertStringContainsString('name="csv"', $body);
        self::assertStringContainsString((string) file_get_contents($path), $body);
        self::assertSame(60, $result->getSavedReports());
        self::assertCount(2, $result->getInvalidReports());
    }

    public function testBulkReportFromContents(): void
    {
        $this->respond('bulk-report.json');

        $this->client->bulkReport("IP,Categories,ReportDate,Comment\n1.2.3.4,18,2023-01-01T00:00:00Z,test\n");

        self::assertStringContainsString('1.2.3.4,18', (string) $this->lastRequest()->getBody());
    }

    public function testClearAddress(): void
    {
        $this->respond('clear-address.json');

        $result = $this->client->clearAddress('127.0.0.1');

        self::assertSame('DELETE', $this->lastRequest()->getMethod());
        self::assertSame(self::BASE . 'clear-address?ipAddress=127.0.0.1', (string) $this->lastRequest()->getUri());
        self::assertSame(0, $result->getNumReportsDeleted());
    }

    public function testCustomBaseUri(): void
    {
        $factory = new Psr17Factory();
        $client = new AbuseIpDbClient('k', $this->http, $factory, $factory, ['base_uri' => 'https://proxy.test/v2']);
        $this->respond('clear-address.json');

        $client->clearAddress('127.0.0.1');

        self::assertSame('https://proxy.test/v2/clear-address?ipAddress=127.0.0.1', (string) $this->lastRequest()->getUri());
    }

    public function testInvalidInputFailsBeforeHttpCall(): void
    {
        try {
            $this->client->check('not-an-ip');
            self::fail('Expected exception');
        } catch (InvalidArgumentException $e) {
            self::assertCount(0, $this->http->getRequests());
        }
    }

    public function testAuthenticationError(): void
    {
        $this->http->addResponse($this->errorResponse(401, 'Authentication failed.'));

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Authentication failed.');
        $this->client->check('1.2.3.4');
    }

    public function testValidationError(): void
    {
        $this->http->addResponse($this->errorResponse(422, 'The ip address must be a valid IP address.'));

        $this->expectException(ValidationException::class);
        $this->client->report('1.2.3.4', [ReportCategory::SSH]);
    }

    public function testRateLimitError(): void
    {
        $this->http->addResponse($this->errorResponse(429, 'Daily rate limit of 1000 requests exceeded.', ['Retry-After' => '60']));

        $this->expectException(RateLimitExceededException::class);
        $this->client->blacklist();
    }

    /**
     * @param array<string, string> $headers
     */
    private function respond(string $fixture, array $headers = []): void
    {
        $body = (string) file_get_contents(__DIR__ . '/../Fixtures/' . $fixture);
        $this->http->addResponse(new Response(200, ['Content-Type' => 'application/json'] + $headers, $body));
    }

    /**
     * @param array<string, string> $headers
     */
    private function errorResponse(int $status, string $detail, array $headers = []): Response
    {
        $body = (string) json_encode(['errors' => [['detail' => $detail, 'status' => $status]]]);

        return new Response($status, ['Content-Type' => 'application/json'] + $headers, $body);
    }

    private function lastRequest(): RequestInterface
    {
        $request = $this->http->getLastRequest();
        self::assertInstanceOf(RequestInterface::class, $request);

        return $request;
    }
}
