<?php
/** DEV-ONLY poll endpoint used by the simulator flow. */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
use App\Database;

$paynow = new \App\Paynow();
if (!$paynow->testMode || $paynow->configured()) { http_response_code(404); exit; }

$ref = $_GET['ref'] ?? '';
$status = Database::value('SELECT status FROM payments WHERE txn_id = ?', [$ref]) ?: 'pending';
echo 'status=' . ($status === 'successful' ? 'Paid' : ucfirst($status));
