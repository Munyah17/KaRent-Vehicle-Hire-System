<?php
namespace App\Services;

use App\Audit;
use App\Auth;
use App\Database;

final class ClientService
{
    /** Search existing clients before creating a new one (walk-in workflow). */
    public static function search(string $term): array
    {
        $like = '%' . $term . '%';
        return Database::all(
            'SELECT * FROM clients
             WHERE phone LIKE ? OR email LIKE ? OR national_id LIKE ?
                OR client_no LIKE ? OR full_name LIKE ?
             ORDER BY full_name LIMIT 20',
            [$like, $like, $like, $like, $like]
        );
    }

    public static function nextClientNo(): string
    {
        $max = (int) Database::value(
            "SELECT MAX(CAST(SUBSTRING(client_no, 4) AS UNSIGNED)) FROM clients"
        );
        return 'CL-' . ($max + 1);
    }

    /**
     * Create a walk-in client: auto-creates a CLIENT user account with a
     * temporary password that must be changed on first login.
     * Returns [client_id, temp_password].
     */
    public static function createWalkIn(array $data, int $staffId): array
    {
        // Guard against duplicates by phone/email/national ID.
        foreach (['phone', 'email', 'national_id'] as $field) {
            if (!empty($data[$field])) {
                $existing = Database::one(
                    "SELECT id FROM clients WHERE $field = ?", [$data[$field]]
                );
                if ($existing) {
                    return [(int) $existing['id'], null];
                }
            }
        }

        $tempPassword = Auth::generateTempPassword();
        $email = strtolower(trim($data['email'] ?? ''));
        if ($email === '') {
            $email = 'walkin-' . bin2hex(random_bytes(4)) . '@no-email.local';
        }

        Database::begin();
        try {
            $clientRoleId = (int) Database::value("SELECT id FROM roles WHERE name = 'CLIENT'");
            $userId = Database::insert(
                "INSERT INTO users (role_id, name, email, phone, password_hash, must_change_password)
                 VALUES (?,?,?,?,?,1)",
                [
                    $clientRoleId,
                    $data['full_name'],
                    $email,
                    $data['phone'] ?? null,
                    Auth::hashPassword($tempPassword),
                ]
            );
            $clientId = Database::insert(
                'INSERT INTO clients
                 (user_id, client_no, full_name, dob, phone, email, address,
                  national_id, licence_no, licence_expiry, source, kyc_status)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
                [
                    $userId,
                    self::nextClientNo(),
                    $data['full_name'],
                    $data['dob'] ?: null,
                    $data['phone'] ?? null,
                    $data['email'] ?? null,
                    $data['address'] ?? null,
                    $data['national_id'] ?? null,
                    $data['licence_no'] ?? null,
                    $data['licence_expiry'] ?: null,
                    'walk_in',
                    'pending',
                ]
            );
            Database::insert('INSERT INTO wallets (client_id) VALUES (?)', [$clientId]);
            Database::commit();
        } catch (\Throwable $e) {
            Database::rollback();
            throw $e;
        }

        Audit::log($staffId, 'create_walkin_client', 'clients', 'client', $clientId, null, $data);
        return [$clientId, $tempPassword];
    }

    /** Online self-registration. Returns [client_id, null] or [null, error]. */
    public static function register(string $name, string $email, string $phone, string $password): array
    {
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [null, 'Invalid email address.'];
        }
        if (strlen($password) < 8) {
            return [null, 'Password must be at least 8 characters.'];
        }
        if (Database::value('SELECT id FROM users WHERE email = ?', [$email])) {
            return [null, 'An account with this email already exists.'];
        }

        Database::begin();
        try {
            $clientRoleId = (int) Database::value("SELECT id FROM roles WHERE name = 'CLIENT'");
            $userId = Database::insert(
                'INSERT INTO users (role_id, name, email, phone, password_hash)
                 VALUES (?,?,?,?,?)',
                [$clientRoleId, $name, $email, $phone, Auth::hashPassword($password)]
            );
            $clientId = Database::insert(
                "INSERT INTO clients (user_id, client_no, full_name, phone, email, source)
                 VALUES (?,?,?,?,?, 'online')",
                [$userId, self::nextClientNo(), $name, $phone, $email]
            );
            Database::insert('INSERT INTO wallets (client_id) VALUES (?)', [$clientId]);
            Database::commit();
        } catch (\Throwable $e) {
            Database::rollback();
            return [null, 'Registration failed. Please try again.'];
        }
        Audit::log($userId, 'register', 'clients', 'client', $clientId);
        return [$clientId, null];
    }
}
