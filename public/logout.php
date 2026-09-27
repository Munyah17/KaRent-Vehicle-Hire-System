<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
use App\Auth;

Auth::logout();
redirect('login.php');
