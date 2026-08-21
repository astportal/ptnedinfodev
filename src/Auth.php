<?php

class Auth
{
    public static function start(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }

    public static function check(): bool
    {
        self::start();
        return !empty($_SESSION['user_id']);
    }

    public static function requireLogin(): void
    {
        self::start();
        if (empty($_SESSION['user_id'])) {
            header('Location: login.php');
            exit;
        }
    }

    public static function attempt(PDO $db, string $username, string $password): bool
    {
        $stmt = $db->prepare('SELECT id, password_hash, display_name FROM users WHERE username = :u');
        $stmt->execute(['u' => $username]);
        $user = $stmt->fetch();
        if (!$user || !password_verify($password, $user['password_hash'])) {
            return false;
        }
        self::start();
        session_regenerate_id(true);
        $_SESSION['user_id']      = $user['id'];
        $_SESSION['display_name'] = $user['display_name'];
        return true;
    }

    public static function logout(): void
    {
        self::start();
        $_SESSION = [];
        session_destroy();
    }

    public static function userId(): ?int
    {
        self::start();
        return $_SESSION['user_id'] ?? null;
    }

    public static function displayName(): string
    {
        self::start();
        return $_SESSION['display_name'] ?? '';
    }
}
