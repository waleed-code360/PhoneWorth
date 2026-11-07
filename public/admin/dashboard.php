<?php
require_once __DIR__ . '/_start.php';
require_once __DIR__ . '/_layout.php';
$phoneCount = one($pdo,'SELECT COUNT(*) AS total FROM phone_models WHERE is_active=1');
$priceCount = one($pdo,'SELECT COUNT(*) AS total FROM market_prices WHERE is_active=1');
$partCount = one($pdo,'SELECT COUNT(*) AS total FROM model_part_prices WHERE is_active=1');
$historyCount = one($pdo,'SELECT COUNT(*) AS total FROM valuations');
$recent = rows($pdo,
  'SELECT v.id,v.estimated_price,v.currency_code,v.created_at,m.model_name,b.brand_name
   FROM valuations v JOIN phone_variants pv ON pv.id=v.variant_id
   JOIN phone_models m ON m.id=pv.model_id JOIN brands b ON b.id=m.brand_id
   ORDER BY v.id DESC LIMIT 8'
);
admin_header('Dashboard','dashboard.php');
?>
<div class="stat-grid">
  <?php
   $stats = [
       ['Active models', $phoneCount['total']],
       ['Local market prices', $priceCount['total']],
       ['Part price records', $partCount['total']],
       ['Total estimates', $historyCount['total']]
   ];
   foreach ($stats as $stat):
   ?>
  <div class="stat">
    <small><?= e($stat[0]) ?>
    </small>
    <strong><?= (int) $stat[1] ?>
    </strong>
  </div>
  <?php endforeach; ?>
</div>
<p class="subtle">Counts are database records, not prices in rupees.
  One phone may have several local prices and part-cost records.</p>
<div class="admin-card">
  <h2>New here? Start with the guide</h2>
  <p>
    To add a phone: Brand &rarr; Model &rarr; Storage &rarr; Market price &rarr; Repair costs. Changes
    are saved to MySQL.
  </p>
  <p>
    <a class="button secondary small" href="guide.php">Read the admin guide</a>
  </p>
</div>
<div class="admin-card">
  <h2>Quick actions</h2>
  <p>Add a new model, set local prices, or adjust valuation rules without touching PHP code.</p>
  <div class="button-row">
    <a class="button primary small" href="phones.php?section=models">Manage phones</a>
    <a class="button secondary small" href="market.php">Market prices</a>
    <a class="button secondary small" href="rules.php?section=options">Valuation rules</a>
  </div>
</div>
<div class="admin-card">
  <h2>Recent valuations</h2>
  <div class="table-scroll">
    <table class="data-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Phone</th>
          <th>Estimate</th>
          <th>Time (PKT)</th>
          <th>
          </th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($recent as $r): ?>
        <tr>
          <td>#<?= (int)$r['id'] ?>
          </td>
          <td>
            <?= e($r['brand_name'].' '.$r['model_name']) ?>
          </td>
          <td>
            <?= money($r['estimated_price'],$r['currency_code']) ?>
          </td>
          <td>
            <?= e($r['created_at']) ?>
          </td>
          <td>
            <a href="history.php?id=<?= (int)$r['id'] ?>">View</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php admin_footer(); ?>
