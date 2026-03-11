<?php

declare(strict_types=1);

namespace InventoryAgent;

/**
 * Retrieves and decodes a single device-history snapshot from the database.
 */
class SnapshotService
{
    private \mysqli $mysqli;

    public function __construct(\mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    /**
     * Fetches the raw_json column for the given snapshot id / device id pair
     * and returns it as an associative array.  Returns [] when no row is found.
     *
     * @return array<string, mixed>
     */
    public function getSnapshot(int $snapshotId, int $deviceId): array
    {
        $stmt = $this->mysqli->prepare(
            "SELECT raw_json FROM device_history WHERE id = ? AND device_id = ? LIMIT 1"
        );
        $stmt->bind_param("ii", $snapshotId, $deviceId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return $row ? (json_decode($row['raw_json'], true) ?? []) : [];
    }
}
