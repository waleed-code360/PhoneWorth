<?php
// First owner account setup. Run ONLY from terminal, never from a browser.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Terminal only.\n");
}
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$existing = one($pdo, "SELECT COUNT(*) AS total FROM users WHERE role = 'admin'");
if ((int) $existing['total'] !== 0) {
    exit("An administrator already exists. Use the owner's Manage Admins page.\n");
}

$name = trim(readline('Owner name: '));
$email = strtolower(trim(readline('Owner email: ')));
if ($name === '' || strlen($name) > 100 ||
    !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
    exit("Invalid name or email.\n");
}
if (!defined('PHONEWORTH_OWNER_EMAIL') ||
    strcasecmp($email, PHONEWORTH_OWNER_EMAIL) !== 0) {
    exit("Email must match PHONEWORTH_OWNER_EMAIL in config/database.php.\n");
}

// Hide typed passwords on Unix terminals. Do not pass passwords on the command line.
function hidden_password($label) {
    echo $label;
    if (function_exists('shell_exec')) { shell_exec('stty -echo'); }
    $password = trim(fgets(STDIN));
    if (function_exists('shell_exec')) { shell_exec('stty echo'); }
    echo "\n";
    return $password;
}
$password = hidden_password('New password (10-72 characters): ');
$again = hidden_password('Confirm password: ');
if (strlen($password) < 10 || strlen($password) > 72 || $password !== $again) {
    exit("Password length invalid or confirmation differs.\n");
}
$hash = password_hash($password, PASSWORD_DEFAULT);
execute_sql($pdo,
    "INSERT INTO users (name, email, password_hash, role, is_active)
     VALUES (?, ?, ?, 'admin', 1)",
    [$name, $email, $hash]
);
echo "Owner created. You can now sign in at /admin/login.php.\n";
