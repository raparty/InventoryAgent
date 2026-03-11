<?php
require 'config.php';

header("Content-Type: text/csv");
header("Content-Disposition: attachment; filename=\"Full Inventory.csv\"");

$out = fopen("php://output", "w");

// CSV Header (ordered as requested)
fputcsv($out, [
    "Hostname",
    "Serial Number",
    "Asset Tag",
    "Model",
    "Manufacturer",
    "OS Name",
    "OS Build",
    "OS UBR",
    "Monitor 1 Model",
    "Monitor 1 Serial",
    "Monitor 1 Asset Tag",
    "Monitor 2 Model",
    "Monitor 2 Serial",
    "Monitor 2 Asset Tag"
]);

// Query devices + computer asset tag
$devices = $mysqli->query("
    SELECT 
        d.id,
        d.hostname,
        d.serial,
        d.model,
        d.manufacturer,
        d.os_name,
        d.os_build,
        d.os_ubr,
        atm.asset_tag AS computer_asset_tag
    FROM devices d
    LEFT JOIN asset_tag_map atm 
        ON atm.serial_number = d.serial
    ORDER BY d.hostname
");

while ($d = $devices->fetch_assoc()) {

    $device_id = (int)$d["id"];

    // Get monitors (max 2) and their asset tags
    $m_stmt = $mysqli->prepare("
        SELECT 
            mo.model AS monitor_model, 
            mo.serial AS monitor_serial,
            mat.asset_tag AS monitor_asset_tag
        FROM monitors mo
        LEFT JOIN monitor_asset_tag_map mat 
            ON mat.monitor_serial = mo.serial
        WHERE mo.device_id = ?
        ORDER BY mo.id
        LIMIT 2
    ");
    $m_stmt->bind_param("i", $device_id);
    $m_stmt->execute();
    $m = $m_stmt->get_result();

    $monitors = [];
    while ($row = $m->fetch_assoc()) {
        $monitors[] = $row;
    }

    // Prepare monitor data (ensure keys exist)
    $m1 = $monitors[0] ?? ["monitor_model"=>"", "monitor_serial"=>"", "monitor_asset_tag"=>""];
    $m2 = $monitors[1] ?? ["monitor_model"=>"", "monitor_serial"=>"", "monitor_asset_tag"=>""];

    // Output row in requested order
    fputcsv($out, [
        $d["hostname"],
        $d["serial"],
        $d["computer_asset_tag"] ?: "",
        $d["model"],
        $d["manufacturer"],
        $d["os_name"],
        $d["os_build"],
        $d["os_ubr"],

        // Monitor 1
        $m1["monitor_model"],
        $m1["monitor_serial"],
        $m1["monitor_asset_tag"],

        // Monitor 2
        $m2["monitor_model"],
        $m2["monitor_serial"],
        $m2["monitor_asset_tag"],
    ]);
}

fclose($out);
exit;
?>
