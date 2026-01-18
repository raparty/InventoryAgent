<?php
require_once "config.php";
$pageTitle = "Inventory Drilldown";
include "header_new.php"; 

$mysqli->set_charset('utf8mb4');

$type = $_GET['type'] ?? '';
$displayTitle = "Inventory Details";
$whereClause = "1=1";

// Handle Drilldown Types
switch ($type) {
    case 'missing_asset_tag':
        $displayTitle = "Devices Missing Asset Tags";
        $whereClause = "NOT EXISTS (SELECT 1 FROM asset_tag_map atm WHERE atm.serial_number = d.serial)";
        break;
    case 'offline':
        $displayTitle = "Devices Offline > 7 Days";
        $whereClause = "DATEDIFF(CURDATE(), d.last_seen) > 7";
        break;
    case 'pending_reboot':
        $displayTitle = "Devices Requiring Reboot";
        $whereClause = "d.uptime_seconds > 604800";
        break;
    default:
        $displayTitle = "General Inventory Drilldown";
}

$query = "
    SELECT d.id, d.hostname, d.serial, d.location, d.last_seen, d.model, d.chassis_type
    FROM devices d
    WHERE $whereClause
    ORDER BY d.hostname ASC
";

$res = $mysqli->query($query);
$drillData = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
?>

<style>
/* Enterprise Grid Styling */
.ent-container { background: #fff; border: 1px solid #d2d0ce; margin: 15px; overflow-x: auto; }
.ent-table { width: 100%; border-collapse: collapse; table-layout: auto; }
.ent-table th, .ent-table td { border: 1px solid #d2d0ce !important; padding: 4px 8px !important; font-size: 11px; white-space: nowrap; }
.ent-table th { background: #2b3b4c; color: #fff; text-transform: uppercase; cursor: pointer; text-align: left; }
.host-text { color: #000; font-weight: 700; display: block; }
.location-text { color: #005a9e; font-weight: 600; }
.chassis-badge { background: #f3f2f1; color: #323130; padding: 2px 6px; border-radius: 4px; font-size: 10px; border: 1px solid #d2d0ce; }

.ent-pagination { display: flex !important; justify-content: center; align-items: center; gap: 4px; padding: 10px; background: #fff; border: 1px solid #d2d0ce; border-top: none; }
.ent-page-btn { min-width: 28px; height: 28px; border: 1px solid #d2d0ce; background: #fff; font-size: 11px; display: flex; align-items: center; justify-content: center; color: #333; cursor: pointer; }
.ent-page-btn.active { background: #005a9e; color: #fff; border-color: #005a9e; }
</style>

<div class="p-3 bg-light border-bottom d-flex justify-content-between align-items-center">
    <div>
        <h5 class="mb-0 fw-bold"><?= htmlspecialchars($displayTitle) ?></h5>
        <small class="text-muted">Total Records: <?= count($drillData) ?></small>
    </div>
    <div class="d-flex gap-2">
        <input type="text" id="drillSearch" class="form-control form-control-sm" style="width:200px" placeholder="Search results...">
        <button onclick="exportDrilldownCSV()" class="btn btn-sm btn-success">Export to CSV</button>
    </div>
</div>

<div class="ent-container">
    <table class="ent-table">
        <thead>
            <tr>
                <th onclick="handleSort('hostname')">Hostname ↕</th>
                <th onclick="handleSort('location')">Location ↕</th>
                <th onclick="handleSort('chassis_type')">Chassis ↕</th>
                <th onclick="handleSort('serial')">Serial Number ↕</th>
                <th onclick="handleSort('model')">Model ↕</th>
                <th onclick="handleSort('last_seen')">Last Seen ↕</th>
                <th class="text-end">Action</th>
            </tr>
        </thead>
        <tbody id="drillTableBody"></tbody>
    </table>
    <div id="drillPagination" class="ent-pagination"></div>
</div>

<script>
const rawData = <?= json_encode($drillData) ?>;
let filteredData = [...rawData];
let page = 1;
const rowsPerPage = 15;
let sortState = { key: 'hostname', asc: true };

// Filtering and Sorting Logic
function refreshUI() {
    const q = document.getElementById('drillSearch').value.toLowerCase();
    filteredData = rawData.filter(d => 
        (d.hostname || '').toLowerCase().includes(q) || 
        (d.serial || '').toLowerCase().includes(q) ||
        (d.location || '').toLowerCase().includes(q)
    );

    filteredData.sort((a, b) => {
        const valA = (a[sortState.key] || '').toString().toLowerCase();
        const valB = (b[sortState.key] || '').toString().toLowerCase();
        if (valA < valB) return sortState.asc ? -1 : 1;
        if (valA > valB) return sortState.asc ? 1 : -1;
        return 0;
    });

    renderTable();
    renderPager();
}

function handleSort(key) {
    sortState.asc = (sortState.key === key) ? !sortState.asc : true;
    sortState.key = key;
    page = 1;
    refreshUI();
}

function renderTable() {
    const start = (page - 1) * rowsPerPage;
    const items = filteredData.slice(start, start + rowsPerPage);
    document.getElementById('drillTableBody').innerHTML = items.map(d => `
        <tr>
            <td><span class="host-text">${d.hostname}</span></td>
            <td><span class="location-text">${d.location || 'UNKNOWN'}</span></td>
            <td><span class="chassis-badge">${d.chassis_type || 'N/A'}</span></td>
            <td style="font-family: monospace;">${d.serial}</td>
            <td>${d.model}</td>
            <td>${d.last_seen || '-'}</td>
            <td class="text-end"><a href="view_new.php?id=${d.id}" class="btn btn-sm btn-outline-primary py-0">Details</a></td>
        </tr>`).join('');
}

function renderPager() {
    const total = Math.ceil(filteredData.length / rowsPerPage);
    const container = document.getElementById('drillPagination');
    container.innerHTML = '';
    if (total <= 1) return;
    for (let i = 1; i <= total; i++) {
        if (i === 1 || i === total || (i >= page - 1 && i <= page + 1)) {
            const btn = document.createElement('div');
            btn.className = `ent-page-btn ${i === page ? 'active' : ''}`;
            btn.innerText = i;
            btn.onclick = () => { page = i; renderTable(); renderPager(); };
            container.appendChild(btn);
        }
    }
}

/**
 * EXPORT TO CSV LOGIC
 * Captures currently filtered data and generates a downloadable CSV.
 */
function exportDrilldownCSV() {
    if (filteredData.length === 0) return alert("No data to export.");
    
    const typeLabel = "<?= htmlspecialchars($type) ?>" || "Inventory";
    let csv = "Hostname,Location,Chassis,Serial,Model,Last Seen\n";
    
    filteredData.forEach(d => {
        csv += `"${d.hostname}","${d.location || 'N/A'}","${d.chassis_type || 'N/A'}","${d.serial}","${d.model}","${d.last_seen || '-'}"\n`;
    });

    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement("a");
    link.href = URL.createObjectURL(blob);
    link.setAttribute("download", `Drilldown_${typeLabel}_${new Date().toISOString().slice(0,10)}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

document.getElementById('drillSearch').addEventListener('input', () => { page = 1; refreshUI(); });
document.addEventListener('DOMContentLoaded', refreshUI);
</script>

<?php include "footer.php"; ?>
