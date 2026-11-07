<?php
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/db.php';
require_admin($pdo);

// This flag only controls whether the menu link is visible.
// admins.php checks owner permissions again before doing anything.
$_SESSION['is_owner'] = is_owner($pdo);
