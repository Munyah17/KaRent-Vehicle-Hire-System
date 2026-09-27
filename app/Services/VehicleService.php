<?php
namespace App\Services;

use App\Database;

final class VehicleService
{
    public static function find(int $id): ?array
    {
        return Database::one('SELECT * FROM vehicles WHERE id = ?', [$id]);
    }

    /** Primary public photo path, or a placeholder. */
    public static function photo(int $vehicleId): string
    {
        $path = Database::value(
            'SELECT file_path FROM vehicle_photos WHERE vehicle_id = ?
             ORDER BY is_primary DESC, sort_order ASC LIMIT 1',
            [$vehicleId]
        );
        if ($path && file_exists(PUBLIC_UPLOAD_PATH . '/' . $path)) {
            return 'uploads/' . $path;
        }
        return 'assets/img/car-placeholder.jpg';
    }

    /**
     * A vehicle is available for [pickup, return) when:
     *  - its status is not maintenance/unavailable
     *  - no overlapping confirmed/active booking exists
     */
    public static function isAvailable(int $vehicleId, string $pickup, string $return, ?int $excludeBookingId = null): bool
    {
        $vehicle = self::find($vehicleId);
        if (!$vehicle || in_array($vehicle['status'], ['maintenance', 'unavailable'], true)) {
            return false;
        }
        $sql = "SELECT COUNT(*) FROM bookings
                WHERE vehicle_id = ?
                  AND status IN ('confirmed','active','overdue')
                  AND pickup_at < ? AND return_at > ?";
        $params = [$vehicleId, $return, $pickup];
        if ($excludeBookingId) {
            $sql .= ' AND id != ?';
            $params[] = $excludeBookingId;
        }
        return (int) Database::value($sql, $params) === 0;
    }

    /**
     * Tiered price calculation: monthly rate for 30-day blocks where set,
     * weekly for 7-day blocks, daily for the remainder.
     */
    public static function quote(array $vehicle, string $pickup, string $return): array
    {
        $days = max(1, (int) ceil((strtotime($return) - strtotime($pickup)) / 86400));

        $monthly = $vehicle['monthly_rate'] !== null ? (float) $vehicle['monthly_rate'] : null;
        $weekly  = $vehicle['weekly_rate']  !== null ? (float) $vehicle['weekly_rate']  : null;
        $daily   = (float) $vehicle['daily_rate'];

        $months = $monthly ? intdiv($days, 30) : 0;
        $rem = $days - $months * 30;
        $weeks = $weekly ? intdiv($rem, 7) : 0;
        $rem -= $weeks * 7;

        $base = $months * ($monthly ?? 0) + $weeks * ($weekly ?? 0) + $rem * $daily;

        return [
            'days' => $days,
            'base' => round($base, 2),
            'deposit' => (float) $vehicle['deposit'],
        ];
    }

    /** Recalculate a vehicle's display status from its live bookings. */
    public static function syncStatus(int $vehicleId): void
    {
        $vehicle = self::find($vehicleId);
        if (!$vehicle) return;
        if (in_array($vehicle['status'], ['maintenance', 'unavailable'], true)) return;

        $now = date('Y-m-d H:i:s');
        $active = (int) Database::value(
            "SELECT COUNT(*) FROM bookings WHERE vehicle_id = ? AND status = 'active'", [$vehicleId]
        );
        $reserved = (int) Database::value(
            "SELECT COUNT(*) FROM bookings WHERE vehicle_id = ? AND status = 'confirmed'
             AND pickup_at > ?", [$vehicleId, $now]
        );
        $status = $active > 0 ? 'on_hire' : ($reserved > 0 ? 'reserved' : 'available');
        if ($status !== $vehicle['status']) {
            Database::run('UPDATE vehicles SET status = ? WHERE id = ?', [$status, $vehicleId]);
        }
    }
}
