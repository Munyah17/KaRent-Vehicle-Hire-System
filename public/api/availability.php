<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
use App\Auth;
use App\Services\VehicleService;

header('Content-Type: application/json');
Auth::requireLogin();

$vehicleId = (int) ($_GET['vehicle'] ?? 0);
$pickup = trim(($_GET['pickup'] ?? '') . ' ' . ($_GET['pickup_time'] ?? '09:00'));
$return = trim(($_GET['return'] ?? '') . ' ' . ($_GET['return_time'] ?? '17:00'));

$vehicle = VehicleService::find($vehicleId);
if (!$vehicle) { echo json_encode(['available' => false, 'message' => 'Vehicle not found']); exit; }
if (strtotime($return) <= strtotime($pickup)) {
    echo json_encode(['available' => false, 'message' => 'Return must be after pickup']); exit;
}

$available = VehicleService::isAvailable($vehicleId, $pickup, $return);
$quote = VehicleService::quote($vehicle, $pickup, $return);

echo json_encode([
    'available' => $available,
    'message' => $available ? '' : 'Vehicle not available for selected dates',
    'days' => $quote['days'],
    'base' => $quote['base'],
    'base_fmt' => money($quote['base']),
    'deposit' => $quote['deposit'],
    'deposit_fmt' => money($quote['deposit']),
]);
