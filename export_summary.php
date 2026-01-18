<?php
// export_summary.php - exports summary CSV with clean headers
include 'config.php';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=inventory_summary.csv');

$out = fopen('php://output', 'w');

// header row (clean readable names)
fputcsv($out, ['Device ID','Hostname','Serial Number','Model','Manufacturer','OS Name','OS Build','OS UBR']);

// query
$q = "SELECT id, hostname, serial, model, manufacturer, os_name, os_build, os_ubr FROM devices ORDER BY hostname";
$res = $mysqli->query($q);

while($r = $res->fetch_assoc()){
    fputcsv($out, [
        $r['id'],
        $r['hostname'],
        $r['serial'],
        $r['model'],
        $r['manufacturer'],
        $r['os_name'],
        $r['os_build'],
        $r['os_ubr']
    ]);
}
fclose($out);
exit;
