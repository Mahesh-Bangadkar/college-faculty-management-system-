<?php
require_once __DIR__ . '/config.php';

app_require_login();

$user = app_current_user();

if (($user['role'] ?? '') === 'admin') {
    app_redirect('/admin/dashboard.php');
}

if (($user['role'] ?? '') === 'faculty') {
    app_redirect('/faculty/dashboard.php');
}

app_flash('error', 'Session expired. Please log in again.');
app_redirect('/index.php');
