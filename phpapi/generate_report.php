<?php
/**
 * phpapi/generate_report.php — Generates CSV reports for the Reports Center.
 *
 * Supported report_type values:
 *   summary                     — Core device summary
 *   full                        — Full inventory export
 *   installed_software          — Devices with a specific software (requires software_name)
 *   missing_computer_asset_tags — Devices without an asset tag
 *   missing_monitor_asset_tags  — Monitors without an asset tag (stub)
 *   os_report                   — Devices by OS (requires os)
 *   ubr_report                  — OS Build/UBR distribution
 *   manufacturer_report         — Devices by manufacturer (requires manufacturer)
 *   model_report                — Devices by model (requires model)
 */
require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/config.php';

$report_type = isset($_GET['report_type']) ? trim($_GET['report_type']) : '';

$allowed_types = [
    'summary', 'full', 'installed_software',
    'missing_computer_asset_tags', 'missing_monitor_asset_tags',
    'os_report', 'ubr_report', 'manufacturer_report', 'model_report',
];

if (!in_array($report_type, $allowed_types, true)) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Invalid or missing report_type.']);
    exit;
}

// Output CSV headers
$filename = 'Report_' . preg_replace('/[^a-z0-9_]/i', '_', $report_type) . '_' . date('Y-m-d') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');

/**
 * Escape special LIKE pattern characters so user input is treated as literal text.
 */
function like_escape(string $value): string
{
    return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
}

