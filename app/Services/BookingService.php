<?php
namespace App\Services;

use App\Audit;
use App\Database;
use App\Notify;

final class BookingService
{
    public static function find(int $id): ?array
    {
        return Database::one(
            'SELECT b.*, c.full_name AS client_name, c.client_no, c.phone AS client_phone,
                    c.email AS client_email,
                    v.make, v.model, v.reg_no, v.daily_rate, v.deposit AS vehicle_deposit
             FROM bookings b
             JOIN clients c ON c.id = b.client_id
             JOIN vehicles v ON v.id = b.vehicle_id
             WHERE b.id = ?',
            [$id]
        );
    }

    public static function nextRef(): string
    {
        $max = (int) Database::value(
            "SELECT MAX(CAST(SUBSTRING(ref, 4) AS UNSIGNED)) FROM bookings"
        );
        return 'BK-' . ($max + 1);
    }

    /** Amount actually paid (successful payments against the booking, any purpose except topup). */
    public static function amountPaid(int $bookingId): float
    {
        return (float) Database::value(
            "SELECT COALESCE(SUM(amount),0) FROM payments
             WHERE booking_id = ? AND status = 'successful'",
            [$bookingId]
        );
    }

    /** Outstanding rental balance (total minus successful rental/extension payments). */
    public static function outstanding(int $bookingId): float
    {
        $b = Database::one('SELECT total FROM bookings WHERE id = ?', [$bookingId]);
        if (!$b) return 0.0;
        $paid = (float) Database::value(
            "SELECT COALESCE(SUM(amount),0) FROM payments
             WHERE booking_id = ? AND status = 'successful' AND purpose IN ('rental','extension')",
            [$bookingId]
        );
        return max(0.0, round((float) $b['total'] - $paid, 2));
    }

    /**
     * Create a booking. Validates availability and computes totals server-side.
     * Returns [booking_id, error].
     */
    public static function create(
        int $clientId,
        int $vehicleId,
        string $pickup,
        string $return,
        string $source,
        ?int $createdBy,
        float $discount = 0.0,
        string $notes = ''
    ): array {
        $vehicle = VehicleService::find($vehicleId);
        if (!$vehicle) return [null, 'Vehicle not found.'];
        if (strtotime($return) <= strtotime($pickup)) {
            return [null, 'Return must be after pickup.'];
        }
        if (!VehicleService::isAvailable($vehicleId, $pickup, $return)) {
            return [null, 'Vehicle is not available for the selected dates.'];
        }

        $quote = VehicleService::quote($vehicle, $pickup, $return);
        $total = round($quote['base'] - $discount, 2);
        if ($total < 0) return [null, 'Discount cannot exceed the rental amount.'];

        Database::begin();
        try {
            $bookingId = Database::insert(
                'INSERT INTO bookings
                 (ref, client_id, vehicle_id, pickup_at, return_at, status,
                  base_amount, discount, total, deposit_required, notes, source, created_by)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
                [
                    self::nextRef(), $clientId, $vehicleId, $pickup, $return,
                    'pending', $quote['base'], $discount, $total,
                    $quote['deposit'], $notes, $source, $createdBy,
                ]
            );
            Database::run(
                'INSERT INTO deposits (booking_id, client_id, required_amount) VALUES (?,?,?)',
                [$bookingId, $clientId, $quote['deposit']]
            );
            self::recordStatus($bookingId, 'pending',
                $source === 'walk_in' ? 'Walk-in booking created' : 'Online booking request',
                $createdBy);
            Database::commit();
        } catch (\Throwable $e) {
            Database::rollback();
            error_log('Booking create failed: ' . $e->getMessage());
            return [null, 'Could not create booking. Please try again.'];
        }

        Audit::log($createdBy, 'create_booking', 'bookings', 'booking', $bookingId, null, [
            'client_id' => $clientId, 'vehicle_id' => $vehicleId,
            'pickup' => $pickup, 'return' => $return, 'total' => $total,
        ]);
        Notify::staff('booking', 'New booking ' . self::refOf($bookingId),
            'Booking created for ' . $vehicle['make'] . ' ' . $vehicle['model'],
            'admin/booking.php?id=' . $bookingId);
        return [$bookingId, null];
    }

