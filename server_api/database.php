<?php
// database.php

$db = new SQLite3('requests.db');

// Create table for logging requests if it doesn't exist
$db->exec("CREATE TABLE IF NOT EXISTS requests (
    id INTEGER PRIMARY KEY,
    method TEXT,
    endpoint TEXT,
    params TEXT,
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
)");

// Create table for storing device IDs if it doesn't exist
$db->exec("CREATE TABLE IF NOT EXISTS device_ids (
    id INTEGER PRIMARY KEY,
    device_id TEXT UNIQUE,
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_seen DATETIME,
    description TEXT
)");

// Create table for storing device configurations if it doesn't exist
$db->exec("CREATE TABLE IF NOT EXISTS device_configs (
    device_id TEXT PRIMARY KEY,
    use_static_ip INTEGER,
    static_ip TEXT,
    gateway TEXT,
    subnet TEXT,
    dns1 TEXT,
    dns2 TEXT,
    FOREIGN KEY (device_id) REFERENCES device_ids(device_id)
)");
?>