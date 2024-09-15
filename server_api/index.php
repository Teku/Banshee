<?php
include 'database.php';

$method = $_SERVER['REQUEST_METHOD'];
$endpoint = $_SERVER['REQUEST_URI'];
$params = $_GET;

// Skip logging the empty requests
if (empty($params)) {
    // Do not log the request, but continue to display the HTML below
} else {
    // Insert the request into the SQLite database
    $stmt = $db->prepare('INSERT INTO requests (method, endpoint, params) VALUES (:method, :endpoint, :params)');
    $stmt->bindValue(':method', $method, SQLITE3_TEXT);
    $stmt->bindValue(':endpoint', $endpoint, SQLITE3_TEXT);
    $stmt->bindValue(':params', json_encode($params), SQLITE3_TEXT);
    $stmt->execute();
}

if ($method == 'GET' && isset($params['action'])) {
    $action = $params['action'];

    if ($action == 'check_id' && isset($params['id'])) {
        $device_id = $params['id'];

        // Validate the ID format (simple alphanumeric check, adjust as needed)
        if (!preg_match('/^[a-zA-Z0-9]+$/', $device_id)) {
            http_response_code(400);
            echo "error";
            exit();
        }

        // Check if the ID already exists in the device_ids table
        $stmt = $db->prepare('SELECT COUNT(*) as count FROM device_ids WHERE device_id = :device_id');
        $stmt->bindValue(':device_id', $device_id, SQLITE3_TEXT);
        $result = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

        if ($result['count'] > 0) {
            // Update the last_seen timestamp for the existing device
            $current_time = date('Y-m-d H:i:s');
            $stmt = $db->prepare('UPDATE device_ids SET last_seen = :last_seen WHERE device_id = :device_id');
            $stmt->bindValue(':last_seen', $current_time, SQLITE3_TEXT);
            $stmt->bindValue(':device_id', $device_id, SQLITE3_TEXT);
            $stmt->execute();

            http_response_code(200);
            echo "exists";
        } else {
            http_response_code(404);
            echo "not_found";
        }
        exit();
    } elseif ($action == 'get_id' && isset($params['id'])) {
        $device_id = $params['id'];

        // Validate the ID format (simple alphanumeric check, adjust as needed)
        if (!preg_match('/^[a-zA-Z0-9]+$/', $device_id)) {
            http_response_code(400);
            echo "error";
            exit();
        }

        // Check if the ID already exists in the device_ids table
        $stmt = $db->prepare('SELECT COUNT(*) as count FROM device_ids WHERE device_id = :device_id');
        $stmt->bindValue(':device_id', $device_id, SQLITE3_TEXT);
        $result = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

        if ($result['count'] > 0) {
            // ID already exists, need to get a new one
            http_response_code(200);
            echo "exists";
        } else {
            // If the ID is not found, insert it into the device_ids table
            $current_time = date('Y-m-d H:i:s');
            $stmt = $db->prepare('INSERT INTO device_ids (device_id, last_seen) VALUES (:device_id, :last_seen)');
            $stmt->bindValue(':device_id', $device_id, SQLITE3_TEXT);
            $stmt->bindValue(':last_seen', $current_time, SQLITE3_TEXT);
            if ($stmt->execute()) {
                http_response_code(200);
                echo "added";
            } else {
                http_response_code(500);
                echo "error";
            }
        }
        exit();
    } elseif ($action == 'check_config' && isset($params['id']) && isset($params['ip'])) {
        $device_id = $params['id'];
        $current_ip = $params['ip'];

        // Validate the ID format
        if (!preg_match('/^[a-zA-Z0-9]+$/', $device_id)) {
            http_response_code(400);
            echo "error";
            exit();
        }

        // Fetch the device configuration from the database
        $config = getDeviceConfig($device_id, $current_ip);

        http_response_code(200);
        echo json_encode($config);
        exit();
    }
}

function getDeviceConfig($device_id, $current_ip) {
    global $db;
    
    $stmt = $db->prepare('SELECT use_static_ip, static_ip, gateway, subnet, dns1, dns2 FROM device_configs WHERE device_id = :device_id');
    $stmt->bindValue(':device_id', $device_id, SQLITE3_TEXT);
    $result = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    
    if ($result) {
        return [
            "use_static_ip" => (bool)$result['use_static_ip'],
            "static_ip" => explode('.', $result['static_ip']),
            "gateway" => explode('.', $result['gateway']),
            "subnet" => explode('.', $result['subnet']),
            "dns1" => explode('.', $result['dns1']),
            "dns2" => explode('.', $result['dns2'])
        ];
    } else {
        // Return default configuration if no specific config is found
        return [
            "use_static_ip" => false,
            "static_ip" => [192, 168, 1, 100],
            "gateway" => [192, 168, 1, 1],
            "subnet" => [255, 255, 255, 0],
            "dns1" => [8, 8, 8, 8],
            "dns2" => [8, 8, 4, 4]
        ];
    }
}

