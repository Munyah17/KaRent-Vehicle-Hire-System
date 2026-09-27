<?php
namespace App;

final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . self::token() . '">';
    }

    public static function verify(): void
    {
        $token = $_POST['_csrf'] ?? '';
        if (!is_string($token) || empty($_SESSION['_csrf'])
            || !hash_equals($_SESSION['_csrf'], $token)) {
            http_response_code(419);
            exit('Invalid or expired form token. Please go back and try again.');
        }
    }
}
