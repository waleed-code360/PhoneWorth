<?php
require_once __DIR__ . '/_start.php';
require_once __DIR__ . '/_layout.php';
$id = (int) ($_GET['id'] ?? 0);
admin_header('Valuation history','history.php');
if ($id) {
  $v = one($pdo,
    'SELECT v.*, b.brand_name, m.model_name, c.country_name
     FROM valuations v JOIN phone_variants pv ON pv.id=v.variant_id
     JOIN phone_models m ON m.id=pv.model_id JOIN brands b ON b.id=m.brand_id
     JOIN countries c ON c.id=v.country_id WHERE v.id=?', [$id]);
  if (!$v) {
      echo shown_error('Valuation not found.');
  }
  else {
    $answers = rows($pdo,'SELECT * FROM valuation_answers WHERE valuation_id=? ORDER BY question_id',[$id]);
    ?>
<p>
  <a href="history.php">&larr; Back to valuation list</a>
</p>
<div class="stat-grid">
  <div class="stat">
    <small>Phone</small>
    <strong class="stat-text">
      <?= e($v['brand_name'].' '.$v['model_name']) ?>
    </strong>
  </div>
  <div class="stat">
    <small>Estimate</small>
    <strong class="stat-text">
      <?= money($v['estimated_price'],$v['currency_code']) ?>
    </strong>
  </div>
  <div class="stat">
    <small>Condition score</small>
    <strong>
      <?= (int)$v['condition_score'] ?>/100</strong>
    </div>
    <div class="stat">
      <small>Country</small>
      <strong class="stat-text">
        <?= e($v['country_name']) ?>
      </strong>
    </div>
  </div>
  <p class="subtle">Checked at <?= e($v['created_at']) ?> PKT (UTC+05:00)</p>
  <div class="admin-card">
    <h2>Answers and user comments</h2>
    <p>Historical text and deductions remain unchanged when the admin edits rules.</p>
    <?php foreach ($answers as $a): ?>
    <div class="history-item">
      <strong>
        <?= e($a['question_text_snapshot']) ?>
      </strong>
      <p>
        <?= e($a['option_text_snapshot']) ?>
      </p>
      <?php if ($a['user_note'] !== null && $a['user_note'] !== ''): ?>
      <div class="comment-note">Additional comment: <?= e($a['user_note']) ?>
      </div>
      <?php endif; ?>
      <?php if ((float)$a['applied_deduction']>0): ?>
      <small>Deduction: <?= money($a['applied_deduction'],$v['currency_code']) ?>
      </small>
      <?php endif; ?>
    </div>
    <?php endforeach; ?></div>
    <?php
      }
    } else {
      $list = rows($pdo,
        'SELECT v.id,v.estimated_price,v.currency_code,v.condition_score,v.created_at,m.model_name,b.brand_name,c.country_name
         FROM valuations v JOIN phone_variants pv ON pv.id=v.variant_id JOIN phone_models m ON m.id=pv.model_id
         JOIN brands b ON b.id=m.brand_id JOIN countries c ON c.id=v.country_id ORDER BY v.id DESC LIMIT 200');
      ?>
    <div class="admin-card">
      <h2>Last 200 estimates</h2>
      <p class="subtle">Times shown in Pakistan Standard Time (PKT, UTC+05:00).</p>
      <div class="table-scroll">
        <table class="data-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Phone</th>
              <th>Country</th>
              <th>Estimate</th>
              <th>Score</th>
              <th>Time (PKT)</th>
              <th>
              </th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($list as $r): ?>
            <tr>
              <td>#<?= (int)$r['id'] ?>
              </td>
              <td>
                <?= e($r['brand_name'].' '.$r['model_name']) ?>
              </td>
              <td>
                <?= e($r['country_name']) ?>
              </td>
              <td>
                <?= money($r['estimated_price'],$r['currency_code']) ?>
              </td>
              <td>
                <?= (int)$r['condition_score'] ?>
              </td>
              <td><?= e($r['created_at']) ?>
              </td>
              <td>
                <a href="?id=<?= (int)$r['id'] ?>">Details</a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php
    }
    admin_footer();
