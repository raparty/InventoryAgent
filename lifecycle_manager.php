<?php
require_once "config.php";
$pageTitle = "Lifecycle Manager";
include "header_new.php"; 

$mysqli->set_charset('utf8mb4');

/** 1. FETCH INACTIVE ASSETS **/
$sql = "SELECT id, hostname, serial, status, location_agent, last_seen 
        FROM devices WHERE status != 'Active' ORDER BY hostname ASC";
$res = $mysqli->query($sql);
$devices = ($res) ? $res->fetch_all(MYSQLI_ASSOC) : [];
?>

<div class="p-3 bg-light border-bottom d-flex gap-2 align-items-center mx-3 mt-3 shadow-sm">
    <h6 class="m-0 fw-bold me-3">Inactive Asset Management</h6>
    <input type="text" id="searchHost" class="form-control form-control-sm" style="width:180px" placeholder="Hostname...">
    <select id="statusFilter" class="form-select form-select-sm" style="width:130px">
        <option value="">All Inactive</option>
        <option value="In-Store">In-Store</option>
        <option value="Scrapped">Scrapped</option>
    </select>
    <button class="btn btn-sm btn-secondary" onclick="resetFilters()">Reset</button>
</div>

<div class="ent-container shadow-sm">
    <table class="ent-table">
        <thead>
            <tr>
                <th onclick="handleSort('hostname')">Hostname ↕</th>
                <th onclick="handleSort('serial')">Serial ↕</th>
                <th onclick="handleSort('status')">Status ↕</th>
                <th>Last Location</th>
                <th>Last Seen</th>
                <th class="text-end">Actions</th>
            </tr>
        </thead>
        <tbody id="lifecycleTableBody"></tbody>
    </table>
    <div id="paginationContainer" class="ent-pagination"></div>
</div>

<div class="modal fade" id="activateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 350px;">
        <div class="modal-content shadow-lg" style="border-radius: 8px;">
            <div class="modal-header bg-success text-white py-2">
                <h6 class="modal-title fw-bold">Re-Activate Asset</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <input type="hidden" id="act_device_id">
                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted mb-1">Confirm Hostname</label>
                    <input type="text" id="act_hostname" class="form-control form-control-sm" style="text-transform: uppercase;">
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted mb-1">Modified By</label>
                    <input type="text" id="act_username" class="form-control form-control-sm" placeholder="username-adm">
                    <small id="userError" class="text-danger" style="display:none; font-size:10px;">Must end with -adm</small>
                </div>
                <button onclick="processActivation()" class="btn btn-success w-100 fw-bold">Confirm Activation</button>
            </div>
        </div>
    </div>
</div>

<script>
const rawData = <?= json_encode($devices) ?>;
let filteredData = [...rawData];
let page = 1;
const rowsPerPage = 15;
let sortState = { key: 'hostname', asc: true };
let actModal = null;