?>

<!DOCTYPE html>
<html lang="en" class="h-full bg-gray-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PHP Device Logger</title>
    <link href="https://unpkg.com/tailwindcss@^2/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="h-full">
    <div class="min-h-full">
        <nav class="bg-gray-800">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between h-16">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <h1 class="text-white font-bold text-xl">PHP Device Logger</h1>
                        </div>
                        <div class="hidden md:block">
                            <div class="ml-10 flex items-baseline space-x-4">
                                <a href="#" class="bg-gray-900 text-white rounded-md px-3 py-2 text-sm font-medium">Dashboard</a>
                                <a href="request_logs.php" class="text-gray-300 hover:bg-gray-700 hover:text-white rounded-md px-3 py-2 text-sm font-medium">Request Logs</a>
                                <a href="device_manager.php" class="text-gray-300 hover:bg-gray-700 hover:text-white rounded-md px-3 py-2 text-sm font-medium">Manage Devices</a>
                            </div>
                        </div>
                    </div>
                    <div class="md:hidden">
                        <button type="button" class="mobile-menu-button bg-gray-800 inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-gray-800 focus:ring-white" aria-controls="mobile-menu" aria-expanded="false">
                            <span class="sr-only">Open main menu</span>
                            <svg class="block h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                            <svg class="hidden h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Mobile menu, show/hide based on menu state. -->
            <div class="md:hidden hidden" id="mobile-menu">
                <div class="px-2 pt-2 pb-3 space-y-1 sm:px-3">
                    <a href="#" class="bg-gray-900 text-white block rounded-md px-3 py-2 text-base font-medium">Dashboard</a>
                    <a href="request_logs.php" class="text-gray-300 hover:bg-gray-700 hover:text-white block rounded-md px-3 py-2 text-base font-medium">Request Logs</a>
                    <a href="device_manager.php" class="text-gray-300 hover:bg-gray-700 hover:text-white block rounded-md px-3 py-2 text-base font-medium">Manage Devices</a>
                </div>
            </div>
        </nav>

        <script>
            // Mobile menu toggle
            document.querySelector('.mobile-menu-button').addEventListener('click', function() {
                document.querySelector('#mobile-menu').classList.toggle('hidden');
            });
        </script>

        <header class="bg-white shadow">
            <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                <h2 class="text-3xl font-bold tracking-tight text-gray-900">Registered Devices</h2>
            </div>
        </header>

        <main>
            <div class="mx-auto max-w-7xl py-6 sm:px-6 lg:px-8">
                <div id="output-device-ids" class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3"></div>
            </div>
        </main>
    </div>

    <script>
        function fetchDeviceIDs() {
            let xhr = new XMLHttpRequest();
            xhr.open('GET', 'fetch_device_ids.php', true);

            xhr.onreadystatechange = function () {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    let deviceIDs = JSON.parse(xhr.responseText);
                    let output = document.getElementById('output-device-ids');
                    output.innerHTML = ''; // Clear the previous entries

                    deviceIDs.forEach(function(id) {
                        let lastSeen = new Date(id.last_seen);
                        let now = new Date();
                        let diffMinutes = Math.floor((now - lastSeen) / 60000);
                        let isActive = diffMinutes < 5;

                        let card = document.createElement('div');
                        card.className = 'bg-white overflow-hidden shadow rounded-lg flex';
                        card.innerHTML = `
                            <div class="flex-shrink-0 flex items-center justify-center w-16 bg-${isActive ? 'green' : 'red'}-100 border-r border-gray-200">
                                <div class="w-3 h-3 rounded-full bg-${isActive ? 'green' : 'red'}-500"></div>
                            </div>
                            <div class="flex-grow flex flex-col">
                                <div class="px-4 py-5 sm:p-6 flex-grow">
                                    ${id.description ? `<h2 class="text-xl font-bold text-gray-900 mb-2">${id.description}</h2>` : ''}
                                    <h3 class="text-lg font-medium leading-6 text-gray-900">Device ID: ${id.device_id}</h3>
                                    <div class="mt-2 max-w-xl text-sm text-gray-500">
                                        <p>Last Seen: ${id.last_seen}</p>
                                        <p>Status: ${isActive ? 'Active' : 'Inactive'}</p>
                                    </div>
                                </div>
                                <div class="bg-gray-50 px-4 py-4 sm:px-6 mt-auto">
                                    <div class="text-xs">
                                        <p class="font-medium text-gray-600">Added: ${id.timestamp}</p>
                                    </div>
                                </div>
                            </div>
                        `;
                        output.appendChild(card);
                    });
                }
            };

            xhr.send();
        }

        // Polling the server every 5 seconds
        setInterval(fetchDeviceIDs, 5000);

        // Initial fetch
        fetchDeviceIDs();
    </script>
</body>
</html>