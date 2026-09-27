<?php
/**
 * Protected document viewer — KYC/vehicle documents are stored OUTSIDE the
 * public uploads dir and only served through this authorized endpoint.
 */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
use App\Auth;
use App\Database;

Auth::requireAdmin();

$id = (int) ($_GET['id'] ?? 0);
$type = $_GET['t'] ?? '';

$path = null;
if ($type === 'client' && Auth::can('clients')) {
    $path = Database::value('SELECT file_path FROM client_documents WHERE id = ?', [$id]);
} elseif ($type === 'vehicle' && Auth::can('vehicles')) {
    $path = Database::value('SELECT file_path FROM vehicle_documents WHERE id = ?', [$id]);
} elseif ($type === 'contract') {
    $path = Database::value('SELECT file_path FROM contracts WHERE id = ?', [$id]);
}
if (!$path || str_contains($path, '..')) {
    http_response_code(404);
    exit('Not found.');
}
$full = UPLOAD_PATH . '/' . $path;
if (!is_file($full)) {
    http_response_code(404);
    exit('File missing.');
}
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($full);
header('Content-Type: ' . $mime);
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: inline; filename="' . basename($full) . '"');
readfile($full);
