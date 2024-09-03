<?php
// fetch_entries.php

include 'database.php';

// Fetch all entries
$results = $db->query('SELECT * FROM requests ORDER BY timestamp DESC');

$entries = [];

while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
    $entries[] = $row;
}

// Return the entries as JSON
echo json_encode($entries);
