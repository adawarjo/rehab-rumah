<?php
session_start();
require __DIR__ . '/../config.php';
require __DIR__ . '/functions.php';

if (empty($PUBLIC_PAGE) && empty($_SESSION['user_id'])) {
    redirect('login.php');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
}
