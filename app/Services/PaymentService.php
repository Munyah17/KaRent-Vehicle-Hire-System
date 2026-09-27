<?php
namespace App\Services;

use App\Audit;
use App\Database;
use App\Notify;

final class PaymentService
{
    public static function nextTxnId(): string
    {
        $max = (int) Database::value(
            "SELECT MAX(CAST(SUBSTRING(txn_id, 5) AS UNSIGNED)) FROM payments"
        );
        return 'TXN-' . ($max + 1);
    }

    /**
     * Record a verified non-gateway payment (cash, bank transfer, wallet...).
     * For wallet payments, funds are debited from the wallet ledger.
     * Returns [payment_id, error].
     */
    public static function record(
        int $clientId,
        ?int $bookingId,
        float $amount,
        string $method,
        ?string $reference,
        string $purpose,
        ?int $staffId,
        ?string $notes = null
    ): array {
        if ($amount <= 0) return [null, 'Amount must be greater than zero.'];

        Database::begin();
        try {
            if ($method === 'wallet') {
                [$ok, $err] = WalletService::debit(
                    $clientId, $amount,
                    'Payment ' . ($bookingId ? BookingService::refOf($bookingId) : $purpose),
                    $bookingId, $staffId
                );
                if (!$ok) {
                    Database::rollback();
                    return [null, $err];
                }
            }

            $paymentId = Database::insert(
                'INSERT INTO payments
                 (txn_id, booking_id, client_id, amount, method, reference, purpose,
                  status, paid_at, staff_id, notes)
                 VALUES (?,?,?,?,?,?,?,\'successful\',NOW(),?,?)',
                [self::nextTxnId(), $bookingId, $clientId, $amount, $method,
                 $reference, $purpose, $staffId, $notes]
            );

            if ($purpose === 'deposit' && $bookingId) {
                DepositService::recordReceipt($bookingId, $amount, $paymentId, $staffId);
            }
            if ($purpose === 'topup') {
                WalletService::credit($clientId, $amount, 'topup',
                    'Wallet top-up (' . $method . ')', $paymentId, null, $staffId);
            }
            Database::commit();
        } catch (\Throwable $e) {
            Database::rollback();
            error_log('Payment record failed: ' . $e->getMessage());
            return [null, 'Could not record payment.'];
        }

        Audit::log($staffId, 'record_payment', 'payments', 'payment', $paymentId, null, [
            'amount' => $amount, 'method' => $method, 'purpose' => $purpose,
            'booking_id' => $bookingId,
        ]);
        $userId = Database::value('SELECT user_id FROM clients WHERE id = ?', [$clientId]);
        if ($userId) {
            Notify::send((int) $userId, 'payment', 'Payment received',
                money($amount) . ' received (' . $method . ').', 'client/payments.php');
        }
        FdmsService::issueForPayment($paymentId);
        return [$paymentId, null];
    }

    /**
     * Mark a gateway payment's final result and fulfil its purpose.
     * Idempotent — duplicate callbacks are safely ignored.
     */
    public static function markGatewayResult(string $txnId, string $status): void
    {
        $payment = Database::one('SELECT * FROM payments WHERE txn_id = ?', [$txnId]);
        if (!$payment || $payment['status'] === 'successful') return;

        $paid = in_array(strtolower($status), ['paid', 'successful'], true);
        Database::run(
            'UPDATE payments SET status = ?, paid_at = IF(?, NOW(), paid_at) WHERE id = ?',
            [$paid ? 'successful' : 'failed', $paid ? 1 : 0, $payment['id']]
        );
        if (!$paid) return;

        $clientId = (int) $payment['client_id'];
        $bookingId = $payment['booking_id'] ? (int) $payment['booking_id'] : null;

        if ($payment['purpose'] === 'topup') {
            WalletService::credit($clientId, (float) $payment['amount'],
                'topup', 'Wallet top-up (Paynow)', (int) $payment['id']);
        }
        if ($payment['purpose'] === 'deposit' && $bookingId) {
            DepositService::recordReceipt($bookingId, (float) $payment['amount'],
                (int) $payment['id'], null);
        }
        if ($bookingId && in_array($payment['purpose'], ['rental', 'extension'], true)) {
            $b = Database::one('SELECT status FROM bookings WHERE id = ?', [$bookingId]);
            if ($b && $b['status'] === 'pending') {
                BookingService::setStatus($bookingId, 'confirmed', null, 'Payment verified via Paynow');
            }
        }
        $userId = Database::value('SELECT user_id FROM clients WHERE id = ?', [$clientId]);
        if ($userId) {
            Notify::send((int) $userId, 'payment', 'Payment confirmed',
                money($payment['amount']) . ' received via Paynow.', 'client/payments.php');
        }
        Audit::log(null, 'paynow_callback', 'payments', 'payment', (int) $payment['id'], null, $status);
        FdmsService::issueForPayment((int) $payment['id']);
    }

    public static function statusCounts(): array
    {
        $rows = Database::all(
            'SELECT status, COUNT(*) c, COALESCE(SUM(amount),0) s FROM payments GROUP BY status'
        );
        $out = [];
        foreach ($rows as $r) $out[$r['status']] = $r;
        return $out;
    }
}
