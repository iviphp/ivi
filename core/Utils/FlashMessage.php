<?php

namespace Ivi\Core\Utils;

class FlashMessage
{
    private const VALID_TYPES = ['success', 'error', 'warning', 'info'];

    /** @var callable|null Handler optionnel pour tests */
    private static $handler;

    public static function setHandler(?callable $handler): void
    {
        self::$handler = $handler;
    }

    public static function add(string $type, string $message): void
    {
        if (!in_array($type, self::VALID_TYPES, true)) {
            $type = 'info';
        }

        $message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

        if (self::$handler) {
            call_user_func(self::$handler, $type, $message);
            return;
        }

        if (!isset($_SESSION['flash_messages'])) {
            $_SESSION['flash_messages'] = [];
        }

        $_SESSION['flash_messages'][$type][] = $message;
    }

    public static function has(): bool
    {
        if (self::$handler) {
            return false;
        }
        return !empty($_SESSION['flash_messages']);
    }

    public static function get(): array
    {
        if (self::$handler) {
            return [];
        }

        $messages = [];
        foreach ($_SESSION['flash_messages'] ?? [] as $type => $list) {
            foreach ($list as $msg) {
                $messages[] = ['type' => $type, 'message' => $msg];
            }
        }

        unset($_SESSION['flash_messages']);
        return $messages;
    }

    public static function getByType(string $type): array
    {
        if (self::$handler) {
            return [];
        }

        $msgs = $_SESSION['flash_messages'][$type] ?? [];
        unset($_SESSION['flash_messages'][$type]);

        return array_map(fn($m) => ['type' => $type, 'message' => $m], $msgs);
    }

    public static function clear(): void
    {
        if (!self::$handler) {
            unset($_SESSION['flash_messages']);
        }
    }
}
