<?php
require 'config.php';

header("Content-Type: text/csv");
header("Content-Disposition: attachment; filename=\"Full Inventory.csv\"");

$out = fopen("php://output", "w");

// CSV Header
fputcsv($out, [
    "Hostname",
    "Serial Number",
    "Asset Tag",
    "Model",
    "Manufacturer",
    "OS Name",
    "OS Build",
    "OS UBR"
]);

// Query devices + computer asset tag
$devices = $mysqli->query("
    SELECT
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

if (!$devices) {
    fputcsv($out, ["Error: failed to retrieve device data."]);
    fclose($out);
    exit;
}

while ($d = $devices->fetch_assoc()) {
    fputcsv($out, [
        $d["hostname"],
        $d["serial"],
        $d["computer_asset_tag"] ?: "",
        $d["model"],
        $d["manufacturer"],
        $d["os_name"],
        $d["os_build"],
        $d["os_ubr"],
    ]);
}

fclose($out);
exit;
?>
