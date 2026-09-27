<?php
namespace App;

/**
 * Authentication, roles and permission checks.
 */
final class Auth
{
    private static ?array $user = null;

    public static function attempt(string $email, string $password): array
    {
        $email = strtolower(trim($email));
        self::throttleCheck($email);

        $user = Database::one(
            'SELECT u.*, r.name AS role_name FROM users u
             JOIN roles r ON r.id = u.role_id WHERE u.email = ?',
            [$email]
        );

        if (!$user || !password_verify($password, $user['password_hash'])) {
            self::recordFailedAttempt($email);
            return [false, 'Invalid email or password.'];
        }
        if ($user['status'] !== 'active') {
            return [false, 'This account is ' . $user['status'] . '. Please contact support.'];
        }

        self::clearAttempts($email);
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        self::$user = $user;
        Audit::log((int) $user['id'], 'login', 'auth', 'user', (int) $user['id']);
        return [true, null];
    }

    public static function user(): ?array
    {
        if (self::$user === null && !empty($_SESSION['user_id'])) {
            self::$user = Database::one(
                'SELECT u.*, r.name AS role_name FROM users u
                 JOIN roles r ON r.id = u.role_id WHERE u.id = ?',
                [$_SESSION['user_id']]
            );
            if (!self::$user || self::$user['status'] !== 'active') {
                self::logout();
            }
        }
        return self::$user;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function id(): ?int
    {
        $u = self::user();
        return $u ? (int) $u['id'] : null;
    }

    public static function role(): ?string
    {
        $u = self::user();
        return $u['role_name'] ?? null;
    }

    public static function isAdmin(): bool
    {
        return in_array(self::role(), ['SUPER_ADMIN', 'STAFF'], true);
    }

    public static function isClient(): bool
    {
        return self::role() === 'CLIENT';
    }

    /** Permission check. SUPER_ADMIN can do everything. */
    public static function can(string $permission): bool
    {
        $user = self::user();
        if (!$user) return false;
        if ($user['role_name'] === 'SUPER_ADMIN') return true;
        if ($user['role_name'] === 'CLIENT') return false;

        // Per-user override wins, else role default.
        $permId = Database::value('SELECT id FROM permissions WHERE code = ?', [$permission]);
        if (!$permId) return false;

        $override = Database::value(
            'SELECT allowed FROM user_permissions WHERE user_id = ? AND permission_id = ?',
            [$user['id'], $permId]
        );
        if ($override !== null && $override !== false) {
            return (bool) $override;
        }
        return (bool) Database::value(
            'SELECT 1 FROM role_permissions WHERE role_id = ? AND permission_id = ?',
            [$user['role_id'], $permId]
        );
    }

    /** Client record linked to the logged-in user (if a client). */
    public static function client(): ?array
    {
        $user = self::user();
        if (!$user) return null;
        return Database::one('SELECT * FROM clients WHERE user_id = ?', [$user['id']]);
    }

    public static function logout(): void
    {
        self::$user = null;
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], '', $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    // ---- Route guards ----

    public static function requireLogin(): void
    {
        if (!self::check()) {
            flash('error', 'Please log in to continue.');
            redirect('login.php');
        }
        $user = self::user();
        if (!empty($user['must_change_password'])
            && basename($_SERVER['SCRIPT_NAME']) !== 'change-password.php') {
            redirect('change-password.php');
        }
    }

    public static function requireAdmin(): void
    {
        self::requireLogin();
        if (!self::isAdmin()) {
            http_response_code(403);
            exit('Access denied.');
        }
    }

    public static function requireClient(): void
    {
        self::requireLogin();
        if (!self::isClient()) {
            http_response_code(403);
            exit('Access denied.');
        }
    }

    public static function requirePermission(string $permission): void
    {
        self::requireAdmin();
        if (!self::can($permission)) {
            http_response_code(403);
            exit('Access denied: missing permission "' . e($permission) . '".');
        }
    }

    // ---- Password helpers ----

    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    public static function generateTempPassword(): string
    {
        return 'Vh' . bin2hex(random_bytes(4)) . '!' . random_int(10, 99);
    }

    // ---- Simple rate limiting for logins ----

    private static function throttleCheck(string $email): void
    {
        $recent = (int) Database::value(
            'SELECT COUNT(*) FROM login_attempts
             WHERE email = ? AND ip = ? AND attempted_at > (NOW() - INTERVAL 10 MINUTE)
             AND successful = 0',
            [$email, client_ip()]
        );
        if ($recent >= 8) {
            sleep(2);
            if ($recent >= 15) {
                exit('Too many login attempts. Please try again later.');
            }
        }
    }

    private static function recordFailedAttempt(string $email): void
    {
        Database::run(
            'INSERT INTO login_attempts (email, ip, successful) VALUES (?, ?, 0)',
            [$email, client_ip()]
        );
    }

    private static function clearAttempts(string $email): void
    {
        Database::run('DELETE FROM login_attempts WHERE email = ? OR ip = ?', [$email, client_ip()]);
    }
}
