<?php
require_once 'config.php';
$pageTitle = "Reports Center";
include "header_new.php"; 
?>

<div class="ent-reports-wrapper">
    <div class="container-fluid">
        
        <div class="ent-report-card">
            <div class="ent-section-title">📊 Quick Data Exports</div>
            <p class="small-muted">Generate and download instant CSV of your current inventory status.</p>
            <div class="d-flex gap-3">
                <a href="phpapi/generate_report.php?report_type=summary" class="btn btn-enterprise btn-export-blue">Export Summary</a>
                <a href="phpapi/generate_report.php?report_type=full" class="btn btn-enterprise btn-export-green">Export Full Inventory</a>
            </div>
        </div>

        <div class="ent-report-card">
            <div class="ent-section-title">🛠️ Custom Report</div>
            <p class="small-muted">Configure specific filters to generate targeted compliance and asset reports.</p>
            
            <form id="reportForm" method="get" action="phpapi/generate_report.php" target="_blank">
                <div class="row g-4">
                    
                    <div class="col-md-4">
                        <label class="form-label fw-bold small">Select Report Type</label>
                        <select name="report_type" id="reportType" class="form-select form-select-sm" required>
                            <option value="installed_software">Software Audit (Searchable)</option>
                            <option value="missing_computer_asset_tags">Inventory: Missing Asset Tags</option>
                            <option value="missing_monitor_asset_tags">Inventory: Missing Monitor Tags</option>
                            <option value="os_report">OS Distribution Report</option>
                            <option value="ubr_report">Build/UBR Compliance Report</option>
                            <option value="manufacturer_report">Hardware: Manufacturer Audit</option>
                            <option value="model_report">Hardware: Model Audit</option>
                        </select>
                    </div>

                    <div class="col-md-5" id="paramSoftware" style="display:none; position:relative;">
                        <label class="form-label fw-bold small">Software Name</label>
                        <input id="softwareSearch" name="software_name" autocomplete="off" class="form-control form-control-sm" placeholder="Start typing (e.g. Chrome, Office)...">
                        <div id="suggestions" class="suggest-container"></div>
                    </div>

                    <div class="col-md-3" id="paramOS" style="display:none;">
                        <label class="form-label fw-bold small">Operating System</label>
                        <select name="os" class="form-select form-select-sm">
                            <option value="Windows 10">Windows 10</option>
                            <option value="Windows 11">Windows 11</option>
                        </select>
                    </div>

                    <div class="col-md-4" id="paramManufacturer" style="display:none;">
                        <label class="form-label fw-bold small">Manufacturer Name</label>
                        <input name="manufacturer" class="form-control form-control-sm" placeholder="e.g. HP, Dell, Lenovo">
                    </div>

                    <div class="col-md-4" id="paramModel" style="display:none;">
                        <label class="form-label fw-bold small">Hardware Model</label>
                        <input name="model" class="form-control form-control-sm" placeholder="e.g. EliteBook 840 G8">
                    </div>

                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-enterprise btn-export-blue w-100">Run Report</button>
                    </div>
                </div>
            </form>
        </div>

    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const rpt = document.getElementById('reportType');
    const paramSoftware = document.getElementById('paramSoftware');
    const paramOS = document.getElementById('paramOS');
    const paramManufacturer = document.getElementById('paramManufacturer');
    const paramModel = document.getElementById('paramModel');

    function hideAll() {
        paramSoftware.style.display = 'none';
        paramOS.style.display = 'none';
        paramManufacturer.style.display = 'none';
        paramModel.style.display = 'none';
    }

    function updateFields() {
        hideAll();
        switch (rpt.value) {
            case 'installed_software': paramSoftware.style.display = 'block'; break;
            case 'os_report': paramOS.style.display = 'block'; break;
            case 'manufacturer_report': paramManufacturer.style.display = 'block'; break;
            case 'model_report': paramModel.style.display = 'block'; break;
        }
    }

    rpt.addEventListener('change', updateFields);
    updateFields();

    /* Autosuggest for software */
    const search = document.getElementById('softwareSearch');
    const suggestions = document.getElementById('suggestions');
    let debounceTimer = null;

    search.addEventListener('input', function() {
        const q = this.value.trim();
        if (q.length < 2) { 
            suggestions.style.display = 'none'; 
            return; 
        }
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            fetch('phpapi/get_software_list.php?q=' + encodeURIComponent(q) + '&limit=15')
                .then(resp => resp.json())
                .then(list => {
                    suggestions.innerHTML = '';
                    if (!Array.isArray(list) || list.length === 0) { 
                        suggestions.style.display = 'none'; 
                        return; 
                    }
                    list.forEach(item => {
                        const div = document.createElement('div');
                        div.className = 'suggest-row';
                        div.textContent = item;
                        div.onclick = () => {
                            search.value = item;
                            suggestions.style.display = 'none';
                        };
                        suggestions.appendChild(div);
                    });
                    suggestions.style.display = 'block';
                })
                .catch(() => { suggestions.style.display = 'none'; });
        }, 200);
    });

    document.addEventListener('click', (e) => {
        if (!paramSoftware.contains(e.target)) suggestions.style.display = 'none';
    });
});
</script>

<?php include "footer.php"; ?>
