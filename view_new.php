<?php
require_once "config.php";
$pageTitle = "Device Details";
include "header_new.php";

$mysqli->set_charset('utf8mb4');

$device_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($device_id <= 0) {
    echo "<div class='alert alert-danger m-4'>Invalid or missing device ID.</div>";
    include "footer.php";
    exit;
}

// Fetch core device record
$stmt = $mysqli->prepare(
    "SELECT d.*, atm.asset_tag
     FROM devices d
     LEFT JOIN asset_tag_map atm ON atm.serial_number = d.serial
     WHERE d.id = ? LIMIT 1"
);
$stmt->bind_param("i", $device_id);
$stmt->execute();
$dev = $stmt->get_result()->fetch_assoc();

if (!$dev) {
    echo "<div class='alert alert-danger m-4'>Device not found.</div>";
    include "footer.php";
    exit;
}

// Fetch installed software
$sw_stmt = $mysqli->prepare(
    "SELECT software_name, version, install_date
     FROM installed_software
     WHERE device_id = ?
     ORDER BY software_name ASC"
);
$sw_stmt->bind_param("i", $device_id);
$sw_stmt->execute();
$software = $sw_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch latest patches
$patch_stmt = $mysqli->prepare(
    "SELECT kb_number, install_date
     FROM patch_status
     WHERE device_id = ?
     ORDER BY install_date DESC
     LIMIT 20"
);
$patch_stmt->bind_param("i", $device_id);
$patch_stmt->execute();
$patches = $patch_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch recent change log
$log_stmt = $mysqli->prepare(
    "SELECT change_type, old_value, new_value, logged_at
     FROM device_change_logs
     WHERE device_id = ?
     ORDER BY logged_at DESC
     LIMIT 25"
);
$log_stmt->bind_param("i", $device_id);
$log_stmt->execute();
$changes = $log_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$statusBadge = match($dev['status'] ?? 'Active') {
    'Active'   => 'bg-success',
    'In-Store' => 'bg-warning text-dark',
    'Scrapped' => 'bg-secondary',
    default    => 'bg-light text-dark',
};
?>

<div class="p-3 bg-light border-bottom d-flex justify-content-between align-items-center">
    <div>
        <h5 class="mb-0 fw-bold"><?= htmlspecialchars($dev['hostname']) ?></h5>
        <small class="text-muted">Serial: <?= htmlspecialchars($dev['serial']) ?>
            &nbsp;|&nbsp; <span class="badge <?= $statusBadge ?>"><?= htmlspecialchars($dev['status'] ?? 'Active') ?></span>
        </small>
    </div>
    <div class="d-flex gap-2">
        <a href="compare_new.php?device_id=<?= $device_id ?>" class="btn btn-sm btn-outline-secondary">View History</a>
        <a href="javascript:history.back()" class="btn btn-sm btn-outline-primary">← Back</a>
    </div>
</div>

