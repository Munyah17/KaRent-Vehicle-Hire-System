<?php
/**
 * Shared helper functions: escaping, redirects, flash messages, formatting.
 */
use App\Database;

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function config(string $key, $default = null)
{
    $segments = explode('.', $key);
    $value = $GLOBALS['config'];
    foreach ($segments as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }
        $value = $value[$segment];
    }
    return $value;
}

function url(string $path = ''): string
{
    return rtrim((string) config('app_url'), '/') . '/' . ltrim($path, '/');
}

function redirect(string $path): never
{
    if (!str_starts_with($path, 'http')) {
        $path = url($path);
    }
    header('Location: ' . $path);
    exit;
}

function flash(string $key, ?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['_flash'][$key] = $message;
        return null;
    }
    $value = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);
    return $value;
}

function old(string $key, string $default = ''): string
{
    $value = $_SESSION['_old'][$key] ?? $default;
    return e((string) $value);
}

function remember_old(array $input): void
{
    $_SESSION['_old'] = $input;
}

function clear_old(): void
{
    unset($_SESSION['_old']);
}

function money($amount): string
{
    return (string) config('currency_symbol', '$') . number_format((float) $amount, 2);
}

function fmt_date($value, string $format = 'd M Y'): string
{
    if (empty($value)) return '—';
    $ts = is_numeric($value) ? (int) $value : strtotime((string) $value);
    return $ts ? date($format, $ts) : '—';
}

function fmt_datetime($value): string
{
    return fmt_date($value, 'd M Y, H:i');
}

function setting(string $key, ?string $default = null): ?string
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (Database::all('SELECT `key`, `value` FROM settings') as $row) {
            $cache[$row['key']] = $row['value'];
        }
    }
    return $cache[$key] ?? $default;
}

function set_setting(string $key, string $value): void
{
    Database::run(
        'INSERT INTO settings (`key`, `value`) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)',
        [$key, $value]
    );
}

function status_badge(string $status): string
{
    $map = [
        'available' => 'bg-green-100 text-green-800',
        'reserved' => 'bg-amber-100 text-amber-800',
        'on_hire' => 'bg-blue-100 text-blue-800',
        'maintenance' => 'bg-red-100 text-red-800',
        'unavailable' => 'bg-gray-200 text-gray-700',
        'pending' => 'bg-amber-100 text-amber-800',
        'confirmed' => 'bg-blue-100 text-blue-800',
        'active' => 'bg-green-100 text-green-800',
        'completed' => 'bg-gray-200 text-gray-700',
        'cancelled' => 'bg-red-100 text-red-800',
        'overdue' => 'bg-red-100 text-red-800',
        'paid' => 'bg-green-100 text-green-800',
        'successful' => 'bg-green-100 text-green-800',
        'failed' => 'bg-red-100 text-red-800',
        'refunded' => 'bg-purple-100 text-purple-800',
        'verified' => 'bg-green-100 text-green-800',
        'under_review' => 'bg-amber-100 text-amber-800',
        'rejected' => 'bg-red-100 text-red-800',
        'approved' => 'bg-green-100 text-green-800',
        'partial' => 'bg-amber-100 text-amber-800',
        'held' => 'bg-blue-100 text-blue-800',
        'released' => 'bg-gray-200 text-gray-700',
    ];
    $cls = $map[strtolower($status)] ?? 'bg-gray-200 text-gray-700';
    $label = ucwords(str_replace('_', ' ', $status));
    return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium '
        . $cls . '">' . e($label) . '</span>';
}

/** Paginate: returns [rows, total, pages, page] */
function paginate(string $baseSql, string $countSql, array $params, int $page, int $perPage = 15): array
{
    $page = max(1, $page);
    $total = (int) Database::value($countSql, $params);
    $pages = max(1, (int) ceil($total / $perPage));
    $page = min($page, $pages);
    $offset = ($page - 1) * $perPage;
    $rows = Database::all($baseSql . " LIMIT $perPage OFFSET $offset", $params);
    return [$rows, $total, $pages, $page];
}

function pagination_links(int $page, int $pages, string $baseUrl): string
{
    if ($pages <= 1) return '';
    $html = '<div class="flex items-center gap-1">';
    for ($i = 1; $i <= $pages; $i++) {
        $cls = $i === $page
            ? 'bg-blue-600 text-white'
            : 'bg-white text-gray-600 hover:bg-gray-50 border border-gray-300';
        $html .= '<a href="' . e($baseUrl . '&page=' . $i)
            . '" class="px-3 py-1.5 rounded-md text-sm ' . $cls . '">' . $i . '</a>';
    }
    return $html . '</div>';
}

function upload_file(array $file, string $destDir, array $allowedTypes): array
{
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return [null, 'No file uploaded.'];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return [null, 'Upload failed (error ' . $file['error'] . ').'];
    }
    $max = (int) config('uploads.max_bytes', 5 * 1024 * 1024);
    if ($file['size'] > $max) {
        return [null, 'File exceeds maximum size of ' . round($max / 1048576) . 'MB.'];
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!in_array($mime, $allowedTypes, true)) {
        return [null, 'Invalid file type (' . $mime . ').'];
    }
    $ext = match ($mime) {
        'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp',
        'application/pdf' => 'pdf', default => 'bin',
    };
    if (!is_dir($destDir) && !mkdir($destDir, 0755, true)) {
        return [null, 'Could not create upload directory.'];
    }
    $name = bin2hex(random_bytes(16)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $destDir . '/' . $name)) {
        return [null, 'Could not store uploaded file.'];
    }
    return [$name, null];
}

function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}
