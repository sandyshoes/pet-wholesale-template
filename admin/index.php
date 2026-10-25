<?php
require_once __DIR__ . '/auth.php';

if (is_admin_logged_in()) {
    header('Location: dashboard.php');
} else {
    header('Location: login.php');
}
exit;
