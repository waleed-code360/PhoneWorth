<?php
require_once __DIR__ . '/_start.php';
require_once __DIR__ . '/_layout.php';
$error = '';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    check_csrf();
    try {
        $id = field_int($_POST['id'] ?? '0', 'Record ID', 0);
        $action = $_POST['action'] ?? 'save';
        if (!in_array($action, ['save','toggle','delete'], true)) {
            throw new Exception('Unknown action.');
        }
        if ($id && !one($pdo, 'SELECT id FROM countries WHERE id=?', [$id])) {
            throw new Exception('Record not found.');
        }
        if (in_array($action, ['toggle','delete'], true) && $id < 1) {
            throw new Exception('Invalid record selected.');
        }
        if ($action === 'delete') {
            $checks = [
                'SELECT COUNT(*) AS total FROM market_prices WHERE country_id=?',
                'SELECT COUNT(*) AS total FROM model_part_prices WHERE country_id=?',
                'SELECT COUNT(*) AS total FROM condition_questions WHERE country_id=?',
                'SELECT COUNT(*) AS total FROM valuations WHERE country_id=?'
            ];
            foreach ($checks as $sql) {
                $result = one($pdo, $sql, [$id]);
                if ((int) $result['total'] > 0) {
                    throw new Exception('Country has linked prices, rules or valuations. Disable instead.');
                }
            }
            execute_sql($pdo, 'DELETE FROM countries WHERE id=?', [$id]);
            flash('Unused country deleted.');
            go('countries.php');
        }
        if (($_POST['action']??'')==='toggle') {
            execute_sql($pdo,'UPDATE countries SET is_active=1-is_active WHERE id=?',[$id]);
        } else {
            $name = field_text($_POST['country_name'] ?? '', 'Country name', 100);
            $iso = strtoupper(field_text($_POST['iso_code'] ?? '', 'ISO code', 2));
            $currency = strtoupper(field_text($_POST['currency_code'] ?? '', 'Currency code', 3));
            $symbol = field_text($_POST['currency_symbol'] ?? '', 'Currency symbol', 10);
            $range = field_decimal($_POST['estimate_range_percent'] ?? '', 'Estimate range (%)', 0, 100);
            if (!preg_match('/^[A-Z]{2}$/', $iso) || !preg_match('/^[A-Z]{3}$/', $currency)) {
                throw new Exception('Use two letters for ISO and three letters for currency.');
            }
            
            $duplicate = one($pdo,
                'SELECT id FROM countries WHERE LOWER(TRIM(country_name))=LOWER(TRIM(?)) AND id<>? LIMIT 1',
                [$name, $id]
            );
            if ($duplicate) {
                throw new Exception('This country name already exists. Edit the old entry.');
            }
            if ($id) {
                execute_sql(
                    $pdo,
                    'UPDATE countries
                        SET country_name=?,iso_code=?,currency_code=?,currency_symbol=?,estimate_range_percent=?
                        WHERE id=?',
                    [$name,$iso,$currency,$symbol,$range,$id]
                );
            }
            else {
                execute_sql(
                    $pdo,
                    'INSERT INTO countries (country_name,iso_code,currency_code,currency_symbol,estimate_range_percent)
                        VALUES (?,?,?,?,?)',
                    [$name,$iso,$currency,$symbol,$range]
                );
            }
        }
        flash('Country data saved to MySQL.');
        go('countries.php');
    } catch (PDOException $ex) {$error=$action === 'delete'
        ? 'Country has linked records. Disable it instead.'
        : 'Could not save. Country code must be unique.';}
    catch (Exception $ex) {$error=$ex->getMessage();}
}
$edit = (int)($_GET['edit']??0);
$country = $edit?one($pdo,'SELECT * FROM countries WHERE id=?',[$edit]):null;
$list = rows($pdo,
    'SELECT c.*,
       (SELECT COUNT(*) FROM market_prices p WHERE p.country_id=c.id) AS price_count,
       (SELECT COUNT(*) FROM model_part_prices pc WHERE pc.country_id=c.id) AS part_count
     FROM countries c ORDER BY c.country_name, c.id'
);
$nameCounts = [];
foreach ($list as $row) {
    $key = strtolower(trim($row['country_name']));
    $nameCounts[$key]=($nameCounts[$key]??0)+1;
}
admin_header('Countries & currencies','countries.php');
if ($error) {
    echo shown_error($error);
}
?>
<details class="admin-card form-details" <?= ($country || $error) ? 'open' : '' ?>>
  <summary><?= $country ? 'Edit country' : 'Add country' ?>
  </summary>
  <p>
    Every country has its own currency, market prices and part costs. Dubai is a city; select United
    Arab Emirates as the country.
  </p>
  <p class="subtle">Example: United Arab Emirates, ISO <strong>AE</strong>, currency <strong>AED</strong>,
    symbol <strong>AED</strong>. Pakistan uses PK / PKR / Rs.</p>
  <p class="subtle">Estimate range 5% means a calculated 50,000 may show 47,500 to 52,500.</p>
  <form method="post" class="admin-form">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="id" value="<?= (int)($country['id']??0) ?>">
    <input type="hidden" name="action" value="save">
    <div class="field">
      <label>Country name</label>
      <input name="country_name" maxlength="100" required value="<?= e($country['country_name']??'') ?>">
    </div>
    <div class="field">
      <label>ISO country code (2 letters, e.g. PK, AE, US)</label>
      <input
        name="iso_code"
        maxlength="2"
        pattern="[A-Za-z]{2}"
        title="Two letters, like PK or AE"
        required
        value="<?= e($country['iso_code']??'') ?>">
    </div>
    <div class="field">
      <label>Currency code (3 letters, e.g. PKR, AED, USD)</label>
      <input
        name="currency_code"
        maxlength="3"
        pattern="[A-Za-z]{3}"
        title="Three letters, like PKR or AED"
        required
        value="<?= e($country['currency_code']??'') ?>">
    </div>
    <div class="field">
      <label>Currency symbol (e.g. Rs, AED, $)</label>
      <input name="currency_symbol" maxlength="10" required value="<?= e($country['currency_symbol']??'') ?>">
    </div>
    <div class="field">
      <label>Estimate range % (example: 5)</label>
      <input
        name="estimate_range_percent"
        type="number"
        step="0.5"
        min="0"
        max="100"
        required
        value="<?= e($country['estimate_range_percent']??5) ?>">
    </div>
    <div class="form-actions">
      <button class="button primary small">Save country</button>
      <?php if ($country): ?>
      <a class="button secondary small" href="countries.php">Cancel</a>
      <?php endif; ?>
    </div>
  </form>
