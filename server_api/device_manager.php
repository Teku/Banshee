<?php
include 'database.php';

// Handle form submissions for updating, deleting a device, or deleting config
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update'])) {
        $device_id = $_POST['device_id'];
        $description = $_POST['description'];
        $use_static_ip = isset($_POST['use_static_ip']) ? 1 : 0;
        $static_ip = $_POST['static_ip'];
        $gateway = $_POST['gateway'];
        $subnet = $_POST['subnet'];
        $dns1 = $_POST['dns1'];
        $dns2 = $_POST['dns2'];

        // Update the device description
        $stmt = $db->prepare('UPDATE device_ids SET description = :description WHERE device_id = :device_id');
        $stmt->bindValue(':description', $description, SQLITE3_TEXT);
        $stmt->bindValue(':device_id', $device_id, SQLITE3_TEXT);
        $stmt->execute();

        // Update or insert the device configuration
        $stmt = $db->prepare('INSERT OR REPLACE INTO device_configs 
            (device_id, use_static_ip, static_ip, gateway, subnet, dns1, dns2) 
            VALUES (:device_id, :use_static_ip, :static_ip, :gateway, :subnet, :dns1, :dns2)');
        $stmt->bindValue(':device_id', $device_id, SQLITE3_TEXT);
        $stmt->bindValue(':use_static_ip', $use_static_ip, SQLITE3_INTEGER);
        $stmt->bindValue(':static_ip', $static_ip, SQLITE3_TEXT);
        $stmt->bindValue(':gateway', $gateway, SQLITE3_TEXT);
        $stmt->bindValue(':subnet', $subnet, SQLITE3_TEXT);
        $stmt->bindValue(':dns1', $dns1, SQLITE3_TEXT);
        $stmt->bindValue(':dns2', $dns2, SQLITE3_TEXT);
        $stmt->execute();
    } elseif (isset($_POST['delete'])) {
        $device_id = $_POST['device_id'];

        // Delete the device
        $stmt = $db->prepare('DELETE FROM device_ids WHERE device_id = :device_id');
        $stmt->bindValue(':device_id', $device_id, SQLITE3_TEXT);
        $stmt->execute();

        // Delete the device configuration
        $stmt = $db->prepare('DELETE FROM device_configs WHERE device_id = :device_id');
        $stmt->bindValue(':device_id', $device_id, SQLITE3_TEXT);
        $stmt->execute();
    } elseif (isset($_POST['delete_config'])) {
        $device_id = $_POST['device_id'];

        // Delete the device configuration
        $stmt = $db->prepare('DELETE FROM device_configs WHERE device_id = :device_id');
        $stmt->bindValue(':device_id', $device_id, SQLITE3_TEXT);
        $stmt->execute();
    }
}

// Fetch all device IDs and their configurations
$results = $db->query('SELECT d.*, c.use_static_ip, c.static_ip, c.gateway, c.subnet, c.dns1, c.dns2 
                       FROM device_ids d 
                       LEFT JOIN device_configs c ON d.device_id = c.device_id 
                       ORDER BY d.last_seen DESC');

$deviceIDs = [];

while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
    $deviceIDs[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Device Manager</title>
</head>
<body>
    <h1>Device Manager</h1>
    <table border="1">
        <tr>
            <th>Device ID</th>
            <th>Last Seen</th>
            <th>Description</th>
            <th>Use Static IP</th>
            <th>Static IP</th>
            <th>Gateway</th>
            <th>Subnet</th>
            <th>DNS1</th>
            <th>DNS2</th>
            <th>Actions</th>
        </tr>
        <?php foreach ($deviceIDs as $device): ?>
            <tr>
                <form method="POST">
                    <td><?php echo htmlentities($device['device_id'], ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?></td>
                    <td><?php echo htmlentities($device['last_seen'], ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?></td>
                    <td>
                        <input type="text" name="description" value="<?php echo htmlentities($device['description'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>" />
                    </td>
                    <td>
                        <input type="checkbox" name="use_static_ip" <?php echo ($device['use_static_ip'] ?? 0) ? 'checked' : ''; ?> />
                    </td>
                    <td>
                        <input type="text" name="static_ip" value="<?php echo htmlentities($device['static_ip'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>" />
                    </td>
                    <td>
                        <input type="text" name="gateway" value="<?php echo htmlentities($device['gateway'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>" />
                    </td>
                    <td>
                        <input type="text" name="subnet" value="<?php echo htmlentities($device['subnet'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>" />
                    </td>
                    <td>
                        <input type="text" name="dns1" value="<?php echo htmlentities($device['dns1'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>" />
                    </td>
                    <td>
                        <input type="text" name="dns2" value="<?php echo htmlentities($device['dns2'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>" />
                    </td>
                    <td>
                        <input type="hidden" name="device_id" value="<?php echo htmlentities($device['device_id'], ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>" />
                        <input type="submit" name="update" value="Update" />
                        <input type="submit" name="delete" value="Delete Device" onclick="return confirm('Are you sure you want to delete this device?');" />
                        <input type="submit" name="delete_config" value="Delete Config" onclick="return confirm('Are you sure you want to delete this device\'s configuration?');" />
                    </td>
                </form>
            </tr>
        <?php endforeach; ?>
    </table>
    <p><a href="index.php">Back to Device Logger</a></p>
</body>
</html>