function refreshUI() {
    const h = document.getElementById('searchHost').value.toLowerCase();
    const s = document.getElementById('statusFilter').value;
    filteredData = rawData.filter(d => (d.hostname || '').toLowerCase().includes(h) && (s === "" || d.status === s));
    filteredData.sort((a, b) => {
        let valA = (a[sortState.key] || '').toString().toLowerCase();
        let valB = (b[sortState.key] || '').toString().toLowerCase();
        return valA.localeCompare(valB, undefined, {numeric: true}) * (sortState.asc ? 1 : -1);
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
    document.getElementById('lifecycleTableBody').innerHTML = '';
    if (items.length === 0) {
        document.getElementById('lifecycleTableBody').innerHTML =
            '<tr><td colspan="6" class="text-center py-4 text-muted">No inactive assets match the current filters.</td></tr>';
        return;
    }
    items.forEach(d => {
        const tr = document.createElement('tr');
        const safeHostname = document.createElement('span');
        safeHostname.textContent = d.hostname;
        const safeSerial = document.createElement('span');
        safeSerial.textContent = d.serial;
        const safeLocation = document.createElement('span');
        safeLocation.textContent = d.location_agent || 'N/A';
        const safeLastSeen = document.createElement('span');
        safeLastSeen.textContent = d.last_seen;
        const statusClass = d.status === 'In-Store' ? 'status-instore' : 'status-scrapped';
        const statusSpan = document.createElement('span');
        statusSpan.className = statusClass;
        statusSpan.textContent = d.status;

        const activateBtn = document.createElement('button');
        activateBtn.className = 'btn btn-sm btn-outline-success py-0';
        activateBtn.textContent = 'Activate';
        activateBtn.addEventListener('click', () => openActivateModal(d.id, d.hostname));

        const detailsLink = document.createElement('a');
        detailsLink.href = `view_new.php?id=${encodeURIComponent(d.id)}`;
        detailsLink.className = 'btn btn-sm btn-outline-primary py-0';
        detailsLink.textContent = 'Details';

        tr.innerHTML = `
            <td class="fw-bold text-primary"></td>
            <td style="font-family:monospace;"></td>
            <td></td>
            <td></td>
            <td class="text-muted" style="font-size:11px;"></td>
            <td class="text-end"></td>`;
        tr.cells[0].appendChild(safeHostname);
        tr.cells[1].appendChild(safeSerial);
        tr.cells[2].appendChild(statusSpan);
        tr.cells[3].appendChild(safeLocation);
        tr.cells[4].appendChild(safeLastSeen);
        tr.cells[5].appendChild(activateBtn);
        tr.cells[5].appendChild(document.createTextNode(' '));
        tr.cells[5].appendChild(detailsLink);
        document.getElementById('lifecycleTableBody').appendChild(tr);
    });
}

function renderPager() {
    const total = Math.ceil(filteredData.length / rowsPerPage);
    const container = document.getElementById('paginationContainer');
    container.innerHTML = '';
    if (total <= 1) return;
    let pages = [];
    for (let i = 1; i <= total; i++) {
        if (i === 1 || i === total || (i >= page - 1 && i <= page + 1)) pages.push(i);
        else if (pages[pages.length - 1] !== '...') pages.push('...');
    }
    pages.forEach(p => {
        if (p === '...') container.insertAdjacentHTML('beforeend', '<span class="px-2">...</span>');
        else {
            const btn = document.createElement('div');
            btn.className = `ent-page-btn ${p === page ? 'active' : ''}`;
            btn.innerText = p;
            btn.onclick = () => { page = p; renderTable(); renderPager(); window.scrollTo(0,0); };
            container.appendChild(btn);
        }
    });
}

function openActivateModal(id, hostname) {
    document.getElementById('act_device_id').value = id;
    document.getElementById('act_hostname').value = hostname.toUpperCase();
    document.getElementById('act_username').value = '';
    document.getElementById('userError').style.display = 'none';
    if(!actModal) actModal = new bootstrap.Modal(document.getElementById('activateModal'));
    actModal.show();
}

function processActivation() {
    const user = document.getElementById('act_username').value.trim().toLowerCase();
    if (!user.endsWith('-adm')) { document.getElementById('userError').style.display = 'block'; return; }
    
    fetch('api_activate_asset.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `id=${document.getElementById('act_device_id').value}&hostname=${document.getElementById('act_hostname').value.trim()}&modified_by=${user}`
    }).then(res => res.json()).then(data => { if(data.success) location.reload(); else alert(data.message); });
}

function resetFilters() { document.getElementById('searchHost').value = ''; document.getElementById('statusFilter').value = ''; refreshUI(); }
document.getElementById('searchHost').addEventListener('input', () => { page = 1; refreshUI(); });
document.getElementById('statusFilter').addEventListener('change', () => { page = 1; refreshUI(); });
document.addEventListener('DOMContentLoaded', refreshUI);
</script>
<?php include "footer.php"; ?>
