<?php
/**
 * phpapi/get_software_list.php — Software name autosuggest for Reports page.
 * Accepts GET: q (search string), limit (int, max 50)
 * Returns JSON array of matching software name strings.
 */
require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/config.php';

header('Content-Type: application/json; charset=utf-8');

$q     = isset($_GET['q'])     ? trim($_GET['q'])      : '';
$limit = isset($_GET['limit']) ? (int)$_GET['limit']   : 15;

if ($limit < 1 || $limit > 50) {
    $limit = 15;
}

if (mb_strlen($q) < 2) {
    echo json_encode([]);
    exit;
}

$like = '%' . $q . '%';
$stmt = $mysqli->prepare(
    "SELECT DISTINCT software_name
     FROM installed_software
     WHERE software_name LIKE ?
     ORDER BY software_name ASC
     LIMIT ?"
);
$stmt->bind_param("si", $like, $limit);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$names = array_column($rows, 'software_name');
echo json_encode($names);