</details>
<div class="admin-card">
  <h2>Countries</h2>
  <p>Check the ID and ISO before disabling a duplicate. The country with saved market prices
    may be the one your users need. Delete is allowed only when nothing references the country.</p>
  <div class="table-scroll">
    <table class="data-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Country</th>
          <th>ISO</th>
          <th>Currency</th>
          <th>Market / part records</th>
          <th>Range</th>
          <th>Status</th>
          <th>
          </th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($list as $r): ?>
        <tr>
          <td><?= (int)$r['id'] ?>
          </td>
          <td>
            <?= e($r['country_name']) ?>
            <?php if (($nameCounts[strtolower(trim($r['country_name']))]??0) > 1): ?>
            <small class="subtle">Duplicate name</small>
            <?php endif; ?>
          </td>
          <td><?= e($r['iso_code']) ?>
          </td>
          <td>
            <?= e($r['currency_code']) ?> (<?= e($r['currency_symbol']) ?>)
          </td>
          <td><?= (int)$r['price_count'] ?> / <?= (int)$r['part_count'] ?>
          </td>
          <td>
            <?= e($r['estimate_range_percent']) ?>%</td>
            <td>
              <?= active_label($r['is_active']) ?>
            </td>
            <td>
              <a href="?edit=<?= (int)$r['id'] ?>" class="mini-button">Edit</a>
              <?php toggle_form($r['id'],$r['is_active']); ?>
              <form method="post" class="inline-form"
                onsubmit="return confirm('Delete this unused country permanently?');">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <button class="mini-button delete-button" type="submit">Delete</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php admin_footer(); ?>
