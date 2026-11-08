<?php
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/questions.php';
require_once __DIR__ . '/../includes/calculate.php';

$error = '';
$hold = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    if (!empty($_SESSION['last_valuation_time']) && time() - $_SESSION['last_valuation_time'] < 2) {
        $error = 'Please wait a moment before calculating another estimate.';
    }
    $countryId = filter_var($_POST['country_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
    $variantId = filter_var($_POST['variant_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
    $mode = ($_POST['mode'] ?? 'quick') === 'detailed' ? 'detailed' : 'quick';
    $device = selected_device($pdo, $countryId, $variantId);
    if (!$device) {
        http_response_code(400);
        $error = 'Invalid phone selection. Please start again.';
    } else {
        $allQuestions = questions_for_phone(
            $pdo,
            $countryId,
            $device['brand_id'],
            $device['model_id']
        );
        $questions = questions_for_mode($allQuestions, $mode);
        
        $postedAnswers = $_POST['answers'] ?? [];
        $postedNotes = $_POST['notes'] ?? [];
        $chosen = [];
        $marketType = 'standard';

        if ($error) {
            
        } elseif (!$questions) {
            $error = 'The questionnaire is not available yet. Please contact the administrator.';
        } elseif (!is_array($postedAnswers) || !is_array($postedNotes) || count($postedAnswers) > 100 || count($postedNotes) > 100) {
            $error = 'Please choose one answer for every question.';
        } else {
            foreach ($questions as $question) {
                if (!$question['options']) {
                    $error = 'A question has no active answer options. Ask the admin to complete its setup.';
                    break;
                }
                $questionId = (int) $question['id'];
                $rawOption = $postedAnswers[$questionId] ?? '';
                if (!is_scalar($rawOption) || !ctype_digit((string) $rawOption)) {
                    $error = 'Please select one valid answer for every question.';
                    break;
                }
                $optionId = (int) $rawOption;
                $matched = null;
                foreach ($question['options'] as $option) {
                    if ((int) $option['id'] === $optionId) {
                        $matched = $option;
                        break;
                    }
                }
                if (!$matched) {
                    $error = 'Please answer every question. Missing: ' . $question['question_text'];
                    break;
                }
                $noteValue = $postedNotes[$questionId] ?? '';
                if (!is_string($noteValue)) {
                    $error = 'Invalid comment submitted.';
                    break;
                }
                $note = trim($noteValue);
                $noteLength = function_exists('mb_strlen') ? mb_strlen($note, 'UTF-8') : iconv_strlen($note, 'UTF-8');
                if ($noteLength === false || $noteLength > 500) {
                    $error = 'Each comment must be 500 characters or fewer.';
                    break;
                }
                
                if (!(int) $matched['needs_note']) {
                    $note = '';
                }

                $entry = array_merge($matched, [
                    'question_text' => $question['question_text'],
                    'damage_group' => $question['damage_group'],
                    'user_note' => $note
                ]);
                $chosen[] = $entry;
                if ((int) $matched['blocks_valuation'] === 1) {
                    $hold = 'This answer requires physical inspection or ownership verification: ' . $question['question_text'];
                }
                if ($matched['market_type']) {
                    $marketType = $matched['market_type'];
                }
            }
        }

        if (!$error && !$hold) {
            
            if ($device['iso_code'] === 'PK' && !in_array($marketType, ['pta_approved','non_pta'], true)) {
                $error = 'Please select a valid PTA registration status.';
            } elseif ($device['iso_code'] !== 'PK' && $marketType !== 'standard') {
                $error = 'Invalid market status for the selected country.';
            } else {
                $market = one($pdo,
                    'SELECT base_price FROM market_prices WHERE variant_id=? AND country_id=? AND market_type=? AND is_active=1',
                    [$variantId, $countryId, $marketType]
                );
                if (!$market) {
                    $error = 'No base price for this model, country and market status yet. Ask the admin to add it.';
                } else {
                    $partRows = rows($pdo,
                        'SELECT part_type_id, repair_cost, replacement_cost FROM model_part_prices
                         WHERE model_id=? AND country_id=? AND is_active=1',
                        [$device['model_id'], $countryId]
                    );
                    $partPrices = [];
                    foreach ($partRows as $part) {
                        $partPrices[(int) $part['part_type_id']] = $part;
                    }
                    try {
                        $calculated = calculate_price(
                            (float) $market['base_price'],
                            (float) $device['estimate_range_percent'],
                            $chosen,
                            $partPrices
                        );
                    } catch (Exception $exception) {
                        $error = 'Missing data: ' . $exception->getMessage();
                    }

                    if (!$error) {
                        try {
                            $pdo->beginTransaction();
                            execute_sql($pdo,
                                'INSERT INTO valuations
                                 (user_id, variant_id, country_id, market_type, currency_code,
                                  base_price, total_deduction, estimated_price, minimum_price,
                                  maximum_price, condition_score, used_fallback)
                                 VALUES (NULL,?,?,?,?,?,?,?,?,?,?,?)',
                                [$variantId,$countryId,$marketType,$device['currency_code'],$calculated['base_price'],
                                 $calculated['total_deduction'],$calculated['estimated_price'],$calculated['minimum_price'],
                                 $calculated['maximum_price'],$calculated['condition_score'],$calculated['used_fallback']]
                            );
                            $valuationId = (int) $pdo->lastInsertId();
                            foreach ($calculated['answers'] as $answer) {
                                execute_sql($pdo,
                                    'INSERT INTO valuation_answers
                                     (valuation_id, question_id, option_id, question_text_snapshot,
                                      option_text_snapshot, user_note, applied_deduction, score_penalty_applied)
                                     VALUES (?,?,?,?,?,?,?,?)',
                                    [$valuationId, $answer['question_id'], $answer['id'], $answer['question_text'],
                                     $answer['option_text'], $answer['user_note'] ?: null,
                                     $answer['applied_deduction'], $answer['score_penalty_applied']]
                                );
                            }
                            $pdo->commit();
                            $_SESSION['last_valuation_time'] = time();
                            $_SESSION['my_results'][] = $valuationId;
                            $_SESSION['quick_results'][$valuationId] = ($mode === 'quick');
                            $_SESSION['my_results'] = array_slice($_SESSION['my_results'], -20);
                            go('result.php?id=' . $valuationId);
                        } catch (PDOException $exception) {
                            if ($pdo->inTransaction()) {
                                $pdo->rollBack();
                            }
                            $error = 'Unable to save this estimate. Please try again.';
                        }
                    }
                }
            }
        }
    }
} else {
    $valuationId = (int) ($_GET['id'] ?? 0);
    $allowed = in_array($valuationId, $_SESSION['my_results'] ?? [], true) || is_admin($pdo);
    if (!$valuationId || !$allowed) {
        http_response_code(403);
        $error = 'This result belongs to another session or is unavailable.';
    } else {
        $valuation = one($pdo,
            'SELECT v.*, m.model_name, b.brand_name, c.country_name, pv.storage_gb
             FROM valuations v JOIN phone_variants pv ON pv.id=v.variant_id
             JOIN phone_models m ON m.id=pv.model_id
             JOIN brands b ON b.id=m.brand_id JOIN countries c ON c.id=v.country_id
             WHERE v.id=?', [$valuationId]
        );
        if (!$valuation) {
            $error = 'Result not found.';
        }
        else $details = rows($pdo,
            'SELECT * FROM valuation_answers WHERE valuation_id=? ORDER BY question_id', [$valuationId]
        );
    }
}

site_header('Your price estimate');
?>
<main class="container content-page">
  <?php if ($error): ?>
  <div class="panel">
    <h1>Couldn't complete the estimate</h1>
    <?= shown_error($error) ?>
    <a class="button primary" href="select-phone.php">Try again</a>
  </div>
  <?php elseif ($hold): ?>
  <div class="panel hold-panel">
    <div class="result-check warn">!</div>
    <h1>Physical inspection needed</h1>
    <p>
      <?= e($hold) ?>
    </p>
    <p>
      We can't give a reliable normal price for this condition. Please confirm ownership or have the
      device checked by a qualified technician.
    </p>
    <a class="button primary" href="select-phone.php">Start again</a>
  </div>
  <?php else: ?>
  <div class="page-title">
    <span class="eyebrow dark">STEP 03 / 03</span>
    <h1>Your price estimate.</h1>
    <p>
      <?= e($valuation['brand_name'] . ' ' . $valuation['model_name']) ?> ·
      <?= e($valuation['storage_gb']) ?>GB ·
      <?= e($valuation['country_name']) ?>
      (<?= e($valuation['market_type']) ?>)
    </p>
    </div>
    <div class="result-grid">
      <section class="panel">
        <div class="result-check">✓</div>
        <p class="result-label">ESTIMATED RESALE VALUE</p>
        <h2 class="result-value"><?= money($valuation['estimated_price'], $valuation['currency_code']) ?>
        </h2>
        <p class="muted center">
          Illustrative range:
          <?= money($valuation['minimum_price'], $valuation['currency_code']) ?> –
          <?= money($valuation['maximum_price'], $valuation['currency_code']) ?>
        </p>
        <div class="summary-grid">
          <div class="summary-stat">
            <small>Condition score</small>
            <strong>
              <?= (int) $valuation['condition_score'] ?>/100</strong>
            </div>
            <div class="summary-stat">
              <small>Total deductions</small>
              <strong>
                <?= money($valuation['total_deduction'], $valuation['currency_code']) ?>
              </strong>
            </div>
          </div>
          <h3 class="table-heading">Price breakdown</h3>
          <table class="data-table">
            <thead>
              <tr>
                <th>Item</th>
                <th class="right">Amount</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td>Good-condition local base price</td>
                <td class="right">
                  <?= money($valuation['base_price'], $valuation['currency_code']) ?>
                </td>
              </tr>
              <?php foreach ($details as $item): ?>
              <?php if ((float) $item['applied_deduction'] > 0): ?>
              <tr>
                <td>
                  <?= e($item['question_text_snapshot']) ?>
                  <br>
                  <small>
                    <?= e($item['option_text_snapshot']) ?>
                  </small>
                </td>
                <td class="right negative">− <?= money($item['applied_deduction'], $valuation['currency_code']) ?>
                </td>
              </tr>
              <?php endif; ?>
              <?php endforeach; ?>
              <tr class="strong-row">
                <td>Estimated value</td>
                <td class="right">
                  <?= money($valuation['estimated_price'], $valuation['currency_code']) ?>
                </td>
              </tr>
            </tbody>
          </table>
          <?php if ($valuation['used_fallback']): ?>
          <div class="alert warning">
            At least one part did not have a local repair cost.
            A less certain fallback rule was used.
          </div>
          <?php endif; ?>
          <?php if (!empty($_SESSION['quick_results'][$valuationId])): ?>
          <div class="alert warning">
            Quick estimate: only the selected condition areas were checked.
            Other faults were not assessed and could reduce the actual resale value.
            <?php
            $detailedUrl = 'check.php?country_id=' . (int) $valuation['country_id'];
            $detailedUrl .= '&variant_id=' . (int) $valuation['variant_id'];
            $detailedUrl .= '&mode=detailed';
            ?>
            <a href="<?= e($detailedUrl) ?>">Try Detailed Check</a>.
          </div>
          <?php endif; ?>
          <div class="alert info">
            This is a university demo. Initial prices and percentages are fictional
            sample values, not a selling-price guarantee.
          </div>
          <a class="button primary" href="select-phone.php">Check another phone &rarr;</a>
        </section>
        <aside class="side-panel">
          <div class="side-number">03</div>
          <h3>Your answers</h3>
          <p>Any extra details you entered are stored for admin review.</p>
          <?php foreach ($details as $item): ?>
          <div class="answer-line">
            <strong>
              <?= e($item['question_text_snapshot']) ?>
            </strong>
            <span>
              <?= e($item['option_text_snapshot']) ?>
            </span>
          </div>
          <?php endforeach; ?>
        </aside>
      </div>
      <?php endif; ?>
    </main>
    <?php site_footer(); ?>
