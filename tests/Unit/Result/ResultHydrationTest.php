<?php

declare(strict_types=1);

namespace AbuseIpDb\Tests\Unit\Result;

use AbuseIpDb\Result\BlacklistEntry;
use AbuseIpDb\Result\BlacklistResult;
use AbuseIpDb\Result\BulkReportResult;
use AbuseIpDb\Result\CheckBlockResult;
use AbuseIpDb\Result\CheckResult;
use AbuseIpDb\Result\ClearAddressResult;
use AbuseIpDb\Result\ReportResult;
use AbuseIpDb\Result\ReportsResult;
use PHPUnit\Framework\TestCase;

final class ResultHydrationTest extends TestCase
{
    /** @return array<mixed> */
    private static function fixture(string $name, bool $dataOnly = true): array
    {
        $json = json_decode((string) file_get_contents(__DIR__ . '/../../Fixtures/' . $name), true);
        self::assertIsArray($json);

        return $dataOnly ? $json['data'] : $json;
    }

    public function testCheckResult(): void
    {
        $r = CheckResult::fromArray(self::fixture('check.json'));
        self::assertSame('118.25.6.39', $r->getIpAddress());
        self::assertTrue($r->isPublic());
        self::assertSame(4, $r->getIpVersion());
        self::assertFalse($r->isWhitelisted());
        self::assertSame(100, $r->getAbuseConfidenceScore());
        self::assertSame('CN', $r->getCountryCode());
        self::assertSame('China', $r->getCountryName());
        self::assertSame('Data Center/Web Hosting/Transit', $r->getUsageType());
        self::assertSame('tencent.com', $r->getDomain());
        self::assertStringStartsWith('Tencent', (string) $r->getIsp());
        self::assertSame([], $r->getHostnames());
        self::assertFalse($r->isTor());
        self::assertSame(1, $r->getTotalReports());
        self::assertSame(1, $r->getNumDistinctUsers());
        self::assertSame('2018-12-20T20:55:14+00:00', (string) ($r->getLastReportedAt() ? $r->getLastReportedAt()->format(DATE_ATOM) : null));
        self::assertCount(1, $r->getReports());
        $report = $r->getReports()[0];
        self::assertSame([18, 22], $report->getCategories());
        self::assertSame(1, $report->getReporterId());
        self::assertSame('US', $report->getReporterCountryCode());
        self::assertSame('United States', $report->getReporterCountryName());
        self::assertStringContainsString('sshd', (string) $report->getComment());
        self::assertNotNull($report->getReportedAt());
    }

    public function testCheckResultDegradesGracefully(): void
    {
        $r = CheckResult::fromArray(['ipAddress' => '1.1.1.1', 'lastReportedAt' => null, 'hostnames' => 'bad']);
        self::assertSame(0, $r->getAbuseConfidenceScore());
        self::assertNull($r->getLastReportedAt());
        self::assertNull($r->getCountryName());
        self::assertSame([], $r->getHostnames());
        self::assertSame([], $r->getReports());
    }

    public function testReportsResult(): void
    {
        $r = ReportsResult::fromArray(self::fixture('reports.json'));
        self::assertSame(8, $r->getTotal());
        self::assertSame(1, $r->getPage());
        self::assertSame(2, $r->getCount());
        self::assertSame(2, $r->getPerPage());
        self::assertSame(4, $r->getLastPage());
        self::assertTrue($r->hasNextPage());
        self::assertStringContainsString('page=2', (string) $r->getNextPageUrl());
        self::assertNull($r->getPreviousPageUrl());
        self::assertCount(2, $r->getResults());
        self::assertSame([14], $r->getResults()[1]->getCategories());
        self::assertSame('Germany', $r->getResults()[1]->getReporterCountryName());
    }

    public function testBlacklistResult(): void
    {
        $r = BlacklistResult::fromArray(self::fixture('blacklist.json', false));
        self::assertSame('2020-09-24T19:54:11+00:00', (string) ($r->getGeneratedAt() ? $r->getGeneratedAt()->format(DATE_ATOM) : null));
        self::assertCount(2, $r);
        self::assertSame(['5.188.10.179', '185.222.209.14'], $r->getIpAddresses());
        $first = $r->getEntries()[0];
        self::assertSame('RU', $first->getCountryCode());
        self::assertSame(100, $first->getAbuseConfidenceScore());
        self::assertNotNull($first->getLastReportedAt());
        self::assertContainsOnlyInstancesOf(BlacklistEntry::class, iterator_to_array($r));
        self::assertNull(BlacklistResult::fromArray([])->getGeneratedAt());
    }

    public function testReportResult(): void
    {
        $r = ReportResult::fromArray(self::fixture('report.json'));
        self::assertSame('127.0.0.1', $r->getIpAddress());
        self::assertSame(52, $r->getAbuseConfidenceScore());
    }

    public function testCheckBlockResult(): void
    {
        $r = CheckBlockResult::fromArray(self::fixture('check-block.json'));
        self::assertSame('127.0.0.0', $r->getNetworkAddress());
        self::assertSame('255.255.255.0', $r->getNetmask());
        self::assertSame('127.0.0.1', $r->getMinAddress());
        self::assertSame('127.0.0.254', $r->getMaxAddress());
        self::assertSame(254, $r->getNumPossibleHosts());
        self::assertSame('Loopback', $r->getAddressSpaceDesc());
        self::assertCount(2, $r->getReportedAddresses());
        $a = $r->getReportedAddresses()[0];
        self::assertSame('127.0.0.1', $a->getIpAddress());
        self::assertSame(631, $a->getNumReports());
        self::assertSame(0, $a->getAbuseConfidenceScore());
        self::assertNull($a->getCountryCode());
        self::assertNotNull($a->getMostRecentReport());
    }

    public function testBulkReportResult(): void
    {
        $r = BulkReportResult::fromArray(self::fixture('bulk-report.json'));
        self::assertSame(60, $r->getSavedReports());
        self::assertTrue($r->hasInvalidReports());
        $e = $r->getInvalidReports()[1];
        self::assertSame('Invalid IP', $e->getError());
        self::assertSame('127.0.foo.bar', $e->getInput());
        self::assertSame(6, $e->getRowNumber());
        self::assertFalse(BulkReportResult::fromArray(['savedReports' => 3, 'invalidReports' => []])->hasInvalidReports());
    }

    public function testClearAddressResult(): void
    {
        self::assertSame(0, ClearAddressResult::fromArray(self::fixture('clear-address.json'))->getNumReportsDeleted());
        self::assertSame(7, ClearAddressResult::fromArray(['numReportsDeleted' => 7])->getNumReportsDeleted());
    }
}
