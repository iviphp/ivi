<?php
// bootstrap/dev_errors.php
declare(strict_types=1);

// Convert warnings/notice to exceptions (respect @)
set_error_handler(function (int $severity, string $message, ?string $file = null, ?int $line = null) {
    if (!(error_reporting() & $severity)) return false;
    throw new ErrorException($message, 0, $severity, $file ?? 'unknown', $line ?? 0);
});

// Uncaught exceptions → Logger::exception (no exit)
set_exception_handler(function (Throwable $e) {
    $context = [
        '_SERVER' => [
            'REQUEST_METHOD'  => $_SERVER['REQUEST_METHOD'] ?? null,
            'REQUEST_URI'     => $_SERVER['REQUEST_URI'] ?? null,
            'HTTP_HOST'       => $_SERVER['HTTP_HOST'] ?? null,
            'HTTP_USER_AGENT' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'REMOTE_ADDR'     => $_SERVER['REMOTE_ADDR'] ?? null,
        ],
        '_GET'  => $_GET  ?? [],
        '_POST' => $_POST ?? [],
    ];

    // Use the pretty Logger
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    \Ivi\Core\Debug\Logger::exception($e, $context, ['exit' => false]);
});

// Fatal errors (parse/require/etc.) → Logger::exception (no exit)
register_shutdown_function(function () {
    $err = error_get_last();
    if (!$err) return;

    $fatal = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (!in_array($err['type'] ?? 0, $fatal, true)) return;

    $e = new ErrorException($err['message'] ?? 'Fatal error', 0, $err['type'] ?? E_ERROR, $err['file'] ?? 'unknown', $err['line'] ?? 0);

    $context = [
        '_SERVER' => [
            'REQUEST_METHOD'  => $_SERVER['REQUEST_METHOD'] ?? null,
            'REQUEST_URI'     => $_SERVER['REQUEST_URI'] ?? null,
            'HTTP_HOST'       => $_SERVER['HTTP_HOST'] ?? null,
            'HTTP_USER_AGENT' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'REMOTE_ADDR'     => $_SERVER['REMOTE_ADDR'] ?? null,
        ],
        '_GET'  => $_GET  ?? [],
        '_POST' => $_POST ?? [],
    ];

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    \Ivi\Core\Debug\Logger::exception($e, $context, ['exit' => false]);
});
