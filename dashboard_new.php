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
        COUNT(*)                                                               AS totalDevices,
        SUM(CASE WHEN DATEDIFF(CURDATE(), last_seen) > 7 THEN 1 ELSE 0 END)  AS offlineDevices,
        /* yesterday sim: DATEDIFF(CURDATE()-1, last_seen) > 7 ≡ DATEDIFF(CURDATE(), last_seen) > 8 */
        SUM(CASE WHEN DATEDIFF(CURDATE(), last_seen) > 8 THEN 1 ELSE 0 END)  AS offlineDevicesYest,
        SUM(CASE WHEN chassis_type LIKE '%Server%'  THEN 1 ELSE 0 END)       AS servers,
        SUM(CASE WHEN chassis_type LIKE '%Desktop%' THEN 1 ELSE 0 END)       AS desktops,
        SUM(CASE WHEN chassis_type LIKE '%Laptop%'  THEN 1 ELSE 0 END)       AS laptops,
        SUM(CASE WHEN os_name LIKE '%Windows 10%'   THEN 1 ELSE 0 END)       AS win10,
        SUM(CASE WHEN os_name LIKE '%Windows 11%'   THEN 1 ELSE 0 END)       AS win11,
        SUM(CASE WHEN location LIKE '%HYDW%'         THEN 1 ELSE 0 END)      AS locHYDW,
        SUM(CASE WHEN location LIKE '%HYDE%'         THEN 1 ELSE 0 END)      AS locHYDE,
        SUM(CASE WHEN (location IS NULL OR location = '' OR location = 'UNKNOWN') THEN 1 ELSE 0 END) AS locUNK,
        SUM(CASE WHEN last_seen < NOW() - INTERVAL 30 DAY THEN 1 ELSE 0 END) AS not_responding
    FROM devices
    WHERE status = 'Active'
")->fetch_assoc();

$totalDevices       = $statsRow['totalDevices']       ?? 0;
$offlineDevices     = $statsRow['offlineDevices']     ?? 0;
$offlineDevicesYest = $statsRow['offlineDevicesYest'] ?? 0;
$servers            = $statsRow['servers']            ?? 0;
$desktops           = $statsRow['desktops']           ?? 0;
$laptops            = $statsRow['laptops']            ?? 0;
$win10              = $statsRow['win10']              ?? 0;
$win11              = $statsRow['win11']              ?? 0;
$locHYDW            = $statsRow['locHYDW']            ?? 0;
$locHYDE            = $statsRow['locHYDE']            ?? 0;
$locUNK             = $statsRow['locUNK']             ?? 0;
$not_responding     = $statsRow['not_responding']     ?? 0;

// -----------------------------------------------------------------------
// Query 2: Patch compliance for recently-seen devices — 1 query
// -----------------------------------------------------------------------
$patchRow = $mysqli->query("
    SELECT
        SUM(CASE WHEN max_install IS NOT NULL AND DATEDIFF(CURDATE(), max_install) <= 45 THEN 1 ELSE 0 END) AS up_to_date,
        SUM(CASE WHEN max_install IS NULL     OR  DATEDIFF(CURDATE(), max_install) >  45 THEN 1 ELSE 0 END) AS outdated_total,
        /* yesterday sim: apply same thresholds with CURDATE()-1, i.e. shift boundaries by 1 day */
        SUM(CASE WHEN max_install IS NOT NULL AND DATEDIFF(CURDATE(), max_install) <= 46 THEN 1 ELSE 0 END) AS up_to_date_yest,
        SUM(CASE WHEN max_install IS NULL     OR  DATEDIFF(CURDATE(), max_install) >  46 THEN 1 ELSE 0 END) AS outdated_yest
    FROM (
        SELECT d.id, MAX(p.install_date) AS max_install
        FROM devices d
        LEFT JOIN patch_status p ON d.id = p.device_id
        WHERE d.status = 'Active'
          AND d.last_seen > NOW() - INTERVAL 30 DAY
        GROUP BY d.id
    ) AS patch_summary
")->fetch_assoc();

$up_to_date      = $patchRow['up_to_date']      ?? 0;
$outdated_total  = $patchRow['outdated_total']  ?? 0;
$up_to_date_yest = $patchRow['up_to_date_yest'] ?? 0;
$outdated_yest   = $patchRow['outdated_yest']   ?? 0;

// -----------------------------------------------------------------------
// Trend deltas (positive = count increased since yesterday)
// -----------------------------------------------------------------------
$offlineDelta  = $offlineDevices - $offlineDevicesYest; // positive = worse
$outdatedDelta = $outdated_total - $outdated_yest;      // positive = worse
$upToDateDelta = $up_to_date    - $up_to_date_yest;    // positive = better

$refreshTime = date('Y-m-d H:i:s');
?>

<div class="dash-container">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h4 class="fw-bold mb-0">Enterprise Dashboard</h4>
        <div class="dash-refresh-bar">
            <span>Last refreshed: <?= htmlspecialchars($refreshTime) ?></span>
            <a href="dashboard_new.php" class="btn btn-sm btn-outline-secondary ms-3">↻ Refresh</a>
        </div>
    </div>
    
    <div class="stats-grid mb-4">
        <div class="stat-card">
            <h3><?= $totalDevices ?></h3>
            <p>Total Active Devices</p>
        </div>
        <a href="offline_devices.php" class="stat-card stat-card-link">
            <h3 class="text-danger"><?= $offlineDevices ?></h3>
            <p>Offline Devices (&gt;7 Days)</p>
            <?php if ($offlineDelta > 0): ?>
                <span class="stat-trend stat-trend-worse">▲ <?= $offlineDelta ?> vs yesterday</span>
            <?php elseif ($offlineDelta < 0): ?>
                <span class="stat-trend stat-trend-better">▼ <?= abs($offlineDelta) ?> vs yesterday</span>
            <?php else: ?>
                <span class="stat-trend stat-trend-neutral">— same as yesterday</span>
            <?php endif; ?>
        </a>
        <div class="stat-card">
            <h3 class="text-success"><?= $up_to_date ?></h3>
            <p>Patch Compliant</p>
            <?php if ($upToDateDelta > 0): ?>
                <span class="stat-trend stat-trend-better">▲ <?= $upToDateDelta ?> vs yesterday</span>
            <?php elseif ($upToDateDelta < 0): ?>
                <span class="stat-trend stat-trend-worse">▼ <?= abs($upToDateDelta) ?> vs yesterday</span>
            <?php else: ?>
                <span class="stat-trend stat-trend-neutral">— same as yesterday</span>
            <?php endif; ?>
        </div>
        <a href="patch-compliance_new.php?status=Outdated" class="stat-card stat-card-link">
            <h3 class="text-warning"><?= $outdated_total ?></h3>
            <p>Patch Outdated</p>
            <?php if ($outdatedDelta > 0): ?>
                <span class="stat-trend stat-trend-worse">▲ <?= $outdatedDelta ?> vs yesterday</span>
            <?php elseif ($outdatedDelta < 0): ?>
                <span class="stat-trend stat-trend-better">▼ <?= abs($outdatedDelta) ?> vs yesterday</span>
            <?php else: ?>
                <span class="stat-trend stat-trend-neutral">— same as yesterday</span>
            <?php endif; ?>
        </a>
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
