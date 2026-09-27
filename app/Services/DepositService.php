<?php
namespace App\Services;

use App\Audit;
use App\Database;

final class DepositService
{
    public static function forBooking(int $bookingId): ?array
    {
        return Database::one('SELECT * FROM deposits WHERE booking_id = ?', [$bookingId]);
    }

    /** Called inside PaymentService transaction when a deposit payment succeeds. */
    public static function recordReceipt(int $bookingId, float $amount, ?int $paymentId, ?int $staffId): void
    {
        $deposit = self::forBooking($bookingId);
        if (!$deposit) return;
        Database::run(
            "INSERT INTO deposit_transactions (deposit_id, type, amount, reason, payment_id, created_by)
             VALUES (?,'received',?,'Deposit collected',?,?)",
            [$deposit['id'], $amount, $paymentId, $staffId]
        );
        self::recalc((int) $deposit['id']);
    }

    public static function deduct(int $depositId, float $amount, string $reason, int $staffId): array
    {
        $d = Database::one('SELECT * FROM deposits WHERE id = ?', [$depositId]);
        if (!$d) return [false, 'Deposit not found.'];
        $available = (float) $d['received_amount'] - (float) $d['deducted_amount'] - (float) $d['refunded_amount'];
        if ($amount <= 0 || $amount > $available) {
            return [false, 'Deduction exceeds available deposit balance.'];
        }
        Database::run(
            "INSERT INTO deposit_transactions (deposit_id, type, amount, reason, created_by)
             VALUES (?,'deduction',?,?,?)",
            [$depositId, $amount, $reason, $staffId]
        );
        self::recalc($depositId);
        Audit::log($staffId, 'deposit_deduct', 'deposits', 'deposit', $depositId, null, [
            'amount' => $amount, 'reason' => $reason,
        ]);
        return [true, null];
    }

    public static function refund(int $depositId, float $amount, string $reason,
                                  int $staffId, string $refundMethod = 'external'): array
    {
        $d = Database::one('SELECT * FROM deposits WHERE id = ?', [$depositId]);
        if (!$d) return [false, 'Deposit not found.'];
        $available = (float) $d['received_amount'] - (float) $d['deducted_amount'] - (float) $d['refunded_amount'];
        if ($amount <= 0 || $amount > $available) {
            return [false, 'Refund exceeds available deposit balance.'];
        }
        Database::begin();
        try {
            Database::run(
                "INSERT INTO deposit_transactions (deposit_id, type, amount, reason, created_by)
                 VALUES (?,'refund',?,?,?)",
                [$depositId, $amount, $reason ?: 'Deposit refund', $staffId]
            );
            self::recalc($depositId);
            if ($refundMethod === 'wallet') {
                $booking = Database::one('SELECT b.*, b.client_id FROM bookings b JOIN deposits dd ON dd.booking_id=b.id WHERE dd.id=?', [$depositId]);
                WalletService::credit((int) $booking['client_id'], $amount,
                    'refund', 'Deposit refund ' . ($booking['ref'] ?? ''),
                    null, (int) $booking['id'], $staffId);
            }
            Database::commit();
        } catch (\Throwable $e) {
            Database::rollback();
            return [false, 'Refund failed.'];
        }
        Audit::log($staffId, 'deposit_refund', 'deposits', 'deposit', $depositId, null, [
            'amount' => $amount, 'reason' => $reason, 'method' => $refundMethod,
        ]);
        return [true, null];
    }

    public static function recalc(int $depositId): void
    {
        Database::run(
            "UPDATE deposits d SET
                d.received_amount = COALESCE((SELECT SUM(amount) FROM deposit_transactions
                     WHERE deposit_id = d.id AND type = 'received'),0),
                d.deducted_amount = COALESCE((SELECT SUM(amount) FROM deposit_transactions
                     WHERE deposit_id = d.id AND type = 'deduction'),0),
                d.refunded_amount = COALESCE((SELECT SUM(amount) FROM deposit_transactions
                     WHERE deposit_id = d.id AND type = 'refund'),0),
                d.status = CASE
                    WHEN COALESCE((SELECT SUM(amount) FROM deposit_transactions
                          WHERE deposit_id = d.id AND type = 'received'),0) <= 0
                        THEN 'pending'
                    WHEN COALESCE((SELECT SUM(amount) FROM deposit_transactions
                          WHERE deposit_id = d.id AND type = 'received'),0)
                       - COALESCE((SELECT SUM(amount) FROM deposit_transactions
                          WHERE deposit_id = d.id AND type IN ('deduction','refund')),0) <= 0
                        THEN 'released'
                    WHEN COALESCE((SELECT SUM(amount) FROM deposit_transactions
                          WHERE deposit_id = d.id AND type = 'received'),0) < d.required_amount
                        THEN 'partial'
                    ELSE 'held'
                END
             WHERE d.id = ?",
            [$depositId]
        );
    }
}
