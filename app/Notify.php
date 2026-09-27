<?php
namespace App;

/**
 * Notification infrastructure. Stores in-app notifications; email/SMS/WhatsApp
 * channels can be plugged in later via the `channel`/`status` fields.
 */
final class Notify
{
    public static function send(?int $userId, string $type, string $title, string $body = '', ?string $link = null): void
    {
        Database::run(
            'INSERT INTO notifications (user_id, type, title, body, link, channel, status)
             VALUES (?,?,?,?,?,?,?)',
            [$userId, $type, $title, $body, $link, 'in_app', 'unread']
        );
    }

    /** Notify all staff holding a permission (or super admins). */
    public static function staff(string $type, string $title, string $body = '', ?string $link = null): void
    {
        $staff = Database::all(
            "SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id
             WHERE r.name IN ('SUPER_ADMIN','STAFF') AND u.status = 'active'"
        );
        foreach ($staff as $s) {
            self::send((int) $s['id'], $type, $title, $body, $link);
        }
    }

    public static function unreadCount(int $userId): int
    {
        return (int) Database::value(
            "SELECT COUNT(*) FROM notifications WHERE user_id = ? AND status = 'unread'",
            [$userId]
        );
    }
}
