<?php
/**
 * Paynow result URL — server-to-server confirmation endpoint.
 * The Paynow-PHP-SDK verifies the callback hash; only a verified status
 * update may mark a payment successful. Client-side success is never trusted.
 */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
use App\Database;
use App\Paynow;
use App\Services\PaymentService;

$reference = $_POST['reference'] ?? '';
$payment = Database::one('SELECT * FROM payments WHERE txn_id = ?', [$reference]);
if (!$payment) {
    http_response_code(404);
    exit('Unknown reference');
}
if ($payment['status'] === 'successful') {
    exit('OK'); // idempotent — duplicate callbacks are safe
}

$paynow = new Paynow();

if ($paynow->configured()) {
    // SDK path: processStatusUpdate() verifies the Paynow hash signature.
    $status = $paynow->processStatusUpdate();
    if ($status === null) {
        error_log('Paynow callback hash verification failed for ' . $reference);
        http_response_code(400);
        exit('Invalid signature');
    }
    PaymentService::markGatewayResult($reference, $status->status());
    echo 'OK';
    exit;
}

// DEV/TEST mode without credentials: verify the locally-computed signature.
$concat = '';
foreach ($_POST as $k => $v) {
    if (strtolower($k) !== 'hash') $concat .= $v;
}
$expected = strtoupper(hash('sha512', $concat . ($GLOBALS['config']['paynow']['integration_key'] ?? '')));
$valid = isset($_POST['hash']) && hash_equals($expected, strtoupper((string) $_POST['hash']));

if (!$valid) {
    http_response_code(400);
    exit('Invalid hash');
}
PaymentService::markGatewayResult($reference, strtolower($_POST['status'] ?? ''));
echo 'OK';
