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

<style>
.ent-container { background: #fff; border: 1px solid #d2d0ce; margin: 15px; overflow-x: auto; }
.ent-table { width: 100%; border-collapse: collapse; table-layout: auto; }
.ent-table th, .ent-table td { border: 1px solid #d2d0ce !important; padding: 6px 12px !important; font-size: 12px; white-space: nowrap; }
.ent-table th { background: #2b3b4c; color: #fff; text-transform: uppercase; cursor: pointer; text-align: left; }
.status-instore { background: #fff4ce; color: #856404; font-weight: 700; border: 1px solid #ffeeba; padding: 2px 6px; border-radius: 3px; font-size: 10px; }
.status-scrapped { background: #fde7e9; color: #a4262c; font-weight: 700; border: 1px solid #f8d7da; padding: 2px 6px; border-radius: 3px; font-size: 10px; }
.ent-pagination { display: flex !important; justify-content: center; align-items: center; gap: 4px; padding: 10px; background: #fff; border: 1px solid #d2d0ce; border-top: none; }
.ent-page-btn { min-width: 28px; height: 28px; border: 1px solid #d2d0ce; background: #fff; font-size: 11px; display: flex; align-items: center; justify-content: center; color: #333; cursor: pointer; }
.ent-page-btn.active { background: #005a9e; color: #fff; border-color: #005a9e; }
</style>

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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
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
    document.getElementById('lifecycleTableBody').innerHTML = items.map(d => `
        <tr>
            <td class="fw-bold text-primary">${d.hostname}</td>
            <td style="font-family:monospace;">${d.serial}</td>
            <td><span class="${d.status === 'In-Store' ? 'status-instore' : 'status-scrapped'}">${d.status}</span></td>
            <td>${d.location_agent || 'N/A'}</td>
            <td class="text-muted" style="font-size:11px;">${d.last_seen}</td>
            <td class="text-end">
                <button onclick="openActivateModal(${d.id}, '${d.hostname}')" class="btn btn-sm btn-outline-success py-0">Activate</button>
                <a href="view_new.php?id=${d.id}" class="btn btn-sm btn-outline-primary py-0">Details</a>
            </td>
        </tr>`).join('');
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