    public static function refOf(int $bookingId): string
    {
        return (string) Database::value('SELECT ref FROM bookings WHERE id = ?', [$bookingId]);
    }

    public static function recordStatus(int $bookingId, string $status, ?string $note, ?int $userId): void
    {
        Database::run(
            'INSERT INTO booking_status_history (booking_id, status, note, changed_by)
             VALUES (?,?,?,?)',
            [$bookingId, $status, $note, $userId]
        );
    }

    /**
     * Transition a booking status with validation.
     * Valid: pending→confirmed/cancelled, confirmed→active/cancelled,
     *        active→completed/overdue, overdue→completed.
     */
    public static function setStatus(int $bookingId, string $newStatus, ?int $userId, ?string $note = null): array
    {
        $b = Database::one('SELECT * FROM bookings WHERE id = ?', [$bookingId]);
        if (!$b) return [false, 'Booking not found.'];
        $allowed = [
            'pending' => ['confirmed', 'cancelled'],
            'confirmed' => ['active', 'cancelled'],
            'active' => ['completed', 'overdue', 'cancelled'],
            'overdue' => ['completed', 'cancelled'],
        ];
        if (!in_array($newStatus, $allowed[$b['status']] ?? [], true)) {
            return [false, "Cannot move booking from {$b['status']} to {$newStatus}."];
        }
        if ($newStatus === 'confirmed') {
            if (!VehicleService::isAvailable(
                (int) $b['vehicle_id'], $b['pickup_at'], $b['return_at'], $bookingId)) {
                return [false, 'Vehicle has a conflicting booking for these dates.'];
            }
        }

        Database::begin();
        try {
            Database::run('UPDATE bookings SET status = ? WHERE id = ?', [$newStatus, $bookingId]);
            self::recordStatus($bookingId, $newStatus, $note, $userId);
            Database::commit();
        } catch (\Throwable $e) {
            Database::rollback();
            return [false, 'Status update failed.'];
        }
        VehicleService::syncStatus((int) $b['vehicle_id']);
        Audit::log($userId, 'booking_status', 'bookings', 'booking', $bookingId,
            $b['status'], $newStatus);
        return [true, null];
    }

    /** Recalculate totals after editing dates/vehicle or adding charges/discount. */
    public static function recalcTotals(int $bookingId): void
    {
        Database::run(
            'UPDATE bookings b SET
                b.additional_amount = COALESCE((SELECT SUM(amount) FROM booking_charges
                                               WHERE booking_id = b.id), 0),
                b.total = ROUND(b.base_amount
                          + COALESCE((SELECT SUM(amount) FROM booking_charges
                                      WHERE booking_id = b.id), 0)
                          - b.discount, 2)
             WHERE b.id = ?',
            [$bookingId]
        );
    }

    public static function addCharge(int $bookingId, string $label, float $amount, int $staffId): void
    {
        Database::run(
            'INSERT INTO booking_charges (booking_id, label, amount, created_by) VALUES (?,?,?,?)',
            [$bookingId, $label, $amount, $staffId]
        );
        self::recalcTotals($bookingId);
        Audit::log($staffId, 'add_charge', 'bookings', 'booking', $bookingId, null, [
            'label' => $label, 'amount' => $amount,
        ]);
    }

    public static function applyDiscount(int $bookingId, float $discount, int $staffId): array
    {
        $b = Database::one('SELECT base_amount, additional_amount FROM bookings WHERE id = ?', [$bookingId]);
        $max = (float) $b['base_amount'] + (float) $b['additional_amount'];
        if ($discount < 0 || $discount > $max) {
            return [false, 'Invalid discount amount.'];
        }
        Database::run('UPDATE bookings SET discount = ? WHERE id = ?', [$discount, $bookingId]);
        self::recalcTotals($bookingId);
        Audit::log($staffId, 'apply_discount', 'bookings', 'booking', $bookingId, null, $discount);
        return [true, null];
    }