switch ($report_type) {

    // ------------------------------------------------------------------
    case 'summary':
        fputcsv($out, ['Device ID', 'Hostname', 'Serial', 'Model', 'Manufacturer', 'OS Name', 'OS Build', 'UBR', 'Status', 'Last Seen']);
        $res = $mysqli->query("SELECT id, hostname, serial, model, manufacturer, os_name, os_build, os_ubr, status, last_seen FROM devices ORDER BY hostname");
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                fputcsv($out, [$r['id'], $r['hostname'], $r['serial'], $r['model'], $r['manufacturer'], $r['os_name'], $r['os_build'], $r['os_ubr'], $r['status'], $r['last_seen']]);
            }
        }
        break;

    // ------------------------------------------------------------------
    case 'full':
        fputcsv($out, ['Device ID', 'Hostname', 'Serial', 'Model', 'Manufacturer', 'Chassis', 'OS Name', 'OS Build', 'UBR', 'Location', 'Site', 'Status', 'Last Seen', 'Asset Tag']);
        $res = $mysqli->query(
            "SELECT d.id, d.hostname, d.serial, d.model, d.manufacturer, d.chassis_type,
                    d.os_name, d.os_build, d.os_ubr, d.location, d.location_agent, d.status, d.last_seen,
                    atm.asset_tag
             FROM devices d
             LEFT JOIN asset_tag_map atm ON atm.serial_number = d.serial
             ORDER BY d.hostname"
        );
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                fputcsv($out, [$r['id'], $r['hostname'], $r['serial'], $r['model'], $r['manufacturer'], $r['chassis_type'], $r['os_name'], $r['os_build'], $r['os_ubr'], $r['location'], $r['location_agent'], $r['status'], $r['last_seen'], $r['asset_tag']]);
            }
        }
        break;

    // ------------------------------------------------------------------
    case 'installed_software':
        $sw_name = isset($_GET['software_name']) ? trim($_GET['software_name']) : '';
        if ($sw_name === '') {
            fputcsv($out, ['Error']);
            fputcsv($out, ['software_name parameter is required.']);
            break;
        }
        fputcsv($out, ['Hostname', 'Serial', 'Location', 'Software Name', 'Version', 'Install Date']);
        $like = '%' . like_escape($sw_name) . '%';
        $stmt = $mysqli->prepare(
            "SELECT d.hostname, d.serial, d.location, s.software_name, s.version, s.install_date
             FROM installed_software s
             JOIN devices d ON s.device_id = d.id
             WHERE s.software_name LIKE ?
             ORDER BY d.hostname"
        );
        $stmt->bind_param("s", $like);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            fputcsv($out, [$r['hostname'], $r['serial'], $r['location'], $r['software_name'], $r['version'], $r['install_date']]);
        }
        break;

    // ------------------------------------------------------------------
    case 'missing_computer_asset_tags':
        fputcsv($out, ['Device ID', 'Hostname', 'Serial', 'Model', 'Location', 'Last Seen']);
        $res = $mysqli->query(
            "SELECT d.id, d.hostname, d.serial, d.model, d.location, d.last_seen
             FROM devices d
             WHERE NOT EXISTS (SELECT 1 FROM asset_tag_map atm WHERE atm.serial_number = d.serial)
               AND d.status = 'Active'
             ORDER BY d.hostname"
        );
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                fputcsv($out, [$r['id'], $r['hostname'], $r['serial'], $r['model'], $r['location'], $r['last_seen']]);
            }
        }
        break;

    // ------------------------------------------------------------------
    case 'missing_monitor_asset_tags':
        fputcsv($out, ['Note']);
        fputcsv($out, ['Monitor asset tag data is not yet tracked in this system.']);
        break;

    // ------------------------------------------------------------------
    case 'os_report':
        $os = isset($_GET['os']) ? trim($_GET['os']) : '';
        fputcsv($out, ['Hostname', 'Serial', 'OS Name', 'OS Build', 'UBR', 'Location', 'Last Seen']);
        if ($os !== '') {
            $like = '%' . like_escape($os) . '%';
            $stmt = $mysqli->prepare(
                "SELECT hostname, serial, os_name, os_build, os_ubr, location, last_seen
                 FROM devices WHERE os_name LIKE ? AND status = 'Active' ORDER BY hostname"
            );
            $stmt->bind_param("s", $like);
        } else {
            $stmt = $mysqli->prepare(
                "SELECT hostname, serial, os_name, os_build, os_ubr, location, last_seen
                 FROM devices WHERE status = 'Active' ORDER BY hostname"
            );
        }
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            fputcsv($out, [$r['hostname'], $r['serial'], $r['os_name'], $r['os_build'], $r['os_ubr'], $r['location'], $r['last_seen']]);
        }
        break;

    // ------------------------------------------------------------------
    case 'ubr_report':
        fputcsv($out, ['OS Name', 'OS Build', 'UBR', 'Count']);
        $res = $mysqli->query(
            "SELECT os_name, os_build, os_ubr, COUNT(*) AS cnt
             FROM devices WHERE status = 'Active'
             GROUP BY os_name, os_build, os_ubr
             ORDER BY os_name, os_build, os_ubr"
        );
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                fputcsv($out, [$r['os_name'], $r['os_build'], $r['os_ubr'], $r['cnt']]);
            }
        }
        break;

    // ------------------------------------------------------------------
    case 'manufacturer_report':
        $mfr = isset($_GET['manufacturer']) ? trim($_GET['manufacturer']) : '';
        fputcsv($out, ['Hostname', 'Serial', 'Manufacturer', 'Model', 'Chassis', 'Location', 'Last Seen']);
        if ($mfr !== '') {
            $like = '%' . like_escape($mfr) . '%';
            $stmt = $mysqli->prepare(
                "SELECT hostname, serial, manufacturer, model, chassis_type, location, last_seen
                 FROM devices WHERE manufacturer LIKE ? AND status = 'Active' ORDER BY hostname"
            );
            $stmt->bind_param("s", $like);
        } else {
            $stmt = $mysqli->prepare(
                "SELECT hostname, serial, manufacturer, model, chassis_type, location, last_seen
                 FROM devices WHERE status = 'Active' ORDER BY manufacturer, hostname"
            );
        }
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            fputcsv($out, [$r['hostname'], $r['serial'], $r['manufacturer'], $r['model'], $r['chassis_type'], $r['location'], $r['last_seen']]);
        }
        break;

    // ------------------------------------------------------------------
    case 'model_report':
        $model = isset($_GET['model']) ? trim($_GET['model']) : '';
        fputcsv($out, ['Hostname', 'Serial', 'Model', 'Manufacturer', 'Location', 'Last Seen']);
        if ($model !== '') {
            $like = '%' . like_escape($model) . '%';
            $stmt = $mysqli->prepare(
                "SELECT hostname, serial, model, manufacturer, location, last_seen
                 FROM devices WHERE model LIKE ? AND status = 'Active' ORDER BY hostname"
            );
            $stmt->bind_param("s", $like);
        } else {
            $stmt = $mysqli->prepare(
                "SELECT hostname, serial, model, manufacturer, location, last_seen
                 FROM devices WHERE status = 'Active' ORDER BY model, hostname"
            );
        }
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            fputcsv($out, [$r['hostname'], $r['serial'], $r['model'], $r['manufacturer'], $r['location'], $r['last_seen']]);
        }
        break;
}

fclose($out);
exit;
