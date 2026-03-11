<?php

declare(strict_types=1);

namespace InventoryAgent;

/**
 * Resolves a drilldown type string to a SQL WHERE clause fragment.
 * Only types present in ALLOWED_TYPES are accepted; anything else
 * falls back to a no-op clause ("1=1").
 */
class DrilldownFilter
{
    /** @var string[] */
    public const ALLOWED_TYPES = ['missing_asset_tag', 'offline', 'pending_reboot'];

    /**
     * Returns true when $type is in the allowed-types whitelist.
     */
    public static function isAllowed(string $type): bool
    {
        return in_array($type, self::ALLOWED_TYPES, true);
    }

    /**
     * Maps a whitelisted type to its SQL WHERE clause fragment.
     * Returns '1=1' for unknown types so queries are always valid.
     */
    public static function toWhereClause(string $type): string
    {
        return match ($type) {
            'missing_asset_tag' => "NOT EXISTS (SELECT 1 FROM asset_tag_map atm WHERE atm.serial_number = d.serial)",
            'offline'           => "DATEDIFF(CURDATE(), d.last_seen) > 7",
            'pending_reboot'    => "d.uptime_seconds > 604800",
            default             => "1=1",
        };
    }
}
