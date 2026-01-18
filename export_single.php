<?php
require_once "config.php";

// 1. Get the ID and sanitize it
$device_id = isset($_GET['device_id']) ? intval($_GET['device_id']) : 0;

// 2. Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
if ($device_id > 0) {
    header('Content-Disposition: attachment; filename=Device_Detail_ID_' . $device_id . '.csv');
} else {
    header('Content-Disposition: attachment; filename=Full_Inventory_Export.csv');
}

// 3. Open output stream
$output = fopen('php://output', 'w');

if ($device_id > 0) {
    /* =====================================================
       SINGLE DEVICE EXPORT (Detailed)
       ===================================================== */
    $stmt = $mysqli->prepare("SELECT * FROM devices WHERE id = ?");
    $stmt->bind_param("i", $device_id);
    $stmt->execute();
    $d = $stmt->get_result()->fetch_assoc();

    if ($d) {
        fputcsv($output, ['DEVICE PROPERTY REPORT']);
        fputcsv($output, ['Generated:', date('Y-m-d H:i:s')]);
        fputcsv($output, []); // Spacer

        // Main Properties
        fputcsv($output, ['Property', 'Value']);
        fputcsv($output, ['Hostname', $d['hostname']]);
        fputcsv($output, ['Serial Number', $d['serial']]);
        fputcsv($output, ['Manufacturer', $d['manufacturer']]);
        fputcsv($output, ['Model', $d['model']]);
        fputcsv($output, ['CPU', $d['cpu']]);
        fputcsv($output, ['RAM (GB)', $d['ram_gb']]);
        fputcsv($output, ['Disk (GB)', $d['disk_gb']]);
        fputcsv($output, ['OS Name', $d['os_name']]);
        fputcsv($output, ['OS Build', $d['os_build']]);
        fputcsv($output, ['Last Seen', $d['last_seen']]);
        fputcsv($output, []);

        // Software Inventory
        fputcsv($output, ['INSTALLED SOFTWARE INVENTORY']);
        fputcsv($output, ['Software Name', 'Version']);
        $soft = $mysqli->query("SELECT software_name, version FROM installed_software WHERE device_id = $device_id ORDER BY software_name ASC");
        while ($s = $soft->fetch_assoc()) {
            fputcsv($output, [$s['software_name'], $s['version']]);
        }
    }
} else {
    /* =====================================================
       FULL INVENTORY EXPORT (Summary)
       ===================================================== */
    fputcsv($output, ['Hostname', 'Serial', 'Manufacturer', 'Model', 'Location', 'Last Seen']);
    $res = $mysqli->query("SELECT hostname, serial, manufacturer, model, location, last_seen FROM devices ORDER BY hostname ASC");
    while ($row = $res->fetch_assoc()) {
        fputcsv($output, $row);
    }
}

fclose($output);
exit;
