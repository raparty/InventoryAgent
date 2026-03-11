<?php
require_once "config.php";
$pageTitle = "Duplicate Inventory Audit";
include "header_new.php"; // Using the updated header

$mysqli->set_charset('utf8mb4');

/* 1. Duplicate Hostnames query */
$dupHosts = $mysqli->query("
    SELECT hostname, COUNT(*) AS cnt, GROUP_CONCAT(serial ORDER BY serial SEPARATOR ', ') as serials
    FROM devices
    GROUP BY hostname
    HAVING cnt > 1
    ORDER BY cnt DESC
");

/* 2. Duplicate Asset Tags Query (Desktops) */
$dupAssets = $mysqli->query("
    SELECT 
        atm.asset_tag,
        d.id,
        d.hostname,
        d.serial,
        d.location,
        d.model
    FROM asset_tag_map atm
    JOIN devices d ON atm.serial_number = d.serial
    WHERE atm.asset_tag IN (
        SELECT asset_tag 
        FROM asset_tag_map 
        WHERE asset_tag IS NOT NULL AND TRIM(asset_tag) <> ''
        GROUP BY asset_tag 
        HAVING COUNT(*) > 1
    )
    ORDER BY atm.asset_tag ASC, d.hostname ASC
");
?>

<div class="dup-container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-bold mb-0">Duplicate Records Audit</h4>
        <button onclick="window.print()" class="btn btn-sm btn-outline-secondary">Print Report</button>
    </div>

    <div class="dup-card shadow-sm">
        <div class="dup-card-header">Duplicate Hostnames</div>
        <table class="ent-table">
            <thead>
                <tr>
                    <th style="width: 30%;">Hostname</th>
                    <th style="width: 10%;">Count</th>
                    <th>Associated Serials</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($dupHosts && $dupHosts->num_rows): ?>
                <?php while($r = $dupHosts->fetch_assoc()): ?>
                <tr>
                    <td class="fw-bold"><?= htmlspecialchars($r['hostname']) ?></td>
                    <td><span class="count-badge"><?= (int)$r['cnt'] ?></span></td>
                    <td class="text-muted small"><?= htmlspecialchars($r['serials']) ?></td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="3" class="text-center py-4 text-muted">No duplicate hostnames detected.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="dup-card shadow-sm">
        <div class="dup-card-header">Duplicate Asset Tags (Desktop Hardware)</div>
        <table class="ent-table">
            <thead>
                <tr>
                    <th style="width: 20%;">Asset Tag</th>
                    <th>Hostname</th>
                    <th>Serial Number</th>
                    <th>Location</th>
                    <th>Model</th>
                    <th style="width: 80px;">Action</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $lastTag = null;
            if ($dupAssets && $dupAssets->num_rows):
                while($r = $dupAssets->fetch_assoc()):
                    if ($lastTag !== $r['asset_tag']):
                        $lastTag = $r['asset_tag'];
            ?>
                <tr class="tag-group-header">
                    <td colspan="6">
                        <span class="tag-badge">TAG GROUP</span> 
                        &nbsp; <?= htmlspecialchars($r['asset_tag']) ?>
                    </td>
                </tr>
            <?php endif; ?>
                <tr>
                    <td class="text-muted small">#<?= htmlspecialchars($r['asset_tag']) ?></td>
                    <td class="fw-bold"><?= htmlspecialchars($r['hostname']) ?></td>
                    <td style="font-family: monospace;"><?= htmlspecialchars($r['serial']) ?></td>
                    <td><?= htmlspecialchars($r['location']) ?></td>
                    <td><?= htmlspecialchars($r['model']) ?></td>
                    <td><a href="view_new.php?id=<?= $r['id'] ?>" class="btn btn-sm btn-outline-primary py-0 px-2" style="font-size: 10px;">Details</a></td>
                </tr>
            <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="6" class="text-center py-4 text-muted">No duplicate desktop asset tags detected.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include "footer.php"; ?>