    /** Extensions */
    public static function requestExtension(int $bookingId, string $newReturn, ?int $requestedBy): array
    {
        $b = Database::one('SELECT * FROM bookings WHERE id = ?', [$bookingId]);
        if (!$b) return [null, 'Booking not found.'];
        if (!in_array($b['status'], ['active', 'confirmed'], true)) {
            return [null, 'Extensions only apply to active/confirmed bookings.'];
        }
        if (strtotime($newReturn) <= strtotime($b['return_at'])) {
            return [null, 'New return date must be later than the current return date.'];
        }
        $vehicle = VehicleService::find((int) $b['vehicle_id']);
        $days = (int) ceil((strtotime($newReturn) - strtotime($b['return_at'])) / 86400);
        $additional = round($days * (float) $vehicle['daily_rate'], 2);

        $id = Database::insert(
            'INSERT INTO booking_extensions
             (booking_id, old_return_at, new_return_at, additional_amount, requested_by)
             VALUES (?,?,?,?,?)',
            [$bookingId, $b['return_at'], $newReturn, $additional, $requestedBy]
        );
        Notify::staff('extension', 'Extension requested ' . self::refOf($bookingId),
            "Requested return: $newReturn (+" . money($additional) . ")",
            'admin/booking.php?id=' . $bookingId);
        return [$id, null];
    }

    public static function decideExtension(int $extensionId, bool $approve, int $staffId, ?string $note = null): array
    {
        $ext = Database::one(
            'SELECT e.*, b.vehicle_id, b.ref FROM booking_extensions e
             JOIN bookings b ON b.id = e.booking_id WHERE e.id = ?',
            [$extensionId]
        );
        if (!$ext || $ext['status'] !== 'pending') return [false, 'Extension not found.'];

        if ($approve) {
            // Re-check availability for the extended window.
            if (!VehicleService::isAvailable(
                (int) $ext['vehicle_id'], $ext['old_return_at'], $ext['new_return_at'], (int) $ext['booking_id'])) {
                return [false, 'Vehicle is booked during the requested extension period.'];
            }
            Database::begin();
            try {
                Database::run(
                    "UPDATE booking_extensions SET status='approved', decided_by=?, decided_at=NOW(), note=? WHERE id=?",
                    [$staffId, $note, $extensionId]
                );
                Database::run('UPDATE bookings SET return_at = ? WHERE id = ?',
                    [$ext['new_return_at'], $ext['booking_id']]);
                // Extra rental is a booking charge so totals stay consistent.
                Database::run(
                    'INSERT INTO booking_charges (booking_id, label, amount, created_by) VALUES (?,?,?,?)',
                    [$ext['booking_id'], 'Extension to ' . date('d M Y', strtotime($ext['new_return_at'])),
                     $ext['additional_amount'], $staffId]
                );
                self::recalcTotals((int) $ext['booking_id']);
                Database::commit();
            } catch (\Throwable $e) {
                Database::rollback();
                return [false, 'Could not approve extension.'];
            }
        } else {
            Database::run(
                "UPDATE booking_extensions SET status='rejected', decided_by=?, decided_at=NOW(), note=? WHERE id=?",
                [$staffId, $note, $extensionId]
            );
        }
        Audit::log($staffId, $approve ? 'approve_extension' : 'reject_extension',
            'bookings', 'booking', (int) $ext['booking_id'], null, $note);
        return [true, null];
    }

    /** Flag bookings past return date as overdue. Call from dashboard/cron. */
    public static function flagOverdue(): void
    {
        Database::run(
            "UPDATE bookings SET status = 'overdue'
             WHERE status = 'active' AND return_at < NOW()"
        );
        Database::run(
            "INSERT INTO booking_status_history (booking_id, status, note)
             SELECT id, 'overdue', 'Auto-flagged overdue' FROM bookings
             WHERE status = 'overdue' AND id NOT IN
               (SELECT booking_id FROM booking_status_history WHERE status = 'overdue')"
        );
    }
}
