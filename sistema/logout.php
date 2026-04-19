<?php
require __DIR__ . '/includes/auth.php';

if (!isAuthenticated()) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: inicio.php');
    exit;
}

$csrfToken = (string) ($_POST['csrf_token'] ?? '');
if (!lsis_csrf_validate_token('logout_form', $csrfToken)) {
    header('Location: inicio.php');
    exit;
}

logout();

header('Location: login.php?m=logout');
exit;
