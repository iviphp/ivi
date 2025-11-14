<?php

namespace Ivi\Core\Utils;

class FlashMessage
{
    private const VALID_TYPES = ['success', 'error', 'warning', 'info'];

    /**
     * Add a flash message.
     */
    public static function add(string $type, string $message): void
    {
        if (!in_array($type, self::VALID_TYPES)) {
            $type = 'info'; // fallback sécurisé
        }

        // Protection XSS
        $message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

        if (!isset($_SESSION['flash_messages'])) {
            $_SESSION['flash_messages'] = [];
        }

        $_SESSION['flash_messages'][$type][] = $message;
    }

    /**
     * Check if any flash messages exist.
     */
    public static function has(): bool
    {
        return !empty($_SESSION['flash_messages']);
    }

    /**
     * Get all flash messages (and clear them).
     */
    public static function get(): array
    {
        if (empty($_SESSION['flash_messages'])) {
            return [];
        }

        $messages = [];

        foreach ($_SESSION['flash_messages'] as $type => $list) {
            foreach ($list as $msg) {
                $messages[] = [
                    'type' => $type,
                    'message' => $msg
                ];
            }
        }

        unset($_SESSION['flash_messages']);

        return $messages;
    }

    /**
     * Get messages only for a specific type
     */
    public static function getByType(string $type): array
    {
        if (empty($_SESSION['flash_messages'][$type])) {
            return [];
        }

        $msgs = $_SESSION['flash_messages'][$type];
        unset($_SESSION['flash_messages'][$type]);

        return array_map(fn($m) => ['type' => $type, 'message' => $m], $msgs);
    }

    /**
     * Clear all flash messages without returning them.
     */
    public static function clear(): void
    {
        unset($_SESSION['flash_messages']);
    }
}
