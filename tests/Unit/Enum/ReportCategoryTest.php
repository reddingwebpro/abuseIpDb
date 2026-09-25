<?php

declare(strict_types=1);

namespace AbuseIpDb\Tests\Unit\Enum;

use AbuseIpDb\Enum\ReportCategory;
use PHPUnit\Framework\TestCase;

final class ReportCategoryTest extends TestCase
{
    public function testIdsCoverOneToTwentyThree(): void
    {
        $constants = (new \ReflectionClass(ReportCategory::class))->getConstants();
        unset($constants['NAMES']);
        $ids = array_values($constants);
        sort($ids);
        self::assertSame(range(1, 23), $ids);
        self::assertSame(range(1, 23), array_keys(ReportCategory::all()));
    }

    public function testLookup(): void
    {
        self::assertSame(18, ReportCategory::BRUTE_FORCE);
        self::assertSame(22, ReportCategory::SSH);
        self::assertSame('SSH', ReportCategory::nameOf(22));
        self::assertNull(ReportCategory::nameOf(24));
        self::assertTrue(ReportCategory::isValid(1));
        self::assertFalse(ReportCategory::isValid(0));
    }
}
