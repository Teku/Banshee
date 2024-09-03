# Banshee Device Management System

## Overview

Banshee is a device management system that allows you to monitor and configure ESP32-based devices remotely. It consists of two main components:

1. An ESP32 device running custom firmware (Banshee)
2. A PHP-based server API for device registration and configuration

The system allows devices to:
- Generate and register unique device IDs
- Periodically check in with the server
- Receive and apply network configuration updates

## Features

- Automatic device registration with unique IDs
- Periodic device check-ins to confirm connectivity
- Remote configuration of network settings (static IP vs DHCP)
- Web interface for viewing registered devices and their last seen times
- Logging of API requests for debugging and monitoring

## ESP32 Firmware (Banshee)

The ESP32 firmware (`banshee.ino`) handles:
- Ethernet connection setup
- Device ID generation and registration
- Periodic check-ins with the server
- Applying network configuration updates

Key functions:
- `generateAndAssignNewID()`: Creates and registers a new device ID
- `checkID()`: Verifies the validity of the current device ID
- `checkAndApplyConfig()`: Fetches and applies network configuration from the server

## Server API

The PHP-based server API (`index.php`) provides endpoints for:
- Device ID registration and validation
- Configuration management
- Logging of requests

Key endpoints:
- `?action=check_id`: Validates a device ID
- `?action=get_id`: Registers a new device ID
- `?action=check_config`: Provides network configuration for a device

## Database

The system uses SQLite (`database.php`) to store:
- Device IDs and last seen times
- Request logs
- Device configurations

## Web Interface

A simple web interface is provided to:
- View registered device IDs and their last seen times
- Access request logs
- Manage devices (via `device_manager.php`, not included in the provided code)

## Future Enhancements

- Add device location tracking using MapBox
- Implement GPS functionality for precise location data
- Integration with Laravel as a package ???

## Setup and Usage

create credentials.h file with your API endpoint
