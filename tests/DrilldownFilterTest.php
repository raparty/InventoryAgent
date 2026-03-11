<?php

declare(strict_types=1);

namespace InventoryAgent\Tests;

use InventoryAgent\DrilldownFilter;
use PHPUnit\Framework\TestCase;

class DrilldownFilterTest extends TestCase
{
    // -----------------------------------------------------------------------
    // isAllowed
    // -----------------------------------------------------------------------

    /** @dataProvider allowedTypeProvider */
    public function testAllowedTypesAreAccepted(string $type): void
    {
        $this->assertTrue(DrilldownFilter::isAllowed($type));
    }

    /** @return array<string, array{string}> */
    public static function allowedTypeProvider(): array
    {
        return [
            'missing_asset_tag' => ['missing_asset_tag'],
            'offline'           => ['offline'],
            'pending_reboot'    => ['pending_reboot'],
        ];
    }

    /** @dataProvider rejectedTypeProvider */
    public function testUnknownTypesAreRejected(string $type): void
    {
        $this->assertFalse(DrilldownFilter::isAllowed($type));
    }

    /** @return array<string, array{string}> */
    public static function rejectedTypeProvider(): array
    {
        return [
            'empty string'          => [''],
            'sql injection attempt' => ["offline' OR '1'='1"],
            'unknown type'          => ['all_devices'],
            'partial match'         => ['offlin'],
        ];
    }

    // -----------------------------------------------------------------------
    // toWhereClause
    // -----------------------------------------------------------------------

    public function testMissingAssetTagWhereClause(): void
    {
        $clause = DrilldownFilter::toWhereClause('missing_asset_tag');
        $this->assertStringContainsString('asset_tag_map', $clause);
        $this->assertStringContainsString('NOT EXISTS', $clause);
    }

    public function testOfflineWhereClause(): void
    {
        $clause = DrilldownFilter::toWhereClause('offline');
        $this->assertStringContainsString('last_seen', $clause);
        $this->assertStringContainsString('7', $clause);
    }

    public function testPendingRebootWhereClause(): void
    {
        $clause = DrilldownFilter::toWhereClause('pending_reboot');
        $this->assertStringContainsString('uptime_seconds', $clause);
    }

    public function testUnknownTypeReturnsFallbackClause(): void
    {
        $this->assertSame('1=1', DrilldownFilter::toWhereClause(''));
        $this->assertSame('1=1', DrilldownFilter::toWhereClause('unknown'));
    }
}
