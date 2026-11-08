<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Terminal only.\n");
}
require_once __DIR__ . '/../includes/db.php';
$tables = [
    'users', 'brands', 'phone_models', 'phone_variants', 'countries',
    'market_prices', 'part_types', 'model_part_prices',
    'condition_questions', 'condition_options', 'valuations', 'valuation_answers'
];
echo "PhoneWorth database checks\n";
foreach ($tables as $table) {
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM `" . $table . "`");
    echo str_pad($table, 22) . $stmt->fetchColumn() . " rows\n";
}
echo "Owner config: " .
    ((defined('PHONEWORTH_OWNER_EMAIL') && filter_var(PHONEWORTH_OWNER_EMAIL, FILTER_VALIDATE_EMAIL))
        ? 'configured' : 'MISSING') . "\n";
