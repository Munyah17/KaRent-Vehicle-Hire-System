<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
use App\Auth;

$portal = ($_GET['portal'] ?? '') === 'admin' ? 'admin/login.php' : 'login.php';
Auth::logout();
flash('success', 'You have been signed out.');
redirect($portal);
