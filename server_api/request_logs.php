<?php
include 'database.php';

// Fetch the 25 most recent logged requests
$results = $db->query('SELECT * FROM requests ORDER BY timestamp DESC LIMIT 25');

$requests = [];

while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
    $requests[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en" class="h-full bg-gray-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Logs</title>
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
                                <a href="#" class="bg-gray-900 text-white rounded-md px-3 py-2 text-sm font-medium">Request Logs</a>
                                <a href="device_manager.php" class="text-gray-300 hover:bg-gray-700 hover:text-white rounded-md px-3 py-2 text-sm font-medium">Manage Devices</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </nav>

        <header class="bg-white shadow">
            <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                <h2 class="text-3xl font-bold tracking-tight text-gray-900">Request Logs</h2>
            </div>
        </header>

        <main>
            <div class="mx-auto max-w-7xl py-6 sm:px-6 lg:px-8">
                <div class="bg-white shadow overflow-hidden sm:rounded-lg">
                    <ul class="divide-y divide-gray-200">
                        <?php foreach ($requests as $request): ?>
                            <li class="px-6 py-4">
                                <div class="flex items-center">
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-medium text-gray-900 truncate">
                                            <?php echo htmlspecialchars($request['timestamp']); ?> - 
                                            <?php echo htmlspecialchars($request['method']); ?> 
                                            <?php echo htmlspecialchars($request['endpoint']); ?>
                                        </p>
                                        <p class="text-sm text-gray-500 truncate">
                                            Params: <?php echo htmlspecialchars($request['params']); ?>
                                        </p>
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
