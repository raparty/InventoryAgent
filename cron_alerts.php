<?php
/**
 * Inventory Alert System - Final Production Version
 * Syncs with Dashboard logic for Offline Devices and Hardware Changes
 */

require_once "config.php";

// 1. Teams Webhook URL
$webhook_url = "https://getyardstick.webhook.office.com/webhookb2/4d63d8cf-86a7-4d65-9f86-6141c160b4d5@b4f407c4-ec13-40d9-9271-ea0eaf7fac6e/IncomingWebhook/de9534f31d794cbb983da009fe1719ae/1e365108-4bb4-44a0-a05f-7f389d20d2b2/V2urSTwgwVSjb0AAbDj3-cbdD6bxkZ7EY0_INGn12xVkE1";

// 2. Fetch Unsent Hardware Changes
$change_list = [];
$log_ids = [];
$change_query = "
    SELECT l.id, d.hostname, l.change_type, l.old_value, l.new_value 
    FROM device_change_logs l 
    JOIN devices d ON l.device_id = d.id 
    WHERE l.is_sent = 0
";

$res = $mysqli->query($change_query);
if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        $change_list[] = $row;
        $log_ids[] = $row['id'];
    }
}

// 3. Fetch Offline Data (Matching Dashboard Logic)
$offline_list = [];
$off_query = "
    SELECT id, hostname, serial, location, last_seen, chassis_type
    FROM devices 
    WHERE DATEDIFF(CURDATE(), last_seen) > 7
    AND status = 'Active' 
    AND hostname IS NOT NULL
    ORDER BY last_seen ASC
";

$off_res = $mysqli->query($off_query);
if ($off_res) {
    while ($row = $off_res->fetch_assoc()) {
        $offline_list[] = $row;
    }
}

// 4. Exit if there is nothing new to report
if (empty($change_list) && empty($offline_list)) {
    die("Inventory Report: No new changes or offline devices to report today.");
}

// 5. Build the Adaptive Card Body
$body = [];
$body[] = [
    "type" => "TextBlock", 
    "text" => "📅 Daily Inventory Change Summary (" . date('Y-m-d') . ")", 
    "weight" => "Bolder", 
    "size" => "Large", 
    "color" => "Accent"
];

// Add Hardware Changes Section
if (!empty($change_list)) {
    $body[] = ["type" => "TextBlock", "text" => "Detected Changes:", "weight" => "Bolder", "spacing" => "Medium"];
    foreach ($change_list as $chg) {
        $body[] = [
            "type" => "TextBlock", 
            "text" => "• **" . $chg['hostname'] . "**: " . $chg['change_type'] . " (" . $chg['old_value'] . " ➔ " . $chg['new_value'] . ")", 
            "wrap" => true
        ];
    }
}

// Add Offline Section (matching Dashboard list)
if (!empty($offline_list)) {
    $body[] = ["type" => "TextBlock", "text" => "Critical Offline (> 7 Days):", "weight" => "Bolder", "color" => "Attention", "spacing" => "Medium"];
    foreach ($offline_list as $dev) {
        $location = !empty($dev['location']) ? $dev['location'] : "N/A";
        $chassis = !empty($dev['chassis_type']) ? $dev['chassis_type'] : "Device";
        
        $body[] = [
            "type" => "TextBlock", 
            "text" => "• **" . $dev['hostname'] . "** (" . $location . " - " . $chassis . ") | Last seen: " . $dev['last_seen'], 
            "wrap" => true
        ];
    }
}

// 6. Package Payload
$payload = [
    "type" => "message",
    "attachments" => [[
        "contentType" => "application/vnd.microsoft.card.adaptive",
        "content" => [
            "type" => "AdaptiveCard",
            "body" => $body,
            "\$schema" => "http://adaptivecards.io/schemas/adaptive-card.json",
            "version" => "1.4"
        ]
    ]]
];

// 7. Send to Microsoft Teams
$options = [
    'http' => [
        'method'  => 'POST',
        'header'  => 'Content-Type: application/json',
        'content' => json_encode($payload)
    ]
];
$context = stream_context_create($options);
$result = file_get_contents($webhook_url, false, $context);

// 8. Mark logs as Sent to prevent duplicates tomorrow
if ($result !== FALSE && !empty($log_ids)) {
    // Sanitize log IDs to prevent SQL injection
    $log_ids = array_map('intval', $log_ids);
    $ids_string = implode(',', $log_ids);
    $mysqli->query("UPDATE device_change_logs SET is_sent = 1 WHERE id IN ($ids_string)");
    echo "Summary successfully sent to Teams.";
} else {
    echo "Report generated. No new changes found to send.";
}
?>
