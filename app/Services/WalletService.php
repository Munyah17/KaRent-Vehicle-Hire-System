<?php
namespace App\Services;

use App\Audit;
use App\Database;

/**
 * Wallet = ledger of signed transactions. Balance is always derived, never edited.
 */
final class WalletService
{
    public static function walletFor(int $clientId): int
    {
        $id = Database::value('SELECT id FROM wallets WHERE client_id = ?', [$clientId]);
        if ($id) return (int) $id;
        return Database::insert('INSERT INTO wallets (client_id) VALUES (?)', [$clientId]);
    }

    public static function balance(int $clientId): float
    {
        return (float) Database::value(
            'SELECT COALESCE(SUM(t.amount),0) FROM wallet_transactions t
             JOIN wallets w ON w.id = t.wallet_id WHERE w.client_id = ?',
            [$clientId]
        );
    }

    public static function transactions(int $clientId, int $limit = 50): array
    {
        return Database::all(
            'SELECT t.* FROM wallet_transactions t
             JOIN wallets w ON w.id = t.wallet_id
             WHERE w.client_id = ? ORDER BY t.id DESC LIMIT ?',
            [$clientId, $limit]
        );
    }

    /** Internal: append to ledger. Negative amount = debit. */
    public static function post(
        int $clientId, string $type, float $amount, ?string $description,
        ?int $paymentId = null, ?int $bookingId = null, ?int $staffId = null
    ): int {
        $walletId = self::walletFor($clientId);
        $ref = 'WT-' . strtoupper(bin2hex(random_bytes(5)));
        return Database::insert(
            'INSERT INTO wallet_transactions
             (wallet_id, ref, type, amount, description, payment_id, booking_id, created_by)
             VALUES (?,?,?,?,?,?,?,?)',
            [$walletId, $ref, $type, $amount, $description, $paymentId, $bookingId, $staffId]
        );
    }

    /**
     * Debit the wallet (e.g. paying a booking). May be called inside an outer
     * transaction (PaymentService::record) — the row lock still holds for the
     * duration of that transaction.
     */
    public static function debit(int $clientId, float $amount, string $description,
                                 ?int $bookingId = null, ?int $staffId = null): array
    {
        $walletId = self::walletFor($clientId);
        $ownTransaction = !Database::pdo()->inTransaction();
        if ($ownTransaction) Database::begin();
        try {
            // Lock the wallet row to keep the balance check + debit consistent.
            Database::run('SELECT id FROM wallets WHERE id = ? FOR UPDATE', [$walletId]);
            $balance = (float) Database::value(
                'SELECT COALESCE(SUM(amount),0) FROM wallet_transactions WHERE wallet_id = ?',
                [$walletId]
            );
            if ($balance < $amount) {
                if ($ownTransaction) Database::rollback();
                return [false, 'Insufficient wallet balance.'];
            }
            self::post($clientId, 'booking_payment', -abs($amount), $description, null, $bookingId, $staffId);
            if ($ownTransaction) Database::commit();
        } catch (\Throwable $e) {
            if ($ownTransaction) Database::rollback();
            return [false, 'Wallet transaction failed.'];
        }
        Audit::log($staffId, 'wallet_debit', 'wallets', 'wallet', $walletId, null, -$amount);
        return [true, null];
    }

    public static function credit(int $clientId, float $amount, string $type,
                                  string $description, ?int $paymentId = null,
                                  ?int $bookingId = null, ?int $staffId = null): void
    {
        self::post($clientId, $type, abs($amount), $description, $paymentId, $bookingId, $staffId);
        Audit::log($staffId, 'wallet_credit', 'wallets', null, null, null, $amount);
    }
}