<div class="container-fluid p-4">

    <!-- Device Info Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card shadow-sm h-100">
                <div class="card-header fw-bold bg-light">🖥 Hardware</div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <tbody>
                            <tr><th class="text-muted" style="width:40%">Hostname</th><td><?= htmlspecialchars($dev['hostname']) ?></td></tr>
                            <tr><th class="text-muted">Serial Number</th><td style="font-family:monospace;"><?= htmlspecialchars($dev['serial']) ?></td></tr>
                            <tr><th class="text-muted">Asset Tag</th><td><?= htmlspecialchars($dev['asset_tag'] ?? 'N/A') ?></td></tr>
                            <tr><th class="text-muted">Model</th><td><?= htmlspecialchars($dev['model'] ?? 'N/A') ?></td></tr>
                            <tr><th class="text-muted">Manufacturer</th><td><?= htmlspecialchars($dev['manufacturer'] ?? 'N/A') ?></td></tr>
                            <tr><th class="text-muted">Chassis Type</th><td><?= htmlspecialchars($dev['chassis_type'] ?? 'N/A') ?></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card shadow-sm h-100">
                <div class="card-header fw-bold bg-light">📦 Software & Location</div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <tbody>
                            <tr><th class="text-muted" style="width:40%">OS Name</th><td><?= htmlspecialchars($dev['os_name'] ?? 'N/A') ?></td></tr>
                            <tr><th class="text-muted">OS Build</th><td><?= htmlspecialchars($dev['os_build'] ?? 'N/A') ?></td></tr>
                            <tr><th class="text-muted">UBR</th><td><?= htmlspecialchars($dev['os_ubr'] ?? 'N/A') ?></td></tr>
                            <tr><th class="text-muted">Location</th><td><?= htmlspecialchars($dev['location'] ?? 'N/A') ?></td></tr>
                            <tr><th class="text-muted">Site</th><td><?= htmlspecialchars($dev['location_agent'] ?? 'N/A') ?></td></tr>
                            <tr><th class="text-muted">Last Seen</th><td><?= htmlspecialchars($dev['last_seen'] ?? 'N/A') ?></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Installed Software -->
    <div class="card shadow-sm mb-4">
        <div class="card-header fw-bold bg-light d-flex justify-content-between align-items-center">
            <span>📋 Installed Software (<?= count($software) ?>)</span>
            <input type="text" id="swSearch" class="form-control form-control-sm" style="width:200px" placeholder="Search software...">
        </div>
        <div class="card-body p-0" style="max-height:350px; overflow-y:auto;">
            <table class="table table-sm mb-0" id="swTable">
                <thead class="bg-light sticky-top">
                    <tr><th>Name</th><th>Version</th><th>Install Date</th></tr>
                </thead>
                <tbody>
                <?php if (empty($software)): ?>
                    <tr><td colspan="3" class="text-center py-3 text-muted">No software records found.</td></tr>
                <?php else: ?>
                    <?php foreach ($software as $sw): ?>
                    <tr>
                        <td><?= htmlspecialchars($sw['software_name']) ?></td>
                        <td class="text-muted small"><?= htmlspecialchars($sw['version'] ?? '') ?></td>
                        <td class="text-muted small"><?= htmlspecialchars($sw['install_date'] ?? '') ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="row g-3">
        <!-- Recent Patches -->
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header fw-bold bg-light">🔒 Recent Patches</div>
                <div class="card-body p-0" style="max-height:280px; overflow-y:auto;">
                    <table class="table table-sm mb-0">
                        <thead class="bg-light"><tr><th>KB Number</th><th>Install Date</th></tr></thead>
                        <tbody>
                        <?php if (empty($patches)): ?>
                            <tr><td colspan="2" class="text-center py-3 text-muted">No patch records found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($patches as $p): ?>
                            <tr>
                                <td style="font-family:monospace;"><?= htmlspecialchars($p['kb_number']) ?></td>
                                <td class="text-muted small"><?= htmlspecialchars($p['install_date'] ?? '') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <!-- Change Log -->
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header fw-bold bg-light">📝 Hardware Change Log</div>
                <div class="card-body p-0" style="max-height:280px; overflow-y:auto;">
                    <table class="table table-sm mb-0">
                        <thead class="bg-light"><tr><th>Change</th><th>Old → New</th><th>Date</th></tr></thead>
                        <tbody>
                        <?php if (empty($changes)): ?>
                            <tr><td colspan="3" class="text-center py-3 text-muted">No change records found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($changes as $c): ?>
                            <tr>
                                <td class="small fw-bold"><?= htmlspecialchars($c['change_type']) ?></td>
                                <td class="small text-muted">
                                    <?= htmlspecialchars($c['old_value'] ?? '') ?>
                                    → <?= htmlspecialchars($c['new_value'] ?? '') ?>
                                </td>
                                <td class="small text-muted"><?= htmlspecialchars($c['logged_at']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
document.getElementById('swSearch').addEventListener('input', function () {
    const q = this.value.toLowerCase();
    document.querySelectorAll('#swTable tbody tr').forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
});
</script>

<?php include "footer.php"; ?>
