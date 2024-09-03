<?php
include 'database.php';

// Fetch all logged requests
$results = $db->query('SELECT * FROM requests ORDER BY timestamp DESC');

$requests = [];

while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
    $requests[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Request Logs</title>
</head>
<body>
    <h1>Request Logs</h1>
    <div id="logs">
        <?php foreach ($requests as $request): ?>
            <div>
                <strong><?php echo htmlspecialchars($request['timestamp']); ?></strong> - 
                <?php echo htmlspecialchars($request['method']); ?> 
                <?php echo htmlspecialchars($request['endpoint']); ?> - 
                Params: <?php echo htmlspecialchars($request['params']); ?>
            </div>
        <?php endforeach; ?>
    </div>
    <p><a href="index.php">Back to Device Logger</a></p>
</body>
</html>
