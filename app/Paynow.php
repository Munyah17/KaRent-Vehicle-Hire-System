<?php
namespace App;

use Paynow\Core\StatusResponse;
use Paynow\Payments\HashMismatchException;

/**
 * Paynow (Zimbabwe) gateway integration — wraps the official
 * paynow/Paynow-PHP-SDK vendored in app/Vendor/paynow.
 *
 * Flow: createPayment -> send -> redirect client to the Paynow browser URL.
 * Confirmation happens ONLY server-side: processStatusUpdate() verifies the
 * signed callback hash, and pollStatus() re-checks directly with Paynow.
 * Client-side "success" is never trusted.
 */
final class Paynow
{
    private string $id;
    private string $key;
    private string $resultUrl;
    private string $returnUrl;
    public bool $testMode;

    public function __construct()
    {
        $cfg = $GLOBALS['config']['paynow'] ?? [];
        $this->id = (string) ($cfg['integration_id'] ?? '');
        $this->key = (string) ($cfg['integration_key'] ?? '');
        $base = rtrim((string) ($GLOBALS['config']['app_url'] ?? ''), '/');
        $this->resultUrl = ($cfg['result_url'] ?? '') ?: $base . '/paynow/result.php';
        $this->returnUrl = ($cfg['return_url'] ?? '') ?: $base . '/paynow/return.php';
        $this->testMode = (bool) ($cfg['test_mode'] ?? true);
    }

    public function configured(): bool
    {
        return $this->id !== '' && $this->key !== '';
    }

    /** The underlying official SDK client. */
    private function sdk(): \Paynow\Payments\Paynow
    {
        return new \Paynow\Payments\Paynow(
            $this->id, $this->key, $this->returnUrl, $this->resultUrl
        );
    }

    /**
     * Initiate a transaction.
     * Returns ['ok'=>bool, 'browser_url'=>?, 'poll_url'=>?, 'error'=>?]
     * In test mode without credentials it routes to the dev simulator so the
     * full lifecycle can be exercised offline.
     */
    public function init(string $reference, float $amount, string $email, string $description): array
    {
        if (!$this->configured()) {
            if ($this->testMode) {
                return [
                    'ok' => true,
                    'browser_url' => url('paynow/simulate.php?ref=' . urlencode($reference)),
                    'poll_url' => url('paynow/simulate-status.php?ref=' . urlencode($reference)),
                    'error' => null,
                    'simulated' => true,
                ];
            }
            return ['ok' => false, 'error' => 'Paynow is not configured.'];
        }

        try {
            $paynow = $this->sdk();
            $payment = $paynow->createPayment(
                $reference,
                filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : 'no-reply@' .
                    parse_url($GLOBALS['config']['app_url'] ?? 'localhost', PHP_URL_HOST)
            );
            $payment->add($description !== '' ? $description : 'Vehicle hire payment', $amount);
            if ($description !== '') {
                $payment->setDescription($description);
            }

            $response = $paynow->send($payment);
            if ($response->success()) {
                return [
                    'ok' => true,
                    'browser_url' => $response->redirectUrl(),
                    'poll_url' => $response->pollUrl(),
                    'error' => null,
                ];
            }
            return ['ok' => false, 'error' => $response->errors() ?: 'Payment initiation failed.'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'Paynow error: ' . $e->getMessage()];
        }
    }

    /** Verify a transaction's status directly against Paynow's poll URL. */
    public function pollStatus(string $pollUrl): array
    {
        if (str_contains($pollUrl, 'simulate-status.php')) {
            $status = Database::value(
                'SELECT status FROM payments WHERE txn_id = ?',
                [basename(parse_url($pollUrl, PHP_URL_QUERY) ?? '')]
            );
            return ['ok' => true, 'status' => $status ?: 'pending', 'paid' => $status === 'successful'];
        }
        try {
            $status = $this->sdk()->pollTransaction($pollUrl);
            return [
                'ok' => true,
                'status' => strtolower($status->status()),
                'paid' => $status->paid(),
                'paynow_reference' => $status->paynowReference(),
            ];
        } catch (HashMismatchException $e) {
            return ['ok' => false, 'status' => 'hash_mismatch'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'status' => 'unknown', 'error' => $e->getMessage()];
        }
    }

    /**
     * Verify the server-to-server status update POSTed to result.php.
     * Uses the SDK's own hash verification — returns the parsed status, or
     * null when the signature cannot be trusted.
     */
    public function processStatusUpdate(): ?StatusResponse
    {
        try {
            return $this->sdk()->processStatusUpdate();
        } catch (\Throwable $e) {
            return null;
        }
    }
}
