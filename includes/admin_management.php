<?php



function add_admin_account($pdo, $nameInput, $emailInput, $password, $confirmation) {
    $name = field_text($nameInput, 'Name', 100);

    if (!is_string($emailInput)) {
        throw new Exception('Enter a valid email address.');
    }

    $email = strtolower(trim($emailInput));
    if (strlen($email) > 150 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Enter a valid email address (max 150 characters).');
    }

    if (!is_string($password) || !is_string($confirmation)) {
        throw new Exception('Enter a valid password.');
    }

    if (strlen($password) < 10 || strlen($password) > 72) {
        throw new Exception('Password must have 10 to 72 characters.');
    }

    if (strpos($password, "\0") !== false) {
        throw new Exception('Password contains an invalid character.');
    }

    if ($password !== $confirmation) {
        throw new Exception('Passwords do not match.');
    }

    
    $existing = one($pdo, 'SELECT id FROM users WHERE email = ?', [$email]);
    if ($existing) {
        throw new Exception('This email already exists. Reactivate the account below if needed.');
    }

    
    $hash = password_hash($password, PASSWORD_DEFAULT);

    execute_sql(
        $pdo,
        "INSERT INTO users (name, email, password_hash, role, is_active)
         VALUES (?, ?, ?, 'admin', 1)",
        [$name, $email, $hash]
    );
}

function change_admin_status($pdo, $idInput, $loggedInId) {
    $id = field_int($idInput, 'Admin ID');

    
    if ($id === (int) $loggedInId) {
        throw new Exception('You cannot deactivate your own account.');
    }

    $target = one(
        $pdo,
        "SELECT id, email, is_active FROM users WHERE id = ? AND role = 'admin'",
        [$id]
    );

    if (!$target) {
        throw new Exception('Admin account not found.');
    }

    
    if (
        defined('PHONEWORTH_OWNER_EMAIL') &&
        strcasecmp($target['email'], PHONEWORTH_OWNER_EMAIL) === 0
    ) {
        throw new Exception('The project owner cannot be deactivated.');
    }

    
    $newStatus = (int) $target['is_active'] === 1 ? 0 : 1;

    execute_sql(
        $pdo,
        "UPDATE users SET is_active = ? WHERE id = ? AND role = 'admin'",
        [$newStatus, $id]
    );

    return $newStatus;
}


function reset_admin_password($pdo, $idInput, $loggedInId, $password, $confirmation) {
    $id = field_int($idInput, 'Admin ID');
    if ($id === (int) $loggedInId) {
        throw new Exception('Change your own password from My Account.');
    }

    $admin = one($pdo,
        "SELECT id, email FROM users WHERE id=? AND role='admin'",
        [$id]
    );
    if (!$admin) {
        throw new Exception('Admin not found.');
    }

    if (defined('PHONEWORTH_OWNER_EMAIL') &&
        strcasecmp($admin['email'], PHONEWORTH_OWNER_EMAIL) === 0) {
        throw new Exception('Reset the owner password from My Account.');
    }

    if (!is_string($password) || !is_string($confirmation) ||
        strlen($password) < 10 || strlen($password) > 72 ||
        strpos($password, "\0") !== false) {
        throw new Exception('Password must have 10 to 72 valid characters.');
    }
    if ($password !== $confirmation) {
        throw new Exception('Passwords do not match.');
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    execute_sql($pdo,
        "UPDATE users SET password_hash=? WHERE id=? AND role='admin'",
        [$hash, $id]
    );
}
