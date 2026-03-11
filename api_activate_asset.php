<?php
/**
 * api_activate_asset.php — Re-activates an inactive device.
 * Accepts POST: id (int), hostname (string), modified_by (string ending in -adm)
 * Returns JSON: {success: bool, message: string}
 */
require_once __DIR__ . '/vendor/autoload.php';
require_once "config.php";

header('Content-Type: application/json; charset=utf-8');

// Only POST requests accepted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$id          = isset($_POST['id'])          ? (int)$_POST['id']                           : 0;
$hostname    = isset($_POST['hostname'])    ? strtoupper(trim($_POST['hostname']))         : '';
$modified_by = isset($_POST['modified_by']) ? strtolower(trim($_POST['modified_by']))     : '';

// Validate admin suffix
if (!\InventoryAgent\AdminValidator::isValid($modified_by)) {
    echo json_encode(['success' => false, 'message' => 'Admin ID (-adm suffix) required.']);
    exit;
}

if ($id <= 0 || $hostname === '') {
    echo json_encode(['success' => false, 'message' => 'Invalid device ID or hostname.']);
    exit;
}

// Verify the hostname matches the given ID (prevents tampering)
$check = $mysqli->prepare("SELECT id, hostname FROM devices WHERE id = ? AND status != 'Active' LIMIT 1");
$check->bind_param("i", $id);
$check->execute();
$device = $check->get_result()->fetch_assoc();

if (!$device) {
    echo json_encode(['success' => false, 'message' => 'Device not found or is already active.']);
    exit;
}

if (strtoupper($device['hostname']) !== $hostname) {
    echo json_encode(['success' => false, 'message' => 'Hostname confirmation does not match.']);
    exit;
}

// Perform the re-activation
$update = $mysqli->prepare(
    "UPDATE devices SET status = 'Active', updated_at = NOW() WHERE id = ? LIMIT 1"
);
$update->bind_param("i", $id);

if (!$update->execute() || $update->affected_rows < 1) {
    echo json_encode(['success' => false, 'message' => 'Database update failed.']);
    exit;
}

// Log the activation in the change log
$log = $mysqli->prepare(
    "INSERT INTO device_change_logs (device_id, change_type, old_value, new_value) VALUES (?, 'Status Change', ?, 'Active')"
);
// $device['status'] is guaranteed non-null by the schema (NOT NULL column), but we guard defensively
$oldStatus = !empty($device['status']) ? $device['status'] : 'In-Store';
$log->bind_param("is", $id, $oldStatus);
$log->execute();

echo json_encode(['success' => true, 'message' => 'Device re-activated successfully.']);
