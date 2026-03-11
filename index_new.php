<?php
require_once "config.php";
$pageTitle = "Enterprise Inventory - Devices";
include "header_new.php"; 

$mysqli->set_charset('utf8mb4');

// 2. UPDATED QUERY: Included 'status' and verified column names match your DESCRIBE output
$query = "SELECT id, hostname, serial, chassis_type, os_name, os_ubr, location, location_agent, status 
          FROM devices 
          WHERE status = 'Active' 
          ORDER BY hostname ASC";

$res = $mysqli->query($query);

if (!$res) {
    die("Database Query Failed: " . $mysqli->error);
}

$devices = $res->fetch_all(MYSQLI_ASSOC);
?>

<div class="p-3 bg-light border-bottom d-flex gap-2">
    <input type="text" id="searchHost" class="form-control form-control-sm" style="width:180px" placeholder="Hostname...">
    <input type="text" id="searchSerial" class="form-control form-control-sm" style="width:130px" placeholder="Serial...">
    <input type="text" id="searchUBR" class="form-control form-control-sm" style="width:100px" placeholder="UBR...">
    <button class="btn btn-sm btn-secondary" onclick="resetFilters()">Reset</button>
    <button class="btn btn-sm btn-success ms-auto" onclick="exportCSV()">Export CSV</button>
</div>

<div class="ent-container">
    <table class="ent-table">
        <thead>
            <tr>
                <th onclick="handleSort('hostname')">Hostname & Site ↕</th>
                <th onclick="handleSort('serial')">Serial ↕</th>
                <th onclick="handleSort('chassis_type')">Chassis ↕</th>
                <th onclick="handleSort('os_name')" class="text-center">OS Version ↕</th>
                <th onclick="handleSort('os_ubr')" class="text-center">UBR ↕</th>
                <th class="text-end">Action</th>
            </tr>
        </thead>
        <tbody id="deviceTableBody"></tbody>
    </table>
    <div id="paginationContainer" class="ent-pagination"></div>
</div>

<script>
// 3. SAFE ENCODING: Ensures JS doesn't crash if data is empty
const rawData = <?= json_encode($devices ?: []) ?>;
let filteredData = [...rawData];
let currentPage = 1;
const rowsPerPage = 15;
let sortState = { key: 'hostname', asc: true };

function refreshUI() {
    const h = document.getElementById('searchHost').value.toLowerCase();
    const s = document.getElementById('searchSerial').value.toLowerCase();
    const u = document.getElementById('searchUBR').value.toLowerCase();

    filteredData = rawData.filter(d => 
        (d.hostname || '').toLowerCase().includes(h) &&
        (d.serial || '').toLowerCase().includes(s) &&
        (d.os_ubr || '').toLowerCase().includes(u)
    );

    filteredData.sort((a, b) => {
        let valA = (a[sortState.key] || '').toString().toLowerCase();
        let valB = (b[sortState.key] || '').toString().toLowerCase();
        return valA.localeCompare(valB, undefined, {numeric: true, sensitivity: 'base'}) * (sortState.asc ? 1 : -1);
    });

    renderTable();
    renderPager();
}

function renderTable() {
    const start = (currentPage - 1) * rowsPerPage;
    const items = filteredData.slice(start, start + rowsPerPage);
    
    if (items.length === 0) {
        document.getElementById('deviceTableBody').innerHTML =
            '<tr><td colspan="6" class="text-center py-4 text-muted">No devices match the current filters.</td></tr>';
        return;
    }
    
    // Updated to use location_agent as per your table structure
    document.getElementById('deviceTableBody').innerHTML = items.map(d => `
        <tr>
            <td>
                <div class="fw-bold text-primary">${d.hostname}</div>
                <div class="small text-muted">${d.location_agent || d.location || 'N/A'}</div>
            </td>
            <td class="serial-red">${d.serial || '-'}</td>
            <td>${d.chassis_type || '-'}</td>
            <td class="text-center">
                <span class="badge ${(d.os_name || '').includes('11') ? 'bg-primary' : 'bg-info'}">
                    ${d.os_name || 'Unknown'}
                </span>
            </td>
            <td class="text-center">${d.os_ubr || '-'}</td>
            <td class="text-end"><a href="view_new.php?id=${d.id}" class="btn btn-sm btn-outline-primary py-0">View</a></td>
        </tr>`).join('');
}

// ... (Your existing renderPager and resetFilters functions remain exactly the same) ...
function renderPager() {
    const total = Math.ceil(filteredData.length / rowsPerPage);
    const container = document.getElementById('paginationContainer');
    container.innerHTML = '';
    if (total <= 1) return;
    let pages = [];
    const sideNeighbors = 1;
    for (let i = 1; i <= total; i++) {
        if (i === 1 || i === total || (i >= currentPage - sideNeighbors && i <= currentPage + sideNeighbors)) {
            pages.push(i);
        } else if (pages[pages.length - 1] !== '...') {
            pages.push('...');
        }
    }
    pages.forEach(p => {
        if (p === '...') {
            container.insertAdjacentHTML('beforeend', '<span class="px-2">...</span>');
        } else {
            const btn = document.createElement('button');
            btn.className = `ent-page-btn ${p === currentPage ? 'active' : ''}`;
            btn.innerText = p;
            btn.onclick = () => { currentPage = p; renderTable(); renderPager(); window.scrollTo(0,0); };
            container.appendChild(btn);
        }
    });
}

function handleSort(key) {
    sortState.asc = (sortState.key === key) ? !sortState.asc : true;
    sortState.key = key;
    currentPage = 1;
    refreshUI();
}

function resetFilters() {
    document.getElementById('searchHost').value = '';
    document.getElementById('searchSerial').value = '';
    document.getElementById('searchUBR').value = '';
    refreshUI();
}

function exportCSV() {
    if (filteredData.length === 0) { alert("No data to export."); return; }
    let csv = "Hostname,Location,Serial,Chassis,OS Name,UBR\n";
    filteredData.forEach(d => {
        csv += `"${d.hostname || ''}","${d.location_agent || d.location || 'N/A'}","${d.serial || ''}","${d.chassis_type || ''}","${d.os_name || ''}","${d.os_ubr || ''}"\n`;
    });
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement("a");
    link.href = URL.createObjectURL(blob);
    link.setAttribute("download", `Inventory_${new Date().toISOString().slice(0,10)}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

document.getElementById('searchHost').addEventListener('input', () => { currentPage = 1; refreshUI(); });
document.getElementById('searchSerial').addEventListener('input', () => { currentPage = 1; refreshUI(); });
document.getElementById('searchUBR').addEventListener('input', () => { currentPage = 1; refreshUI(); });
document.addEventListener('DOMContentLoaded', refreshUI);
</script>

<?php include "footer.php"; ?>
