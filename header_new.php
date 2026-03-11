<?php 
if (!isset($pageTitle)) $pageTitle = 'Enterprise Inventory'; 
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/enterprise_new.css?v=<?= time(); ?>">
</head>
<body style="background-color: #f3f2f1 !important;">

<div class="ent-top-bar">
    <strong style="letter-spacing: 0.5px;">EXAMITY</strong> &nbsp;| Enterprise Inventory
    <div class="ms-auto"><span class="badge bg-success" style="font-size: 10px; font-weight: 600;">PROD</span></div>
</div>

<nav class="ent-nav-bar shadow-sm">
    <a class="ent-nav-link <?= ($current_page == 'dashboard_new.php') ? 'active' : '' ?>" href="dashboard_new.php">Dashboard</a>
    <a class="ent-nav-link <?= ($current_page == 'index_new.php') ? 'active' : '' ?>" href="index_new.php">Devices</a>
    <a class="ent-nav-link <?= ($current_page == 'reports_new.php') ? 'active' : '' ?>" href="reports_new.php">Reports</a>
    <a class="ent-nav-link <?= ($current_page == 'patch-compliance_new.php') ? 'active' : '' ?>" href="patch-compliance_new.php">Patches</a>
    <a class="ent-nav-link <?= ($current_page == 'compare_new.php') ? 'active' : '' ?>" href="compare_new.php">Device History</a>
    <a class="ent-nav-link <?= ($current_page == 'lifecycle_manager.php') ? 'active' : '' ?>" href="lifecycle_manager.php">Lifecycle Manager</a>
</nav>

<div class="page-wrapper container-fluid p-0">
