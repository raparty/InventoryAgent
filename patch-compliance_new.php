<?php
require_once "config.php";
$pageTitle = "Patch Compliance";
include "header_new.php"; 

$mysqli->set_charset('utf8mb4');

/**
 * 1. REFINED COMPLIANCE QUERY
 * Adds a 14-day grace period for new/freshly built machines.
 * Flags as Outdated only if the last patch is older than 45 days.
 */
$query = "
    SELECT 
        d.id, d.hostname, d.serial, d.location, d.os_name, d.os_build, d.os_ubr, d.location_agent,
        MAX(p.install_date) as last_patch,
        CASE 
            -- Rule 1: New machines get a 14-day grace period before being called Outdated
            WHEN MAX(p.install_date) IS NULL AND DATEDIFF(CURDATE(), d.last_seen) <= 14 THEN 'Syncing'
            
            -- Rule 2: If patched in the last 45 days, it is Compliant
            WHEN MAX(p.install_date) IS NOT NULL AND DATEDIFF(CURDATE(), MAX(p.install_date)) <= 45 THEN 'Compliant'
            
            -- Rule 3: Otherwise, it is Outdated
            ELSE 'Outdated'
        END as status,
        CASE 
            WHEN d.uptime_seconds > 604800 THEN 'Yes'
            ELSE 'No'
        END as pending_reboot
    FROM devices d
    LEFT JOIN patch_status p ON d.id = p.device_id
    WHERE d.status = 'Active' 
      AND d.last_seen > NOW() - INTERVAL 30 DAY
    GROUP BY d.id
    ORDER BY 
        CASE WHEN status = 'Outdated' THEN 1 WHEN status = 'Syncing' THEN 2 ELSE 3 END ASC, 
        d.hostname ASC
";

$res = $mysqli->query($query);
$patchData = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

/** 2. CALCULATE SUMMARY STATS **/
$totalActive = count($patchData);
$totalCompliant = count(array_filter($patchData, function($v) { return $v['status'] === 'Compliant'; }));
$complianceRate = ($totalActive > 0) ? round(($totalCompliant / $totalActive) * 100, 1) : 0;
?>

<div class="patch-banner">
    <div class="patch-stat-item">
        <span class="stat-label">Active Fleet</span>
        <span class="stat-value"><?= $totalActive ?></span>
    </div>
    <div class="patch-stat-item">
        <span class="stat-label">Compliant</span>
        <span class="stat-value text-success"><?= $totalCompliant ?></span>
    </div>
    <div class="patch-stat-item">
        <span class="stat-label">Compliance Rate</span>
        <span class="stat-value"><?= $complianceRate ?>%</span>
    </div>
</div>

<div class="p-3 bg-light border-bottom d-flex gap-2 align-items-center mx-3">
    <input type="text" id="patchSearchHost" class="form-control form-control-sm" style="width:180px" placeholder="Hostname...">
    <select id="statusFilter" class="form-select form-select-sm" style="width:130px">
        <option value="">All Statuses</option>
        <option value="Compliant">Compliant</option>
        <option value="Syncing">Syncing</option>
        <option value="Outdated">Outdated</option>
    </select>
    <button class="btn btn-sm btn-secondary" onclick="resetFilters()">Reset</button>
    <button class="btn btn-sm btn-success ms-auto" onclick="exportComplianceCSV()">Export CSV</button>
</div>

<div class="ent-container">
    <table class="ent-table">
        <thead>
            <tr>
                <th onclick="handleSort('hostname')">Hostname & Site ↕</th>
                <th onclick="handleSort('status')">Status ↕</th>
                <th onclick="handleSort('last_patch')">Last Patch ↕</th>
                <th onclick="handleSort('pending_reboot')">Pending Reboot ↕</th>
                <th onclick="handleSort('os_name')">OS Name ↕</th>
                <th class="text-end">Action</th>
            </tr>
        </thead>
        <tbody id="patchTableBody"></tbody>
    </table>
    <div id="patchPagination" class="ent-pagination"></div>
</div>

<script>
const rawData = <?= json_encode($patchData) ?>;
let filteredData = [...rawData];
let page = 1;
const rowsPerPage = 15;
let sortState = { key: 'status', asc: true };

function refreshUI() {
    const hostQ = document.getElementById('patchSearchHost').value.toLowerCase();
    const statQ = document.getElementById('statusFilter').value;

    filteredData = rawData.filter(d => 
        (d.hostname || '').toLowerCase().includes(hostQ) &&
        (statQ === "" || d.status === statQ)
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

/** RENDER TABLE WITH DYNAMIC STATUS COLORS **/
function renderTable() {
    const start = (page - 1) * rowsPerPage;
    const items = filteredData.slice(start, start + rowsPerPage);
    document.getElementById('patchTableBody').innerHTML = items.map(d => `
        <tr>
            <td><span class="host-text">${d.hostname}</span><span class="site-text">${d.location_agent || 'N/A'}</span></td>
            <td><span class="${d.status === 'Compliant' ? 'status-compliant' : (d.status === 'Syncing' ? 'status-syncing' : 'status-outdated')}">${d.status}</span></td>
            <td>${d.last_patch || 'Never'}</td>
            <td class="${d.pending_reboot === 'Yes' ? 'reboot-warn' : ''}">${d.pending_reboot}</td>
            <td>${d.os_name || '-'}</td>
            <td class="text-end"><a href="view_new.php?id=${d.id}" class="btn btn-sm btn-outline-primary py-0">Details</a></td>
        </tr>`).join('');
}

function renderPager() {
    const total = Math.ceil(filteredData.length / rowsPerPage);
    const container = document.getElementById('patchPagination');
    container.innerHTML = '';
    
    if (total <= 1) return;

    let pages = [];
    const sideNeighbors = 1;

    for (let i = 1; i <= total; i++) {
        if (i === 1 || i === total || (i >= page - sideNeighbors && i <= page + sideNeighbors)) {
            pages.push(i);
        } else if (pages[pages.length - 1] !== '...') {
            pages.push('...');
        }
    }

    pages.forEach(p => {
        if (p === '...') {
            const dots = document.createElement('span');
            dots.className = "px-2";
            dots.innerText = '...';
            container.appendChild(dots);
        } else {
            const btn = document.createElement('div');
            btn.className = `ent-page-btn ${p === page ? 'active' : ''}`;
            btn.innerText = p;
            btn.onclick = () => { 
                page = p; 
                renderTable(); 
                renderPager(); 
                window.scrollTo(0, 0); 
            };
            container.appendChild(btn);
        }
    });
}

function exportComplianceCSV() {
    let csv = "Hostname,Location,Status,Last Patch Date,Pending Reboot,OS Name\n";
    filteredData.forEach(d => {
        csv += `"${d.hostname}","${d.location_agent || 'N/A'}","${d.status}","${d.last_patch || 'Never'}","${d.pending_reboot}","${d.os_name}"\n`;
    });
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = `Compliance_Report_${new Date().toISOString().slice(0,10)}.csv`;
    link.click();
}

function resetFilters() {
    document.getElementById('patchSearchHost').value = '';
    document.getElementById('statusFilter').value = '';
    refreshUI();
}

document.getElementById('patchSearchHost').addEventListener('input', () => { page = 1; refreshUI(); });
document.getElementById('statusFilter').addEventListener('change', () => { page = 1; refreshUI(); });

document.addEventListener('DOMContentLoaded', () => {
    const params = new URLSearchParams(window.location.search);
    if (params.get('status') === 'Outdated') {
        document.getElementById('statusFilter').value = 'Outdated';
    }
    refreshUI();
});
</script>

<?php include "footer.php"; ?>
