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
<html lang="en" class="h-full bg-gray-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Device Manager</title>
    <link href="https://unpkg.com/tailwindcss@^2/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="h-full">
    <div class="min-h-full">
        <nav class="bg-gray-800">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="flex h-16 items-center justify-between">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <h1 class="text-white font-bold text-xl">PHP Device Logger</h1>
                        </div>
                        <div class="hidden md:block">
                            <div class="ml-10 flex items-baseline space-x-4">
                                <a href="index.php" class="text-gray-300 hover:bg-gray-700 hover:text-white rounded-md px-3 py-2 text-sm font-medium">Dashboard</a>
                                <a href="request_logs.php" class="text-gray-300 hover:bg-gray-700 hover:text-white rounded-md px-3 py-2 text-sm font-medium">Request Logs</a>
                                <a href="#" class="bg-gray-900 text-white rounded-md px-3 py-2 text-sm font-medium">Manage Devices</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </nav>

        <header class="bg-white shadow">
            <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                <h2 class="text-3xl font-bold tracking-tight text-gray-900">Device Manager</h2>
            </div>
        </header>

        <main>
            <div class="mx-auto max-w-7xl py-6 sm:px-6 lg:px-8">
                <div class="bg-white shadow overflow-hidden sm:rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider p-4">Device ID</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider p-4">Last Seen</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider p-4">Description</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider p-4">Use Static IP</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider p-4">Network Config</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider p-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($deviceIDs as $device): ?>
                                <tr>
                                    <form method="POST">
                                        <td class="p-4"><?php echo htmlentities($device['device_id'], ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?></td>
                                        <td class="p-4"><?php echo htmlentities($device['last_seen'], ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?></td>
                                        <td class="p-4">
                                            <input type="text" name="description" value="<?php echo htmlentities($device['description'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>" class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md" />
                                        </td>
                                        <td class="p-4">
                                            <input type="checkbox" name="use_static_ip" <?php echo ($device['use_static_ip'] ?? 0) ? 'checked' : ''; ?> class="form-checkbox h-4 w-4 text-indigo-600 transition duration-150 ease-in-out" />
                                        </td>
                                        <td class="p-4">
                                            <div class="space-y-2">
                                                <input type="text" name="static_ip" value="<?php echo htmlentities($device['static_ip'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>" placeholder="Static IP" class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md" />
                                                <input type="text" name="gateway" value="<?php echo htmlentities($device['gateway'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>" placeholder="Gateway" class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md" />
                                                <input type="text" name="subnet" value="<?php echo htmlentities($device['subnet'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>" placeholder="Subnet" class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md" />
                                                <input type="text" name="dns1" value="<?php echo htmlentities($device['dns1'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>" placeholder="DNS1" class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md" />
                                                <input type="text" name="dns2" value="<?php echo htmlentities($device['dns2'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>" placeholder="DNS2" class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md" />
                                            </div>
                                        </td>
                                        <td class="p-4">
                                            <div class="space-y-2 w-full">
                                                <input type="hidden" name="device_id" value="<?php echo htmlentities($device['device_id'], ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>" />
                                                <input type="submit" name="update" value="Update" class="w-full inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" />
                                                <input type="submit" name="delete" value="Delete Device" onclick="return confirm('Are you sure you want to delete this device?');" class="w-full inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500" />
                                                <input type="submit" name="delete_config" value="Delete Config" onclick="return confirm('Are you sure you want to delete this device\'s configuration?');" class="w-full inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500" />
                                            </div>
                                        </td>
                                    </form>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
