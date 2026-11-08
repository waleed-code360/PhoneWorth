<?php

function questions_for_phone($pdo, $countryId, $brandId, $modelId) {
    $questions = rows($pdo,
        'SELECT * FROM condition_questions
         WHERE is_active = 1
           AND (country_id IS NULL OR country_id = ?)
           AND (brand_id IS NULL OR brand_id = ?)
           AND (model_id IS NULL OR model_id = ?)
         ORDER BY sort_order, id',
        [$countryId, $brandId, $modelId]
    );

    foreach ($questions as &$question) {
        $question['options'] = rows($pdo,
            'SELECT * FROM condition_options
             WHERE question_id = ? AND is_active = 1
             ORDER BY sort_order, id',
            [$question['id']]
        );
    }
    unset($question);
    return $questions;
}

function selected_device($pdo, $countryId, $variantId) {
    return one($pdo,
        'SELECT v.id AS variant_id, v.ram_gb, v.storage_gb, m.id AS model_id,
                m.model_name, b.id AS brand_id, b.brand_name,
                c.id AS country_id, c.country_name, c.iso_code, c.currency_code,
                c.estimate_range_percent
         FROM phone_variants v
         JOIN phone_models m ON m.id = v.model_id
         JOIN brands b ON b.id = m.brand_id
         CROSS JOIN countries c
         WHERE v.id = ? AND c.id = ?
           AND v.is_active = 1 AND m.is_active = 1 AND b.is_active = 1
           AND c.is_active = 1',
        [$variantId, $countryId]
    );
}



function questions_for_mode($questions, $mode) {
    if ($mode === 'detailed') {
        return $questions;
    }

    $quickGroups = [
        'ownership', 'power', 'water', 'display', 'frame',
        'battery', 'rear_camera', 'charging', 'pta'
    ];
    $usedGroups = [];
    $quickQuestions = [];

    foreach ($questions as $question) {
        $group = $question['damage_group'];

        if (in_array($group, $quickGroups, true) && !isset($usedGroups[$group])) {
            $quickQuestions[] = $question;
            $usedGroups[$group] = true;
        }
    }

    return $quickQuestions;
}
