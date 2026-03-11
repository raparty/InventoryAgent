<?php
require_once "config.php";
$pageTitle = "Enterprise Stock Ledger";
include "header_new.php"; 

$mysqli->set_charset('utf8mb4');

/** 1. HANDLE POST TRANSACTIONS **/
$message = "";
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $cat = $mysqli->real_escape_string($_POST['category']);
    $model = $mysqli->real_escape_string($_POST['model_name']);
    $qty = (int)$_POST['quantity'];
    $modifier = strtolower(trim($mysqli->real_escape_string($_POST['modified_by'])));

    if (!\InventoryAgent\AdminValidator::isValid($modifier)) {
        $message = "<div class='alert alert-danger mx-3 shadow-sm'>Admin ID (-adm) required.</div>";
    } else {
        // Update Balance
        if ($_POST['action'] == 'receive') {
            $stmt = $mysqli->prepare("INSERT INTO stock_inventory (category, model_name, current_stock, modified_by) 
                    VALUES (?, ?, ?, ?) 
                    ON DUPLICATE KEY UPDATE current_stock = current_stock + ?, modified_by = ?");
            $stmt->bind_param("ssiisi", $cat, $model, $qty, $modifier, $qty, $modifier);
        } else {
            $stmt = $mysqli->prepare("UPDATE stock_inventory SET current_stock = current_stock - ?, modified_by = ? 
                    WHERE model_name = ? AND current_stock >= ?");
            $stmt->bind_param("issi", $qty, $modifier, $model, $qty);
        }
        
        if ($stmt->execute()) {
            // Log the Transaction for Audit
            $item_stmt = $mysqli->prepare("SELECT id FROM stock_inventory WHERE model_name = ?");
            $item_stmt->bind_param("s", $model);
            $item_stmt->execute();
            $item_res = $item_stmt->get_result();
            $item_id = $item_res->fetch_assoc()['id'];
            $act_label = ($_POST['action'] == 'receive') ? 'Receive' : 'Issue';
            $log_stmt = $mysqli->prepare("INSERT INTO stock_logs (item_id, action_type, quantity, admin_user) VALUES (?, ?, ?, ?)");
            $log_stmt->bind_param("isis", $item_id, $act_label, $qty, $modifier);
            $log_stmt->execute();
            $message = "<div class='alert alert-success mx-3 shadow-sm'>Successfully logged: $act_label $qty x " . htmlspecialchars($model) . "</div>";
        }
    }
}

/** 2. FETCH DATA FOR UI **/
$inventory = $mysqli->query("SELECT * FROM stock_inventory ORDER BY category ASC, model_name ASC")->fetch_all(MYSQLI_ASSOC);
$logs = $mysqli->query("SELECT l.*, i.model_name FROM stock_logs l JOIN stock_inventory i ON l.item_id = i.id ORDER BY l.timestamp DESC LIMIT 50")->fetch_all(MYSQLI_ASSOC);
?>

<div class="stock-grid">
    <div>
        <div class="ent-card mb-3 shadow-sm">
            <div class="ent-header" style="color: #005a9e;">Stock Action</div>
            <div class="p-3">
                <form method="POST">
                    <label class="small fw-bold text-muted">Transaction</label>
                    <select name="action" class="form-select form-select-sm mb-2">
                        <option value="receive">Receive Stock (+)</option>
                        <option value="issue">Issue to User (-)</option>
                    </select>
                    
                    <label class="small fw-bold text-muted">Category</label>
                    <select name="category" class="form-select form-select-sm mb-2">
                        <option>Keyboard</option><option>Mouse</option>
                        <option>Headset</option><option>Laptop</option><option>Monitor</option><option>Other</option>
                    </select>

                    <label class="small fw-bold text-muted">Item Description</label>
                    <input type="text" name="model_name" list="existing_items" class="form-control form-control-sm mb-2" placeholder="e.g., Logitech K120" required>
                    <datalist id="existing_items">
                        <?php foreach($inventory as $i) echo "<option value='".htmlspecialchars($i['model_name'])."'>"; ?>
                    </datalist>

                    <div class="row mb-2">
                        <div class="col-6">
                            <label class="small fw-bold text-muted">Quantity</label>
                            <input type="number" name="quantity" class="form-control form-control-sm" min="1" value="1">
                        </div>
                        <div class="col-6">
                            <label class="small fw-bold text-muted">By (-adm)</label>
                            <input type="text" name="modified_by" class="form-control form-control-sm" placeholder="user-adm" required>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-sm btn-primary w-100 fw-bold">Execute Update</button>
                </form>
            </div>
        </div>

        <div class="ent-card shadow-sm">
            <div class="ent-header">Recent Audit Logs</div>
            <div class="p-0" style="max-height: 400px; overflow-y: auto;">
                <table class="table table-sm mb-0" style="font-size: 11px;">
                    <?php foreach($logs as $l): ?>
                    <tr>
                        <td class="p-2">
                            <span class="badge <?= $l['action_type']=='Receive'?'bg-success':'bg-secondary' ?>"><?= $l['action_type'] ?></span>
                            <strong><?= htmlspecialchars($l['model_name']) ?></strong> (<?= $l['quantity'] ?>)<br>
                            <span class="text-muted"><?= $l['timestamp'] ?> by <?= $l['admin_user'] ?></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>
    </div>

    <div class="ent-card shadow-sm">
        <div class="ent-header d-flex justify-content-between align-items-center">
            <span>Inventory Balance</span>
            <input type="text" id="invSearch" class="form-control form-control-sm py-0" style="width: 200px;" placeholder="Search models...">
        </div>
        <div class="p-0">
            <table class="stock-table w-100">
                <thead class="bg-light">
                    <tr>
                        <th>Category</th>
                        <th>Model Name</th>
                        <th class="text-center">On Hand</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody id="inventoryBody"></tbody>
            </table>
            <div id="inventoryPager" class="p-2 bg-light border-top text-center"></div>
        </div>
    </div>
</div>

<script>
/** 3. PAGINATION & SEARCH LOGIC **/
const rawInv = <?= json_encode($inventory) ?>;
let filteredInv = [...rawInv];
let invPage = 1;
const invSize = 15;

function renderInventory() {
    const start = (invPage - 1) * invSize;
    const slice = filteredInv.slice(start, start + invSize);
    
    document.getElementById('inventoryBody').innerHTML = slice.map(item => {
        const isLow = item.current_stock <= item.min_alert_level;
        return `
        <tr class="${isLow ? 'low-stock' : ''}">
            <td>${item.category}</td>
            <td class="fw-bold">${item.model_name}</td>
            <td class="text-center"><strong>${item.current_stock}</strong></td>
            <td>
                ${isLow ? '<span class="badge bg-danger">REORDER</span>' : '<span class="text-success small fw-bold">OK</span>'}
            </td>
        </tr>`;
    }).join('');
    
    renderInvPager();
}

function renderInvPager() {
    const total = Math.ceil(filteredInv.length / invSize);
    const pager = document.getElementById('inventoryPager');
    pager.innerHTML = '';
    if(total <= 1) return;

    for(let i=1; i<=total; i++) {
        const btn = document.createElement('button');
        btn.className = `btn btn-xs pager-btn ${i===invPage?'btn-primary':'btn-outline-secondary'}`;
        btn.innerText = i;
        btn.onclick = () => { invPage = i; renderInventory(); };
        pager.appendChild(btn);
    }
}

document.getElementById('invSearch').addEventListener('input', e => {
    const term = e.target.value.toLowerCase();
    filteredInv = rawInv.filter(i => 
        i.model_name.toLowerCase().includes(term) || 
        i.category.toLowerCase().includes(term)
    );
    invPage = 1;
    renderInventory();
});

document.addEventListener('DOMContentLoaded', renderInventory);
</script>

<?php include "footer.php"; ?>
