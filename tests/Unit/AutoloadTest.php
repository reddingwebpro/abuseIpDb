<?php

declare(strict_types=1);

namespace AbuseIpDb\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AutoloadTest extends TestCase
{
    public function testTestNamespaceIsAutoloaded(): void
    {
        self::assertTrue(class_exists(self::class));
    }
}
