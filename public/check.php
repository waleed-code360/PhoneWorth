<?php
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/questions.php';

$countryId = filter_var($_GET['country_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
$variantId = filter_var($_GET['variant_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;

$mode = ($_GET['mode'] ?? 'quick') === 'detailed' ? 'detailed' : 'quick';
$device = selected_device($pdo, $countryId, $variantId);
if (!$device) {
    go('select-phone.php');
}


$typeOne = $device['iso_code'] === 'PK' ? 'pta_approved' : 'standard';
$typeTwo = $device['iso_code'] === 'PK' ? 'non_pta' : 'standard';
$hasPrice = one($pdo,
    'SELECT id FROM market_prices WHERE variant_id=? AND country_id=? AND is_active=1 AND market_type IN (?,?) LIMIT 1',
    [$variantId, $countryId, $typeOne, $typeTwo]
);
if (!$hasPrice) {
    site_header('Price unavailable');
    echo '<main class="container content-page">';
    echo '<div class="panel">';
    echo '<h1>Price not available yet</h1>';
    echo '<p>Ask the admin to add this model\'s local price.</p>';
    echo '<a class="button primary" href="select-phone.php">Choose another phone</a>';
    echo '</div></main>';
    site_footer();
    exit;
}

$allQuestions = questions_for_phone(
    $pdo,
    $countryId,
    $device['brand_id'],
    $device['model_id']
);
$questions = questions_for_mode($allQuestions, $mode);
if (!$questions) {
    site_header('Questions unavailable');
    echo '<main class="container content-page">';
    echo '<div class="panel">';
    echo '<h1>No questions available</h1>';
    echo '<p>Ask the administrator to enable the condition questionnaire.</p>';
    echo '<a class="button primary" href="select-phone.php">Back</a>';
    echo '</div></main>';
    site_footer();
    exit;
}
foreach ($questions as $q) {
    if (!$q['options']) {
        site_header('Questions unavailable');
        echo '<main class="container content-page">';
        echo '<div class="panel">';
        echo '<h1>Questionnaire unavailable</h1>';
        echo '<p>One question has no active options. Ask the admin to finish its setup.</p>';
        echo '<a class="button primary" href="select-phone.php">Back</a>';
        echo '</div></main>';
        site_footer();
        exit;
    }
}
site_header('Phone condition');
?>
<main class="container content-page">
  <div class="page-title">
    <p class="eyebrow dark">STEP 2 OF 3</p>
    <h1>Phone condition</h1>
    <p>Choose an answer for each question. Extra comments appear only when relevant.</p>
  </div>
  <div class="mode-nav">
    <a class="button <?= $mode === 'quick' ? 'primary' : 'secondary' ?>"
      href="check.php?country_id=<?= $countryId ?>&amp;variant_id=<?= $variantId ?>&amp;mode=quick">
      Quick Check
    </a>
    <a class="button <?= $mode === 'detailed' ? 'primary' : 'secondary' ?>"
      href="check.php?country_id=<?= $countryId ?>&amp;variant_id=<?= $variantId ?>&amp;mode=detailed">
      Detailed Check
    </a>
  </div>
  <?php if ($mode === 'quick'): ?>
  <div class="alert info">
    Quick Check asks <?= count($questions) ?> essential questions.
    It does not assess every phone component.
    If you have any other fault, choose Detailed Check for a more complete estimate.
  </div>
  <?php else: ?>
  <div class="alert info">
    Detailed Check includes all <?= count($questions) ?> relevant questions.
    Use it when the phone has additional faults or repair history.
  </div>
  <?php endif; ?>
  <div class="form-layout">
    <section class="panel">
      <div class="selected-phone">
        <span class="selected-icon">pw.</span>
        <div>
          <strong><?= e($device['brand_name'] . ' ' . $device['model_name']) ?>
          </strong>
          <small>
            <?= e($device['country_name']) ?> ·
            <?= e($device['storage_gb']) ?>GB ·
            <?= e($device['currency_code']) ?>
          </small>
        </div>
        <a href="select-phone.php">Change</a>
      </div>
      <form action="result.php" method="post" id="questionnaire">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="country_id" value="<?= $countryId ?>">
        <input type="hidden" name="variant_id" value="<?= $variantId ?>">
        <input type="hidden" name="mode" value="<?= e($mode) ?>">
        <div class="wizard-status" id="wizard-status" aria-live="polite">
          Complete the questions below. Press Next section to continue.
        </div>
        <div id="wizard-error" class="alert error" hidden role="alert">
        </div>
        <?php $category = ''; $number = 0; ?>
        <?php foreach ($questions as $question): ?>
        <?php if (!$question['options']) continue; ?>
        <?php if ($category !== $question['category']):
                      if ($category !== '') {
                          echo '</fieldset>';
                      }
                      $category = $question['category']; ?>
        <fieldset class="question-step">
          <legend class="question-category"><?= e($category) ?>
          </legend>
          <?php endif; ?>
          <?php $number++; ?>
          <div class="question-card" data-question="<?= (int) $question['id'] ?>">
            <h3>
              <span><?= $number ?>.</span> <?= e($question['question_text']) ?>
            </h3>
            <div class="choices">
              <?php foreach ($question['options'] as $option): ?>
              <label class="choice">
                <input type="radio"
                  name="answers[<?= (int) $question['id'] ?>]"
                  value="<?= (int) $option['id'] ?>"
                  data-note="<?= (int) $option['needs_note'] ?>"
                  required>
                <span><?= e($option['option_text']) ?>
                </span>
              </label>
              <?php endforeach; ?>
            </div>
            <div class="note-area" hidden>
              <label for="note-<?= (int) $question['id'] ?>">Anything else we should know? <small>(optional)</small>
              </label>
              <textarea id="note-<?= (int) $question['id'] ?>"
                name="notes[<?= (int) $question['id'] ?>]"
                maxlength="500"
                rows="3"
                placeholder="Explain this issue in your own words..."></textarea>
            </div>
          </div>
          <?php endforeach; ?>
          <?php if ($category !== '') echo '</fieldset>'; ?>
          <div class="form-actions wizard-actions">
            <a class="button secondary" href="select-phone.php">Change phone</a>
            <button class="button secondary" type="button" id="wizard-prev" hidden>&larr; Previous section</button>
            <button class="button primary" type="button" id="wizard-next" hidden>Next section &rarr;</button>
            <button class="button primary" type="submit" id="wizard-submit">Calculate my price &rarr;</button>
          </div>
        </form>
      </section>
    </div>
  </main>
  <script src="js/questions.js" defer></script>
  <?php site_footer(); ?>
