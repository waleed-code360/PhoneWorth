<?php
if (session_status() === PHP_SESSION_NONE) {
    // Cookie is not readable by JavaScript and is limited to this website.
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax'
    ]);
    session_start();
}

// Common security headers for browser responses.
if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}


// Escape text before showing it inside HTML.
function e($text) {
    return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
}

function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf_token'];
}

function check_csrf() {
    $sent = $_POST['csrf_token'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(403);
        exit('Invalid form token. Please reload the page and try again.');
    }
}

function go($page) {
    header('Location: ' . $page);
    exit;
}

function is_admin($pdo = null) {
    if (empty($_SESSION['admin_id']) || empty($_SESSION['admin_password_hash'])) {
        return false;
    }
    if ($pdo === null) {
        return true;
    }

    // A disabled admin or one whose password was reset cannot use an old session.
    $admin = one($pdo,
        "SELECT password_hash FROM users WHERE id=? AND role='admin' AND is_active=1",
        [(int) $_SESSION['admin_id']]
    );
    return $admin && hash_equals(
        (string) $admin['password_hash'],
        (string) $_SESSION['admin_password_hash']
    );
}

function require_admin($pdo) {
    if (!is_admin($pdo)) {
        unset($_SESSION['admin_id'], $_SESSION['admin_name'], $_SESSION['is_owner'], $_SESSION['admin_password_hash']);
        go('login.php');
    }
}

// Only the admin whose email matches the private config is the owner.
// This uses the existing users table; no new database columns are needed.
function is_owner($pdo) {
    if (empty($_SESSION['admin_id']) || !defined('PHONEWORTH_OWNER_EMAIL')) {
        return false;
    }

    $ownerEmail = trim(PHONEWORTH_OWNER_EMAIL);
    if (!filter_var($ownerEmail, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $admin = one(
        $pdo,
        "SELECT email FROM users WHERE id = ? AND role = 'admin' AND is_active = 1",
        [(int) $_SESSION['admin_id']]
    );

    return $admin && strcasecmp($admin['email'], $ownerEmail) === 0;
}

function require_owner($pdo) {
    require_admin($pdo);

    if (!is_owner($pdo)) {
        http_response_code(403);
        exit('Access denied. Only the project owner can manage admins.');
    }
}

// Small helpers: make invalid form input show a clear message.
function field_text($value, $label, $max = 150) {
    if (!is_string($value)) {
        throw new Exception($label . ' must be text.');
    }
    $value = trim($value);
    if ($value === '' || strlen($value) > $max) {
        throw new Exception($label . ' is required (max ' . $max . ' characters).');
    }
    return $value;
}

function field_int($value, $label, $min = 1, $max = 1000000) {
    if (!is_scalar($value) || !preg_match('/^[0-9]+$/', (string) $value)) {
        throw new Exception('Enter a valid number for ' . $label . '.');
    }
    $number = (int) $value;
    if ($number < $min || $number > $max) {
        throw new Exception($label . ' must be between ' . $min . ' and ' . $max . '.');
    }
    return $number;
}

function field_decimal($value, $label, $min = 0, $max = 999999999) {
    if (!is_scalar($value) || !preg_match('/^[0-9]+(?:\.[0-9]{1,2})?$/', (string) $value)) {
        throw new Exception('Enter a valid amount for ' . $label . '.');
    }
    $number = (float) $value;
    if ($number < $min || $number > $max) {
        throw new Exception($label . ' must be between ' . $min . ' and ' . $max . '.');
    }
    return $number;
}


function money($amount, $currency) {
    return e($currency) . ' ' . number_format((float) $amount, 2);
}

// All SQL values are passed separately from the SQL statement.
function rows($pdo, $sql, $values = []) {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($values);
    return $stmt->fetchAll();
}

function one($pdo, $sql, $values = []) {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($values);
    return $stmt->fetch();
}

function execute_sql($pdo, $sql, $values = []) {
    $stmt = $pdo->prepare($sql);
    return $stmt->execute($values);
}

function shown_error($message) {
    return '<div class="alert error">' . e($message) . '</div>';
}

function flash($message) {
    $_SESSION['flash_message'] = $message;
}

function show_flash() {
    if (!empty($_SESSION['flash_message'])) {
        echo '<div class="alert success">' . e($_SESSION['flash_message']) . '</div>';
        unset($_SESSION['flash_message']);
    }
}
