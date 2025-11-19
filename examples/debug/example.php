<?php

/**
 * IVI Logger Example
 *
 * This script demonstrates the usage of the global logging system in ivi.php.
 * It covers:
 *   - Basic logging (info)
 *   - Logging with label
 *   - Different log levels (info, debug, warning, error)
 *   - Logging complex data
 *   - JSON output
 *   - Trace output
 *   - Automatic folder creation and daily log rotation
 *   - Old log cleanup (>30 days)
 *
 * Usage:
 *   php example_logger.php
 *
 * All logs will be written to BASE_PATH/logs/debug-YYYY-MM-DD.log by default.
 */
define('IVI_CLI_TEST', true);

if (PHP_SAPI !== 'cli') {
    die("This logger example should be run from CLI only.\n");
}

if (ob_get_level()) {
    ob_end_clean();
}


defined('BASE_PATH') || define('BASE_PATH', dirname(__DIR__, 2));

require_once BASE_PATH . '/vendor/autoload.php';
require_once BASE_PATH . '/bootstrap/app_bootstrap.php';

\Ivi\Core\Bootstrap\Loader::bootstrap(BASE_PATH);


echo "=== IVI Logger Example ===\n\n";

// -------------------------------
// 1) Basic info log
// -------------------------------
log_msg("Application started");
log_info("Homepage loaded successfully");

// -------------------------------
// 2) Logging with label
// -------------------------------
log_msg("User logged in", "Auth");

// -------------------------------
// 3) Different log levels
// -------------------------------
log_info("Everything is fine");
log_debug(["internal" => "value"], "Debugging");
log_warning("Missing data detected", "Validation");
log_error("Failed to connect to database", "Database");

// -------------------------------
// 4) Logging complex data
// -------------------------------
$user = [
    'id' => 123,
    'name' => 'Gaspard',
    'roles' => ['admin', 'editor']
];

// Normal log
log_msg($user, "User");

// JSON log
log_msg($user, "User JSON", "info", true);

// Debug with trace
log_msg($user, "User Trace", "debug", false, true);

// -------------------------------
// 5) Automatic folder creation & daily rotation
// -------------------------------
log_info("Testing automatic log folder creation and daily rotation");

// -------------------------------
// 6) Shortcuts usage
// -------------------------------
log_info("Shortcut info log");
log_debug("Shortcut debug log");
log_warning("Shortcut warning log");
log_error("Shortcut error log");

echo "\n=== Logs have been written to " . BASE_PATH . "/logs/debug-" . date('Y-m-d') . ".log ===\n";
