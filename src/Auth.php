<?php

declare(strict_types=1);

namespace App;

final class Auth
{
    public function __construct(private readonly \PDO $db)
    {
    }

    public function attemptLogin(string $username, string $password): bool
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if (!$user || (int) $user['active'] !== 1 || !password_verify($password, $user['password_hash'])) {
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        return true;
    }

    public function logout(): void
    {
        $_SESSION = [];
        session_destroy();
    }

    public function isLoggedIn(): bool
    {
        return isset($_SESSION['user_id']);
    }

    public function isAdmin(): bool
    {
        return $this->isLoggedIn() && ($_SESSION['role'] ?? null) === 'admin';
    }

    public function currentUsername(): ?string
    {
        return $_SESSION['username'] ?? null;
    }

    public function currentUserId(): ?int
    {
        return $_SESSION['user_id'] ?? null;
    }

    public function requireLogin(): void
    {
        if (!$this->isLoggedIn()) {
            header('Location: index.php?action=login');
            exit;
        }
    }

    public function requireAdmin(): void
    {
        $this->requireLogin();
        if (!$this->isAdmin()) {
            http_response_code(403);
            echo 'Zugriff verweigert — nur für Administratoren.';
            exit;
        }
    }

    public function csrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public function checkCsrf(?string $token): void
    {
        if (!$token || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            http_response_code(400);
            echo 'Sicherheitsprüfung fehlgeschlagen (ungültiges Formular-Token). Bitte Seite neu laden.';
            exit;
        }
    }

    public function createUser(string $username, string $password, string $role): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO users (username, password_hash, role, active, created_at) VALUES (?, ?, ?, 1, ?)'
        );
        $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), $role, gmdate('Y-m-d')]);
    }

    public function listUsers(): array
    {
        return $this->db->query('SELECT id, username, role, active, created_at FROM users ORDER BY username')->fetchAll();
    }

    public function setUserActive(int $id, bool $active): void
    {
        $stmt = $this->db->prepare('UPDATE users SET active = ? WHERE id = ?');
        $stmt->execute([$active ? 1 : 0, $id]);
    }
}
