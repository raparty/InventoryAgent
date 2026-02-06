<?php
require_once "config.php";
$pageTitle = "Dashboard";
include "header_new.php"; 

$mysqli->set_charset('utf8mb4');

// Fetch dashboard statistics
$totalDevices = $mysqli->query("SELECT COUNT(*) as cnt FROM devices WHERE status = 'Active'")->fetch_assoc()['cnt'] ?? 0;
$offlineDevices = $mysqli->query("SELECT COUNT(*) as cnt FROM devices WHERE DATEDIFF(CURDATE(), last_seen) > 7 AND status = 'Active'")->fetch_assoc()['cnt'] ?? 0;

// Device type distribution
$servers = $mysqli->query("SELECT COUNT(*) as cnt FROM devices WHERE chassis_type LIKE '%Server%' AND status = 'Active'")->fetch_assoc()['cnt'] ?? 0;
$desktops = $mysqli->query("SELECT COUNT(*) as cnt FROM devices WHERE chassis_type LIKE '%Desktop%' AND status = 'Active'")->fetch_assoc()['cnt'] ?? 0;
$laptops = $mysqli->query("SELECT COUNT(*) as cnt FROM devices WHERE chassis_type LIKE '%Laptop%' AND status = 'Active'")->fetch_assoc()['cnt'] ?? 0;

// OS distribution
$win10 = $mysqli->query("SELECT COUNT(*) as cnt FROM devices WHERE os_name LIKE '%Windows 10%' AND status = 'Active'")->fetch_assoc()['cnt'] ?? 0;
$win11 = $mysqli->query("SELECT COUNT(*) as cnt FROM devices WHERE os_name LIKE '%Windows 11%' AND status = 'Active'")->fetch_assoc()['cnt'] ?? 0;

// Location distribution
$locHYDW = $mysqli->query("SELECT COUNT(*) as cnt FROM devices WHERE location LIKE '%HYDW%' AND status = 'Active'")->fetch_assoc()['cnt'] ?? 0;
$locHYDE = $mysqli->query("SELECT COUNT(*) as cnt FROM devices WHERE location LIKE '%HYDE%' AND status = 'Active'")->fetch_assoc()['cnt'] ?? 0;
$locUNK = $mysqli->query("SELECT COUNT(*) as cnt FROM devices WHERE (location IS NULL OR location = '' OR location = 'UNKNOWN') AND status = 'Active'")->fetch_assoc()['cnt'] ?? 0;

// Patch compliance stats
$up_to_date = $mysqli->query("
    SELECT COUNT(DISTINCT d.id) as cnt 
    FROM devices d
    LEFT JOIN patch_status p ON d.id = p.device_id
    WHERE d.status = 'Active' 
      AND d.last_seen > NOW() - INTERVAL 30 DAY
      AND DATEDIFF(CURDATE(), MAX(p.install_date)) <= 45
    GROUP BY d.id
")->num_rows ?? 0;

$outdated_total = $mysqli->query("
    SELECT COUNT(DISTINCT d.id) as cnt 
    FROM devices d
    LEFT JOIN patch_status p ON d.id = p.device_id
    WHERE d.status = 'Active' 
      AND d.last_seen > NOW() - INTERVAL 30 DAY
      AND (MAX(p.install_date) IS NULL OR DATEDIFF(CURDATE(), MAX(p.install_date)) > 45)
    GROUP BY d.id
")->num_rows ?? 0;

$not_responding = $mysqli->query("SELECT COUNT(*) as cnt FROM devices WHERE last_seen < NOW() - INTERVAL 30 DAY AND status = 'Active'")->fetch_assoc()['cnt'] ?? 0;
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

<?php include "footer.php"; ?>
