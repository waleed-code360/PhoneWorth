<?php
require_once __DIR__ . '/../includes/calculate.php';
function test_result($label, $actual, $expected) {
    if (abs($actual - $expected) > 0.001) {
        echo "FAIL: $label expected $expected, got $actual\n";
        exit(1);
    }
    echo "PASS: $label\n";
}

$answers = [[
    'id' => 1,
    'damage_group' => 'display',
    'rule_type' => 'part_cost',
    'part_type_id' => 1,
    'cost_kind' => 'replacement',
    'part_cost_percent' => 80,
    'fallback_percent' => 20,
    'score_penalty' => 20,
    'question_text' => 'Screen'
]];
$parts = [1=>['replacement_cost'=>40000,'repair_cost'=>25000]];
$r = calculate_price(200000,5,$answers,$parts);
test_result('Screen part deduction', $r['estimated_price'],168000);
test_result('Condition score', $r['condition_score'],80);

$answers[] = [
    'id' => 2,
    'damage_group' => 'display',
    'rule_type' => 'part_cost',
    'part_type_id' => 1,
    'cost_kind' => 'replacement',
    'part_cost_percent' => 90,
    'fallback_percent' => 25,
    'score_penalty' => 25,
    'question_text' => 'Touch'
];
$r = calculate_price(200000,5,$answers,$parts);
test_result('Screen and touch do not double count', $r['estimated_price'],164000);
test_result('Overlap score', $r['condition_score'],75);

$answers = [[
    'id' => 1,
    'damage_group' => 'display',
    'rule_type' => 'part_cost',
    'part_type_id' => 1,
    'cost_kind' => 'replacement',
    'part_cost_percent' => 80,
    'fallback_percent' => 20,
    'score_penalty' => 20,
    'question_text' => 'Screen'
]];
$r = calculate_price(30000,5,$answers,[1=>['replacement_cost'=>8000,'repair_cost'=>5600]]);
test_result('Tecno sample screen deduction', $r['estimated_price'],23600);

$r = calculate_price(50000,5,$answers,[]);
test_result('Missing price fallback', $r['estimated_price'],40000);
test_result('Fallback flagged', $r['used_fallback'],1);

$r = calculate_price(1000,5,$answers,[1=>['replacement_cost'=>9000]]);
test_result('Never negative', $r['estimated_price'],0);
