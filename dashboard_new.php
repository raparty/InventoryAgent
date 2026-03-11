<?php
require_once "config.php";
$pageTitle = "Dashboard";
include "header_new.php"; 

$mysqli->set_charset('utf8mb4');

// -----------------------------------------------------------------------
// Query 1: Device counts (total, offline, type, OS, location) — 1 query
// -----------------------------------------------------------------------
$statsRow = $mysqli->query("
    SELECT
        COUNT(*)                                                          AS totalDevices,
        SUM(CASE WHEN DATEDIFF(CURDATE(), last_seen) > 7 THEN 1 ELSE 0 END) AS offlineDevices,
        SUM(CASE WHEN chassis_type LIKE '%Server%'  THEN 1 ELSE 0 END)  AS servers,
        SUM(CASE WHEN chassis_type LIKE '%Desktop%' THEN 1 ELSE 0 END)  AS desktops,
        SUM(CASE WHEN chassis_type LIKE '%Laptop%'  THEN 1 ELSE 0 END)  AS laptops,
        SUM(CASE WHEN os_name LIKE '%Windows 10%'   THEN 1 ELSE 0 END)  AS win10,
        SUM(CASE WHEN os_name LIKE '%Windows 11%'   THEN 1 ELSE 0 END)  AS win11,
        SUM(CASE WHEN location LIKE '%HYDW%'         THEN 1 ELSE 0 END)  AS locHYDW,
        SUM(CASE WHEN location LIKE '%HYDE%'         THEN 1 ELSE 0 END)  AS locHYDE,
        SUM(CASE WHEN (location IS NULL OR location = '' OR location = 'UNKNOWN') THEN 1 ELSE 0 END) AS locUNK,
        SUM(CASE WHEN last_seen < NOW() - INTERVAL 30 DAY THEN 1 ELSE 0 END) AS not_responding
    FROM devices
    WHERE status = 'Active'
")->fetch_assoc();

$totalDevices  = $statsRow['totalDevices']   ?? 0;
$offlineDevices = $statsRow['offlineDevices'] ?? 0;
$servers       = $statsRow['servers']         ?? 0;
$desktops      = $statsRow['desktops']        ?? 0;
$laptops       = $statsRow['laptops']         ?? 0;
$win10         = $statsRow['win10']           ?? 0;
$win11         = $statsRow['win11']           ?? 0;
$locHYDW       = $statsRow['locHYDW']         ?? 0;
$locHYDE       = $statsRow['locHYDE']         ?? 0;
$locUNK        = $statsRow['locUNK']          ?? 0;
$not_responding = $statsRow['not_responding'] ?? 0;

// -----------------------------------------------------------------------
// Query 2: Patch compliance for recently-seen devices — 1 query
// -----------------------------------------------------------------------
$patchRow = $mysqli->query("
    SELECT
        SUM(CASE WHEN max_install IS NOT NULL AND DATEDIFF(CURDATE(), max_install) <= 45 THEN 1 ELSE 0 END) AS up_to_date,
        SUM(CASE WHEN max_install IS NULL     OR  DATEDIFF(CURDATE(), max_install) >  45 THEN 1 ELSE 0 END) AS outdated_total
    FROM (
        SELECT d.id, MAX(p.install_date) AS max_install
        FROM devices d
        LEFT JOIN patch_status p ON d.id = p.device_id
        WHERE d.status = 'Active'
          AND d.last_seen > NOW() - INTERVAL 30 DAY
        GROUP BY d.id
    ) AS patch_summary
")->fetch_assoc();

$up_to_date    = $patchRow['up_to_date']    ?? 0;
$outdated_total = $patchRow['outdated_total'] ?? 0;
?>

<style>
.dash-container { padding: 20px; }
.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px; }
.stat-card { background: #fff; border: 1px solid #d2d0ce; border-radius: 4px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
.stat-card h3 { font-size: 32px; font-weight: 700; color: #005a9e; margin: 0; }
.stat-card p { font-size: 12px; color: #605e5c; margin: 5px 0 0 0; text-transform: uppercase; font-weight: 600; }
.chart-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; }
.chart-card { background: #fff; border: 1px solid #d2d0ce; border-radius: 4px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
.chart-title { font-size: 14px; font-weight: 700; color: #2b3b4c; margin-bottom: 15px; }
</style>

<div class="dash-container">
    <h4 class="mb-4 fw-bold">Enterprise Dashboard</h4>
    
    <div class="stats-grid">
        <div class="stat-card">
            <h3><?= $totalDevices ?></h3>
            <p>Total Active Devices</p>
        </div>
        <div class="stat-card">
            <h3 class="text-danger"><?= $offlineDevices ?></h3>
            <p>Offline Devices (>7 Days)</p>
        </div>
        <div class="stat-card">
            <h3 class="text-success"><?= $up_to_date ?></h3>
            <p>Patch Compliant</p>
        </div>
        <div class="stat-card">
            <h3 class="text-warning"><?= $outdated_total ?></h3>
            <p>Patch Outdated</p>
        </div>
    </div>
    
    <div class="chart-grid">
        <div class="chart-card">
            <div class="chart-title">Device Type Distribution</div>
            <canvas id="chartDeviceType"></canvas>
        </div>
        <div class="chart-card">
            <div class="chart-title">OS Distribution</div>
            <canvas id="chartOS"></canvas>
        </div>
        <div class="chart-card">
            <div class="chart-title">Location Distribution</div>
            <canvas id="chartLocationShare"></canvas>
        </div>
        <div class="chart-card">
            <div class="chart-title">Patch Compliance Status</div>
            <canvas id="chartPatchPie"></canvas>
        </div>
    </div>
    
    <div class="chart-grid mt-4">
        <div class="chart-card">
            <div class="chart-title">Patch Status Overview</div>
            <canvas id="chartPatchBar"></canvas>
        </div>
    </div>
</div>

<script>
// ---------------------------
//  DEVICE TYPE DISTRIBUTION
// ---------------------------
new Chart(document.getElementById('chartDeviceType'), {
    type: 'pie',
    data: {
        labels: ['Server', 'Desktop', 'Laptop'],
        datasets: [{
            data: [<?= $servers ?>, <?= $desktops ?>, <?= $laptops ?>],
            backgroundColor: ['#3b82f6', '#f06292', '#ffa726'],
            borderWidth: 0
        }]
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
});

// ---------------------------
//  OS DISTRIBUTION
// ---------------------------
new Chart(document.getElementById('chartOS'), {
    type: 'doughnut',
    data: {
        labels: ['Windows 10', 'Windows 11'],
        datasets: [{
            data: [<?= $win10 ?>, <?= $win11 ?>],
            backgroundColor: ['#4e79a7', '#f28e2b'],
            borderWidth: 0
        }]
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
});

// ---------------------------
//  LOCATION SHARE PIE
// ---------------------------
new Chart(document.getElementById('chartLocationShare'), {
    type: 'pie',
    data: {
        labels: ['HYDW', 'HYDE', 'UNKNOWN'],
        datasets: [{
            data: [<?= $locHYDW ?>, <?= $locHYDE ?>, <?= $locUNK ?>],
            backgroundColor: ['#66bb6a', '#42a5f5', '#9e9e9e'],
            borderWidth: 0
        }]
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
});

// ---------------------------
//  PATCH COMPLIANCE BREAKDOWN (DONUT)
// ---------------------------
new Chart(document.getElementById('chartPatchPie'), {
    type: 'doughnut',
    data: {
        labels: ['Up-to-date', 'Outdated', 'No Data'],
        datasets: [{
            data: [<?= $up_to_date ?>, <?= $outdated_total ?>, <?= $not_responding ?>],
            backgroundColor: ['#3bb77e','#f6a623','#9aa0a6'],
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        cutout: '65%',
        plugins: { legend: { position: 'bottom' } }
    }
});

// ---------------------------
//  PATCH STATUS BAR
// ---------------------------
new Chart(document.getElementById('chartPatchBar'), {
    type: 'bar',
    data: {
        labels: ['Up-to-date', 'Outdated', 'No Data'],
        datasets: [{
            label: 'Systems',
            data: [<?= $up_to_date ?>, <?= $outdated_total ?>, <?= $not_responding ?>],
            backgroundColor: ['#3bb77e','#f6a623','#9aa0a6'],
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, ticks: { stepSize: 50 } }
        }
    }
});
</script>

<?php include "footer.php"; ?>
