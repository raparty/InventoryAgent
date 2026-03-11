<?php
require_once "config.php";
$pageTitle = "Device History Comparison";
include "header_new.php"; // 3) Updated to header_new.php

$mysqli->set_charset('utf8mb4');

/* 1. AUTO-POPULATE / SEARCH LOGIC */
if (!isset($_GET['device_id'])) {
    ?>
    <div class="p-4">
        <div class="card shadow-sm border" style="max-width: 600px; margin: auto;">
            <div class="card-header bg-light fw-bold">Find Device for History Comparison</div>
            <div class="card-body">
                <div class="position-relative">
                    <input type="text" id="hostAuto" class="form-control form-control-sm" 
                           placeholder="Type Hostname or Serial..." autocomplete="off">
                    <div id="resultsDropdown" class="list-group position-absolute w-100 shadow-lg" style="z-index:1000; display:none;"></div>
                </div>
                <p class="text-muted small mt-2">Type at least 2 characters to see suggestions.</p>
            </div>
        </div>
    </div>

    <script>
    // 2) FAST SEARCH LOGIC: Client-side filtering of master list
    const searchInput = document.getElementById('hostAuto');
    const dropdown = document.getElementById('resultsDropdown');
    
    searchInput.addEventListener('input', async (e) => {
        const val = e.target.value.trim();
        if (val.length < 2) { dropdown.style.display = 'none'; return; }

        // Fetch matches via a dedicated fast-lookup endpoint or current page
        const response = await fetch(`compare_lookup.php?q=${encodeURIComponent(val)}`);
        const data = await response.json();

        if (data.length > 0) {
            dropdown.innerHTML = data.map(d => `
                <a href="compare_new.php?device_id=${d.id}" class="list-group-item list-group-item-action py-2 small">
                    <strong>${d.hostname}</strong> <span class="text-muted float-end">${d.serial}</span>
                </a>
            `).join('');
            dropdown.style.display = 'block';
        } else {
            dropdown.style.display = 'none';
        }
    });
    </script>
    <?php include "footer.php"; exit;
}

/* 2. OPTIMIZED SQL DATA FETCHING */
$device_id = intval($_GET['device_id']);

// 2) FAST QUERY: Using specific columns rather than SELECT * with prepared statement
$stmt = $mysqli->prepare("SELECT id, hostname, serial, location FROM devices WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $device_id);
$stmt->execute();
$dev = $stmt->get_result()->fetch_assoc();

if (!$dev) {
    echo "<div class='alert alert-danger m-4'>Error: Device not found.</div>";
    include "footer.php"; exit;
}

// 2) FAST QUERY: Fetching only IDs and timestamps for the selector with prepared statement
$stmt2 = $mysqli->prepare("SELECT id, snapshot_time FROM device_history WHERE device_id = ? ORDER BY snapshot_time DESC");
$stmt2->bind_param("i", $device_id);
$stmt2->execute();
$snapshots = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);

if (count($snapshots) < 2) {
    echo "<div class='alert alert-info m-4'><strong>Insufficient History:</strong> Two snapshots required to compare.</div>";
    include "footer.php"; exit;
}

$idA = intval($_GET['a'] ?? $snapshots[1]['id']);
$idB = intval($_GET['b'] ?? $snapshots[0]['id']);

$snapshotService = new \InventoryAgent\SnapshotService($mysqli);
$dataA = $snapshotService->getSnapshot($idA, $device_id);
$dataB = $snapshotService->getSnapshot($idB, $device_id);
$keys = array_unique(array_merge(array_keys($dataA), array_keys($dataB)));
sort($keys);
?>

<div class="comp-wrapper">
    <div class="info-strip shadow-sm d-flex justify-content-between">
        <div>
            <span class="text-muted small uppercase fw-bold">Comparing History For:</span>
            <h5 class="mb-0 fw-bold"><?= htmlspecialchars($dev['hostname']) ?> <small class="text-muted">(<?= htmlspecialchars($dev['serial']) ?>)</small></h5>
        </div>
        <a href="compare_new.php" class="btn btn-sm btn-outline-secondary">Switch Device</a>
    </div>

    <form method="get" class="selector-card d-flex gap-3 align-items-end">
        <input type="hidden" name="device_id" value="<?= $device_id ?>">
        <div style="flex:1">
            <label class="small fw-bold text-muted">SNAPSHOT A (Older)</label>
            <select name="a" class="form-select form-select-sm">
                <?php foreach($snapshots as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= $idA == $s['id'] ? 'selected' : '' ?>><?= $s['snapshot_time'] ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="flex:1">
            <label class="small fw-bold text-muted">SNAPSHOT B (Newer)</label>
            <select name="b" class="form-select form-select-sm">
                <?php foreach($snapshots as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= $idB == $s['id'] ? 'selected' : '' ?>><?= $s['snapshot_time'] ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-sm btn-primary">Compare</button>
    </form>

    <div class="card shadow-sm">
        <table class="diff-table">
            <thead>
                <tr><th>Field</th><th>Snapshot A</th><th>Snapshot B</th></tr>
            </thead>
            <tbody>
                <?php 
                $changeCount = 0;
                $ignore = ['last_seen', 'uptime', 'collection_time', 'snapshot_time'];
                foreach($keys as $k): 
                    if(in_array($k, $ignore)) continue;
                    $valA = json_encode($dataA[$k] ?? '', JSON_PRETTY_PRINT);
                    $valB = json_encode($dataB[$k] ?? '', JSON_PRETTY_PRINT);
                    if($valA !== $valB): $changeCount++; ?>
                        <tr class="changed-row">
                            <td class="fw-bold"><?= htmlspecialchars($k) ?></td>
                            <td><pre><?= htmlspecialchars($valA) ?></pre></td>
                            <td><pre><?= htmlspecialchars($valB) ?></pre></td>
                        </tr>
                    <?php endif; 
                endforeach; ?>
                <?php if($changeCount === 0): ?>
                    <tr><td colspan="3" class="text-center py-4 text-muted">No configuration differences found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include "footer.php"; ?>
