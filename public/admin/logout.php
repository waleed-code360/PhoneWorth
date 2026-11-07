<?php
require_once __DIR__ . '/../../includes/functions.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Use the logout form.');
}
check_csrf();
unset($_SESSION['admin_id'], $_SESSION['admin_name'], $_SESSION['is_owner'], $_SESSION['admin_password_hash']);
session_regenerate_id(true);
go('login.php');
