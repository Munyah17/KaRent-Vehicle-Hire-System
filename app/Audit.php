<?php
namespace App;

final class Audit
{
    public static function log(
        ?int $userId,
        string $action,
        string $module,
        ?string $recordType = null,
        ?int $recordId = null,
        $oldValue = null,
        $newValue = null
    ): void {
        Database::run(
            'INSERT INTO audit_logs
             (user_id, action, module, record_type, record_id, old_value, new_value, ip)
             VALUES (?,?,?,?,?,?,?,?)',
            [
                $userId, $action, $module, $recordType, $recordId,
                $oldValue === null ? null : (is_string($oldValue) ? $oldValue : json_encode($oldValue)),
                $newValue === null ? null : (is_string($newValue) ? $newValue : json_encode($newValue)),
                client_ip(),
            ]
        );
    }
}
