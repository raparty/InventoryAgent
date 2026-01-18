<?php
require_once "config.php";
$pageTitle = "Offline Devices Report";
include "header_new.php"; 

$mysqli->set_charset('utf8mb4');

/** * 1. FETCH OFFLINE DATA
 * Filters for devices where the last_seen date is older than 7 days from today.
 */
$query = "
    SELECT id, hostname, serial, location, last_seen, chassis_type
    FROM devices 
    WHERE DATEDIFF(CURDATE(), last_seen) > 7
    ORDER BY last_seen ASC
";

$res = $mysqli->query($query);
$offlineData = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
?>

<style>
/* Enterprise Grid Styling */
.ent-container { background: #fff; border: 1px solid #d2d0ce; margin: 15px; overflow-x: auto; }
.ent-table { width: 100%; border-collapse: collapse; table-layout: auto; }
.ent-table th, .ent-table td { border: 1px solid #d2d0ce !important; padding: 6px 10px !important; font-size: 11px; white-space: nowrap; }
.ent-table th { background: #2b3b4c; color: #fff; text-transform: uppercase; cursor: pointer; text-align: left; }

.host-text { color: #000; font-weight: 700; }
.date-stale { color: #d13438; font-weight: 600; }
.chassis-badge { background: #f3f2f1; color: #323130; padding: 2px 6px; border-radius: 4px; font-size: 10px; border: 1px solid #d2d0ce; }

.ent-pagination { display: flex !important; justify-content: center; align-items: center; gap: 4px; padding: 10px; background: #fff; border: 1px solid #d2d0ce; border-top: none; }
.ent-page-btn { min-width: 28px; height: 28px; border: 1px solid #d2d0ce; background: #fff; font-size: 11px; display: flex; align-items: center; justify-content: center; color: #333; cursor: pointer; }
.ent-page-btn.active { background: #005a9e; color: #fff; border-color: #005a9e; }
</style>

<div class="p-3 bg-light border-bottom d-flex justify-content-between align-items-center">
    <div>
        <h5 class="mb-0 fw-bold">Offline Devices (> 7 Days)</h5>
        <small class="text-muted">High-priority audit list: <?= count($offlineData) ?> devices</small>
    </div>
    <div class="d-flex gap-2">
        <input type="text" id="offlineSearch" class="form-control form-control-sm" style="width:200px" placeholder="Filter by host, serial, or chassis...">
        <button onclick="exportOfflineCSV()" class="btn btn-sm btn-success">Download CSV</button>
    </div>
</div>

<div class="ent-container">
    <table class="ent-table">
        <thead>
            <tr>
                <th onclick="handleSort('hostname')">Hostname ↕</th>
                <th onclick="handleSort('serial')">Serial Number ↕</th>
                <th onclick="handleSort('location')">Location ↕</th>
                <th onclick="handleSort('chassis_type')">Chassis Type ↕</th>
                <th onclick="handleSort('last_seen')">Last Seen ↕</th>
                <th class="text-end">Action</th>
            </tr>
        </thead>
        <tbody id="offlineTableBody"></tbody>
    </table>
    <div id="offlinePagination" class="ent-pagination"></div>
</div>

<script>
const rawData = <?= json_encode($offlineData) ?>;
let filteredData = [...rawData];
let page = 1;
const rowsPerPage = 15;
let sortState = { key: 'last_seen', asc: true };

function refreshUI() {
    const q = document.getElementById('offlineSearch').value.toLowerCase();
    filteredData = rawData.filter(d => 
        (d.hostname || '').toLowerCase().includes(q) || 
        (d.serial || '').toLowerCase().includes(q) ||
        (d.location || '').toLowerCase().includes(q) ||
        (d.chassis_type || '').toLowerCase().includes(q)
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
    document.getElementById('offlineTableBody').innerHTML = items.map(d => `
        <tr>
            <td class="host-text">${d.hostname}</td>
            <td style="font-family: monospace;">${d.serial}</td>
            <td>${d.location || 'N/A'}</td>
            <td><span class="chassis-badge">${d.chassis_type || 'N/A'}</span></td>
            <td class="date-stale">${d.last_seen || 'Unknown'}</td>
            <td class="text-end"><a href="view_new.php?id=${d.id}" class="btn btn-sm btn-outline-primary py-0">Details</a></td>
        </tr>`).join('');
}

function renderPager() {
    const total = Math.ceil(filteredData.length / rowsPerPage);
    const container = document.getElementById('offlinePagination');
    container.innerHTML = '';
    if (total <= 1) return;
    for (let i = 1; i <= total; i++) {
        if (i === 1 || i === total || (i >= page - 1 && i <= page + 1)) {
            const btn = document.createElement('div');
            btn.className = `pg-btn ${i === page ? 'active' : ''}`;
            btn.innerText = i;
            btn.onclick = () => { page = i; renderTable(); renderPager(); };
            container.appendChild(btn);
        }
    }
}

function exportOfflineCSV() {
    if (filteredData.length === 0) return alert("No data to export.");
    let csv = "Hostname,Serial,Location,Chassis Type,Last Seen\n";
    filteredData.forEach(d => {
        csv += `"${d.hostname}","${d.serial}","${d.location || 'N/A'}","${d.chassis_type || 'N/A'}","${d.last_seen || 'Unknown'}"\n`;
    });
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement("a");
    link.href = URL.createObjectURL(blob);
    link.setAttribute("download", `Offline_Devices_${new Date().toISOString().slice(0,10)}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

document.getElementById('offlineSearch').addEventListener('input', () => { page = 1; refreshUI(); });
document.addEventListener('DOMContentLoaded', refreshUI);
</script>

<?php include "footer.php"; ?>
