<?php
include 'database.php';

// Fetch all device IDs
$results = $db->query('SELECT * FROM device_ids ORDER BY timestamp DESC');

$deviceIDs = [];

while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
    $deviceIDs[] = $row;
}

// Return the device IDs as JSON
echo json_encode($deviceIDs);
?>
