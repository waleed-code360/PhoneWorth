<?php
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/db.php';
require_admin($pdo);



$_SESSION['is_owner'] = is_owner($pdo);
