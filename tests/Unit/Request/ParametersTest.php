<?php

declare(strict_types=1);

namespace AbuseIpDb\Tests\Unit\Request;

use AbuseIpDb\Enum\ReportCategory;
use AbuseIpDb\Exception\AbuseIpDbException;
use AbuseIpDb\Exception\InvalidArgumentException;
use AbuseIpDb\Request\BlacklistParameters;
use AbuseIpDb\Request\BulkReportParameters;
use AbuseIpDb\Request\CheckBlockParameters;
use AbuseIpDb\Request\CheckParameters;
use AbuseIpDb\Request\ClearAddressParameters;
use AbuseIpDb\Request\ReportParameters;
use AbuseIpDb\Request\ReportsParameters;
use PHPUnit\Framework\TestCase;

final class ParametersTest extends TestCase
{
    public function testCheckQuery(): void
    {
        self::assertSame(['ipAddress' => '1.2.3.4'], (new CheckParameters('1.2.3.4'))->toQuery());
        self::assertSame(
            ['ipAddress' => '::1', 'maxAgeInDays' => 90, 'verbose' => true],
            (new CheckParameters('::1', 90, true))->toQuery()
        );
    }

    public function testReportsQuery(): void
    {
        self::assertSame(
            ['ipAddress' => '1.2.3.4', 'maxAgeInDays' => 30, 'page' => 2, 'perPage' => 100],
            (new ReportsParameters('1.2.3.4', 30, 2, 100))->toQuery()
        );
        self::assertSame(['ipAddress' => '1.2.3.4', 'page' => 1, 'perPage' => 25], (new ReportsParameters('1.2.3.4'))->toQuery());
    }

    public function testBlacklistQuery(): void
    {
        self::assertSame([], (new BlacklistParameters())->toQuery());
        $p = new BlacklistParameters(90, 1000, ['us', ' cn'], [], 4, true);
        self::assertSame(
            ['confidenceMinimum' => 90, 'limit' => 1000, 'onlyCountries' => ['US', 'CN'], 'ipVersion' => 4, 'plaintext' => true],
            $p->toQuery()
        );
        self::assertTrue($p->isPlaintext());
        self::assertSame(['exceptCountries' => ['DE']], (new BlacklistParameters(null, null, [], ['de']))->toQuery());
    }

    public function testReportBody(): void
    {
        $ts = new \DateTime('2023-10-18T11:25:11-04:00');
        $p = new ReportParameters('127.0.0.1', [ReportCategory::BRUTE_FORCE, ReportCategory::SSH, 18], 'SSH login attempts', $ts);
        self::assertSame(
            ['ip' => '127.0.0.1', 'categories' => [18, 22], 'comment' => 'SSH login attempts', 'timestamp' => '2023-10-18T11:25:11-04:00'],
            $p->toBody()
        );
        self::assertInstanceOf(\DateTimeImmutable::class, $p->getTimestamp());
        self::assertSame(['ip' => '::1', 'categories' => [4]], (new ReportParameters('::1', [4]))->toBody());
    }

    public function testCheckBlockAndClearAddressQuery(): void
    {
        self::assertSame(['network' => '127.0.0.1/24', 'maxAgeInDays' => 15], (new CheckBlockParameters('127.0.0.1/24', 15))->toQuery());
        self::assertSame(['network' => '2001:db8::/64'], (new CheckBlockParameters('2001:db8::/64'))->toQuery());
        self::assertSame(['ipAddress' => '1.2.3.4'], (new ClearAddressParameters('1.2.3.4'))->toQuery());
    }

    public function testBulkReportFromFileAndContents(): void
    {
        $path = __DIR__ . '/../../Fixtures/bulk-report.csv';
        $p = BulkReportParameters::fromPathOrContents($path);
        self::assertSame('bulk-report.csv', $p->getFilename());
        self::assertSame(file_get_contents($path), $p->getContents());

        $p = BulkReportParameters::fromPathOrContents("IP,Categories\n1.2.3.4,18\n");
        self::assertSame('report.csv', $p->getFilename());
        self::assertSame("IP,Categories\n1.2.3.4,18\n", $p->getContents());
    }

    /**
     * @dataProvider invalidProvider
     */
    public function testValidation(callable $factory): void
    {
        $this->expectException(InvalidArgumentException::class);
        $factory();
    }

    /** @return iterable<string, array{callable}> */
    public static function invalidProvider(): iterable
    {
        yield 'check bad ip' => [static fn () => new CheckParameters('999.1.1.1')];
        yield 'check maxAge 0' => [static fn () => new CheckParameters('1.1.1.1', 0)];
        yield 'check maxAge 366' => [static fn () => new CheckParameters('1.1.1.1', 366)];
        yield 'reports page 0' => [static fn () => new ReportsParameters('1.1.1.1', null, 0)];
        yield 'reports perPage 101' => [static fn () => new ReportsParameters('1.1.1.1', null, 1, 101)];
        yield 'blacklist confidence 24' => [static fn () => new BlacklistParameters(24)];
        yield 'blacklist limit 0' => [static fn () => new BlacklistParameters(null, 0)];
        yield 'blacklist ipVersion 5' => [static fn () => new BlacklistParameters(null, null, [], [], 5)];
        yield 'blacklist bad country' => [static fn () => new BlacklistParameters(null, null, ['USA'])];
        yield 'blacklist both country lists' => [static fn () => new BlacklistParameters(null, null, ['US'], ['CN'])];
        yield 'report bad ip' => [static fn () => new ReportParameters('nope', [18])];
        yield 'report no categories' => [static fn () => new ReportParameters('1.1.1.1', [])];
        yield 'report unknown category' => [static fn () => new ReportParameters('1.1.1.1', [24])];
        yield 'report long comment' => [static fn () => new ReportParameters('1.1.1.1', [18], str_repeat('a', 1025))];
        yield 'checkBlock no prefix' => [static fn () => new CheckBlockParameters('1.1.1.1')];
        yield 'checkBlock prefix too big' => [static fn () => new CheckBlockParameters('1.1.1.1/33')];
        yield 'checkBlock bad address' => [static fn () => new CheckBlockParameters('foo/24')];
        yield 'clearAddress bad ip' => [static fn () => new ClearAddressParameters('')];
        yield 'bulk empty' => [static fn () => new BulkReportParameters("  \n")];
        yield 'bulk too big' => [static fn () => new BulkReportParameters(str_repeat('a', BulkReportParameters::MAX_BYTES + 1))];
        yield 'bulk missing file' => [static fn () => BulkReportParameters::fromFile('/nonexistent/file.csv')];
    }

    public function testInvalidArgumentIsLibraryException(): void
    {
        try {
            new CheckParameters('x');
            self::fail('Expected exception');
        } catch (AbuseIpDbException $e) {
            self::assertStringContainsString('"x"', $e->getMessage());
        }
    }
}
