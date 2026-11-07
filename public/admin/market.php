<?php
require_once __DIR__ . '/_start.php';
require_once __DIR__ . '/_layout.php';
$error = '';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    check_csrf();
    try {
        $id = field_int($_POST['id'] ?? '0', 'Record ID', 0);
        $action = $_POST['action'] ?? 'save';
        if (!in_array($action, ['save','toggle'], true)) {
            throw new Exception('Unknown action.');
        }
        if ($id && !one($pdo, 'SELECT id FROM market_prices WHERE id=?', [$id])) {
            throw new Exception('Record not found.');
        }
        if (($_POST['action']??'')==='toggle' && $id < 1) {
            throw new Exception('Invalid record selected.');
        }
        if (($_POST['action']??'')==='toggle') {
            execute_sql($pdo,'UPDATE market_prices SET is_active=1-is_active WHERE id=?',[$id]);
        } else {
            $variant = field_int($_POST['variant_id'] ?? '', 'Phone variant');
            $country = field_int($_POST['country_id'] ?? '', 'Country');
            $type = $_POST['market_type'] ?? 'standard';
            $price = field_decimal($_POST['base_price'] ?? '', 'Base used price', 0.01);
            if (!is_string($type) || !in_array($type,['standard','pta_approved','non_pta'],true)) {
                throw new Exception('Please choose a valid market category.');
            }
            $countryRow = one($pdo,'SELECT iso_code FROM countries WHERE id=?',[$country]);
            if (!$countryRow) {
                throw new Exception('Invalid country.');
            }
             $isPakistan = ($countryRow['iso_code'] === 'PK');
             if (($isPakistan && $type === 'standard') ||
                 (!$isPakistan && $type !== 'standard')) {
                 throw new Exception('Pakistan uses PTA / Non-PTA, other countries use Standard.');
             }
            if ($id) {
                execute_sql(
                    $pdo,
                    'UPDATE market_prices SET variant_id=?,country_id=?,market_type=?,base_price=? WHERE id=?',
                    [$variant,$country,$type,$price,$id]
                );
            }
            else {
                execute_sql(
                    $pdo,
                    'INSERT INTO market_prices (variant_id,country_id,market_type,base_price) VALUES (?,?,?,?)',
                    [$variant,$country,$type,$price]
                );
            }
        }
        flash('Market price saved to MySQL.');
        go('market.php');
    } catch (PDOException $ex) {
        $error = 'Duplicate price or invalid selection. One price per variant, country and market type.';
    }
    catch (Exception $ex) {$error=$ex->getMessage();}
}
$id = (int)($_GET['edit']??0);
$edit = $id?one($pdo,'SELECT * FROM market_prices WHERE id=?',[$id]):null;
$variants = rows($pdo,
    'SELECT v.id, v.ram_gb, v.storage_gb, m.model_name, b.brand_name
     FROM phone_variants v
     JOIN phone_models m ON m.id = v.model_id
     JOIN brands b ON b.id = m.brand_id
     ORDER BY b.brand_name, m.model_name, v.storage_gb'
);
$countries = rows($pdo,'SELECT id,country_name,iso_code FROM countries ORDER BY country_name');
$page = max(1, min(1000, (int) ($_GET['page'] ?? 1)));
$offset = ($page - 1) * 20;
$filterCountry = max(0, (int) ($_GET['country_id'] ?? 0));
$filterVariant = max(0, (int) ($_GET['variant_id'] ?? 0));
$list = rows($pdo,
   "SELECT p.*, c.country_name,m.model_name,b.brand_name,v.storage_gb
    FROM market_prices p JOIN countries c ON c.id=p.country_id JOIN phone_variants v ON v.id=p.variant_id
    JOIN phone_models m ON m.id=v.model_id JOIN brands b ON b.id=m.brand_id
    WHERE (?=0 OR p.country_id=?) AND (?=0 OR p.variant_id=?)
    ORDER BY b.brand_name,m.model_name,c.country_name,p.market_type LIMIT 21 OFFSET $offset",
    [$filterCountry,$filterCountry,$filterVariant,$filterVariant]);
