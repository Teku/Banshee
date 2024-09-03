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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>PHP Device Logger</title>
</head>
<body>
    <h1>PHP Device Logger</h1>

    <h3>Registered Device IDs:</h3>
    <div id="output-device-ids"></div>

    <script>
        // Function to fetch and display registered device IDs
        function fetchDeviceIDs() {
            let xhr = new XMLHttpRequest();
            xhr.open('GET', 'fetch_device_ids.php', true);

            xhr.onreadystatechange = function () {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    let deviceIDs = JSON.parse(xhr.responseText);
                    let output = document.getElementById('output-device-ids');
                    output.innerHTML = ''; // Clear the previous entries

                    deviceIDs.forEach(function(id) {
                        let idDiv = document.createElement('div');
                        idDiv.innerHTML = `<strong>${id.timestamp}</strong> - Device ID: ${id.device_id} - Last Seen: ${id.last_seen}`;
                        output.appendChild(idDiv);
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

    <p><a href="request_logs.php">View Request Logs</a></p>
    <p><a href="device_manager.php">Manage Devices</a></p>
</body>
</html>