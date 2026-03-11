<?php
/**
 * compare_lookup.php — Fast device search endpoint for Device History comparison.
 * Returns JSON array of {id, hostname, serial} objects matching the query string.
 */
require_once "config.php";

header('Content-Type: application/json; charset=utf-8');

$q = isset($_GET['q']) ? trim($_GET['q']) : '';

if (mb_strlen($q) < 2) {
    echo json_encode([]);
    exit;
}

// Escape LIKE special characters to prevent unexpected wildcard expansion
$q_escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q);
$like = '%' . $q_escaped . '%';
$stmt = $mysqli->prepare(
    "SELECT id, hostname, serial
     FROM devices
     WHERE hostname LIKE ? OR serial LIKE ?
     ORDER BY hostname ASC
     LIMIT 20"
);
$stmt->bind_param("ss", $like, $like);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

echo json_encode($rows);