$hasMore = count($list) > 20;
$list = array_slice($list, 0, 20);
admin_header('Market prices','market.php');
if ($error) {
    echo shown_error($error);
}
?>
<details class="admin-card form-details" <?= ($edit || $error) ? 'open' : '' ?>>
  <summary><?= $edit ? 'Edit base price' : 'Add local base price' ?>
  </summary>
  <p>This is the good-condition USED phone price. Never enter a new-phone retail price here.</p>
  <form method="post" class="admin-form">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= (int)($edit['id']??0) ?>">
    <div class="field">
      <label>Phone variant</label>
      <select name="variant_id" required>
        <option value="">Choose phone</option>
        <?php foreach ($variants as $v): ?>
        <option value="<?= (int)$v['id'] ?>" <?= ($edit['variant_id']??0)==$v['id']?'selected':'' ?>>
          <?= e($v['brand_name'].' '.$v['model_name'].' / '.$v['ram_gb'].'GB / '.$v['storage_gb'].'GB') ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label>Country</label>
      <select name="country_id" required>
        <option value="">Choose country</option>
        <?php foreach ($countries as $c): ?>
        <option value="<?= (int)$c['id'] ?>" <?= ($edit['country_id']??0)==$c['id']?'selected':'' ?>>
          <?= e($c['country_name']) ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label>Market category</label>
      <select name="market_type">
        <option value="standard" <?= ($edit['market_type']??'standard')==='standard'?'selected':'' ?>>
          Standard (outside Pakistan)
        </option>
        <option value="pta_approved" <?= ($edit['market_type']??'')==='pta_approved'?'selected':'' ?>>
          PTA approved (Pakistan)
        </option>
        <option value="non_pta" <?= ($edit['market_type']??'')==='non_pta'?'selected':'' ?>>Non-PTA (Pakistan)</option>
      </select>
    </div>
    <div class="field">
      <label>Base used price (local currency)</label>
      <input
        type="number"
        name="base_price"
        step="0.01"
        min="0.01"
        required
        value="<?= e($edit['base_price']??'') ?>">
    </div>
    <div class="form-actions">
      <button class="button primary small">Save price</button>
      <?php if ($edit): ?>
      <a class="button secondary small" href="market.php">Cancel</a>
      <?php endif; ?>
    </div>
  </form>
</details>
<div class="admin-card">
  <h2>Market prices</h2>
  <p>Changes apply to NEW valuations. Previously saved estimates stay unchanged.</p>
  <form method="get" class="filter-row">
    <select name="country_id" aria-label="Filter by country">
      <option value="0">All countries</option>
      <?php foreach ($countries as $c): ?>
      <option value="<?= (int) $c['id'] ?>" <?= $filterCountry === (int) $c['id'] ? 'selected' : '' ?>>
        <?= e($c['country_name']) ?>
      </option>
      <?php endforeach; ?>
    </select>
    <select name="variant_id" aria-label="Filter by phone">
      <option value="0">All phone variants</option>
      <?php foreach ($variants as $v): ?>
      <option value="<?= (int) $v['id'] ?>" <?= $filterVariant === (int) $v['id'] ? 'selected' : '' ?>>
        <?= e($v['brand_name'] . ' ' . $v['model_name'] . ' / ' . $v['storage_gb'] . 'GB') ?>
      </option>
      <?php endforeach; ?>
    </select>
    <button class="button secondary small" type="submit">Filter</button>
    <a class="button secondary small" href="market.php">Clear</a>
  </form>
  <div class="table-scroll">
    <table class="data-table">
      <thead>
        <tr>
          <th>Phone / storage</th>
          <th>Country</th>
          <th>Category</th>
          <th>Price</th>
          <th>Updated (PKT)</th>
          <th>Status</th>
          <th>
          </th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($list as $r): ?>
        <tr>
          <td>
            <?= e($r['brand_name'].' '.$r['model_name'].' / '.$r['storage_gb'].'GB') ?>
          </td>
          <td>
            <?= e($r['country_name']) ?>
          </td>
          <td>
            <?= e($r['market_type']) ?>
          </td>
          <td>
            <?= e($r['base_price']) ?>
          </td>
          <td>
            <?= e($r['updated_at']) ?>
          </td>
          <td>
            <?= active_label($r['is_active']) ?>
          </td>
          <td>
            <a class="mini-button" href="?edit=<?= (int)$r['id'] ?>">Edit</a>
<?php toggle_form($r['id'],$r['is_active']); ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php admin_pages($page, $hasMore, ['country_id'=>$filterCountry, 'variant_id'=>$filterVariant]); ?>
</div>
<?php admin_footer(); ?>
