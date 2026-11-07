<?php
// This file contains the PRICE MATH. It does not connect to MySQL.
// All prices and rules are given to it as arrays from result.php.

function calculate_price($basePrice, $rangePercent, $answers, $partPrices) {
    $highestDeduction = []; // Largest price deduction in each damage group.
    $highestScore = [];     // Largest score penalty in each damage group.
    $fallbackUsed = false;

    foreach ($answers as $key => $answer) {
        $amount = 0;
        $rule = $answer['rule_type'];

        if ($rule === 'percentage') {
            $amount = $basePrice * (float) $answer['percentage_value'] / 100;
        }

        if ($rule === 'part_cost') {
            $partId = (int) $answer['part_type_id'];
            $kind = $answer['cost_kind']; // repair or replacement
            $cost = $partPrices[$partId][$kind . '_cost'] ?? null;

            if ($cost !== null) {
                $amount = (float) $cost * (float) $answer['part_cost_percent'] / 100;
            } else {
                $fallback = (float) $answer['fallback_percent'];
                if ($fallback <= 0) {
                    throw new Exception('Missing part cost and fallback rule for: ' . $answer['question_text']);
                }
                $amount = $basePrice * $fallback / 100;
                $fallbackUsed = true;
                $answers[$key]['used_fallback'] = true;
            }
        }

        $amount = round(max(0, $amount), 2);
        $group = $answer['damage_group'];
        $answers[$key]['applied_deduction'] = 0;
        $answers[$key]['score_penalty_applied'] = 0;

        if (!isset($highestDeduction[$group]) || $amount > $highestDeduction[$group]['amount']) {
            $highestDeduction[$group] = ['key' => $key, 'amount' => $amount];
        }
        $score = (int) $answer['score_penalty'];
        if (!isset($highestScore[$group]) || $score > $highestScore[$group]['score']) {
            $highestScore[$group] = ['key' => $key, 'score' => $score];
        }
    }

    $totalDeduction = 0;
    foreach ($highestDeduction as $winner) {
        $totalDeduction += $winner['amount'];
        $answers[$winner['key']]['applied_deduction'] = $winner['amount'];
    }

    $totalScorePenalty = 0;
    foreach ($highestScore as $winner) {
        $totalScorePenalty += $winner['score'];
        $answers[$winner['key']]['score_penalty_applied'] = $winner['score'];
    }

    $estimated = round(max(0, $basePrice - $totalDeduction), 2);
    $difference = $estimated * $rangePercent / 100;

    return [
        'base_price' => $basePrice,
        'total_deduction' => round($totalDeduction, 2),
        'estimated_price' => $estimated,
        'minimum_price' => round(max(0, $estimated - $difference), 2),
        'maximum_price' => round($estimated + $difference, 2),
        'condition_score' => max(0, 100 - $totalScorePenalty),
        'used_fallback' => $fallbackUsed ? 1 : 0,
        'answers' => $answers
    ];
}
