<?php
require_once __DIR__ . '/_start.php';
require_once __DIR__ . '/_layout.php';
$section = $_GET['section']??'costs';
if (!in_array($section,['types','costs'],true)) {
    $section = 'costs';
}
$error = '';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    check_csrf();
    try {
        $id = field_int($_POST['id'] ?? '0', 'Record ID', 0);
        $action = $_POST['action'] ?? 'save';
        if (!in_array($action, ['save','toggle'], true)) {
            throw new Exception('Unknown action.');
        }
        $targetTable = $section==='types'?'part_types':'model_part_prices';
        if ($id && !one($pdo, "SELECT id FROM $targetTable WHERE id=?", [$id])) {
            throw new Exception('Record not found.');
        }
        if (($_POST['action'] ?? '') === 'toggle' && $id < 1) {
            throw new Exception('Invalid record selected.');
        }
        if ($section==='types') {
            if (($_POST['action']??'')==='toggle') {
                execute_sql($pdo,'UPDATE part_types SET is_active=1-is_active WHERE id=?',[$id]);
            }
            else {
                $name = field_text($_POST['part_name'] ?? '', 'Part name', 100);
                if ($id) {
                    execute_sql($pdo,'UPDATE part_types SET part_name=? WHERE id=?',[$name,$id]);
                }
                else {
                    execute_sql($pdo,'INSERT INTO part_types (part_name) VALUES (?)',[$name]);
                }
            }
        } else {
            if (($_POST['action']??'')==='toggle') {
                execute_sql($pdo,'UPDATE model_part_prices SET is_active=1-is_active WHERE id=?',[$id]);
            }
            else {
                $model = field_int($_POST['model_id'] ?? '', 'Phone model');
                $part = field_int($_POST['part_type_id'] ?? '', 'Part');
                $country = field_int($_POST['country_id'] ?? '', 'Country');
                $repair = ($_POST['repair_cost'] ?? '') === '' ? null : field_decimal($_POST['repair_cost'], 'Repair cost', 0.01);
                $replacement = ($_POST['replacement_cost'] ?? '') === '' ? null : field_decimal($_POST['replacement_cost'], 'Replacement cost', 0.01);
                if ($repair === null && $replacement === null) {
                    throw new Exception('Enter at least one local repair or replacement cost.');
                }
                if ($id) {
                    execute_sql($pdo,
                        'UPDATE model_part_prices
                         SET model_id=?, part_type_id=?, country_id=?,
                             repair_cost=?, replacement_cost=? WHERE id=?',
                        [$model, $part, $country, $repair, $replacement, $id]
                    );
                }
                else {
                    execute_sql(
                        $pdo,
                        'INSERT INTO model_part_prices (model_id,part_type_id,country_id,repair_cost,replacement_cost)
                            VALUES (?,?,?,?,?)',
                        [$model,$part,$country,$repair,$replacement]
                    );
                }
            }
        }
        flash('Part data saved to MySQL.');
        go('parts.php?section=' . $section);
    } catch (PDOException $ex) {
        $error = 'Could not save. Check that the model/part/country combination is not duplicated.';
    }
    catch (Exception $ex) {$error=$ex->getMessage();}
}
$editId = (int)($_GET['edit']??0);
$table = $section==='types'?'part_types':'model_part_prices';
$edit = $editId?one($pdo,"SELECT * FROM $table WHERE id=?",[$editId]):null;
$models = rows(
    $pdo,
    'SELECT m.id,m.model_name,b.brand_name
        FROM phone_models m
        JOIN brands b ON b.id=m.brand_id
        ORDER BY b.brand_name,m.model_name'
);
$parts = rows($pdo,'SELECT id,part_name FROM part_types ORDER BY part_name');
$countries = rows($pdo,'SELECT id,country_name FROM countries ORDER BY country_name');
admin_header('Part prices','parts.php');
admin_tabs(['costs'=>'Local repair costs','types'=>'Part types'],$section);
if ($error) {
    echo shown_error($error);
}
?>
<details class="admin-card help-details">
  <summary>How do local repair costs work? (simple example)</summary>
  <h2>Repair Costs: how this page works</h2>
  <p>
    <strong>Part Types</strong> creates the names: Screen, Battery, Camera, etc.</p>
  <p>
    <strong>Local Repair Costs</strong> assigns a cost to one PHONE MODEL + PART + COUNTRY.
    For example: iPhone 13 + Screen + Pakistan.</p>
  <p>
    <strong>Repair cost</strong> means fixing the original component.
    <strong>Replacement cost</strong> means fitting another component.
    Enter either or both. These prices are in the selected country's currency.</p>
  <p class="subtle">Question Rules chooses which cost to use and what percentage to deduct.
    Example only: replacement cost Rs 20,000 x 80% = Rs 16,000 deduction.
    All demo prices are illustrative, not verified market quotes.</p>
</details>
<details class="admin-card form-details" <?= ($edit || $error) ? 'open' : '' ?>>
  <summary><?= $edit?'Edit':'Add new' ?> <?= $section==='types'?'part type':'model-specific repair cost' ?>
  </summary>
  <form class="admin-form" method="post" action="?section=<?= e($section) ?>">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= (int)($edit['id']??0) ?>">
    <?php if ($section==='types'): ?>
    <div class="field">
      <label>Part name</label>
      <input
        name="part_name"
        maxlength="100"
        required
        value="<?= e($edit['part_name']??'') ?>"
        placeholder="Screen">
    </div>
    <?php else: ?>
    <div class="field">
      <label>Phone model</label>
      <select name="model_id" required>
        <option value="">Choose model</option>
        <?php foreach ($models as $m): ?>
        <option value="<?= (int)$m['id'] ?>" <?= ($edit['model_id']??0)==$m['id']?'selected':'' ?>>
          <?= e($m['brand_name'].' '.$m['model_name']) ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label>Part</label>
      <select name="part_type_id" required>
        <option value="">Choose part</option>
        <?php foreach ($parts as $part): ?>
        <option value="<?= (int)$part['id'] ?>" <?= ($edit['part_type_id']??0)==$part['id']?'selected':'' ?>>
          <?= e($part['part_name']) ?>
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
      <label>Repair cost (fix existing part, optional)</label>
      <input name="repair_cost" type="number" step="0.01" min="0.01" value="<?= e($edit['repair_cost']??'') ?>">
    </div>
    <div class="field">
      <label>Replacement cost (fit replacement part, optional)</label>
      <input
        name="replacement_cost"
        type="number"
        step="0.01"
        min="0.01"
        value="<?= e($edit['replacement_cost']??'') ?>">
    </div>
    <?php endif; ?>
    <div class="form-actions">
      <button class="button primary small">Save record</button>
      <?php if ($edit): ?>
      <a class="button secondary small" href="?section=<?= e($section) ?>">Cancel</a>
      <?php endif; ?>
    </div>
  </form>
</details>
<div class="admin-card">
  <h2>Existing <?= $section==='types'?'part types':'part prices' ?>
  </h2>
  <?php if ($section==='costs'): ?>
  <form method="get" class="filter-row">
    <input type="hidden" name="section" value="costs">
    <select name="model_id">
      <option value="0">All phone models</option>
      <?php foreach ($models as $m): ?>
      <option value="<?= (int)$m['id'] ?>" <?= (int)($_GET['model_id']??0)==$m['id']?'selected':'' ?>>
        <?= e($m['brand_name'].' '.$m['model_name']) ?>
      </option>
      <?php endforeach; ?>
    </select>
    <select name="country_id">
      <option value="0">All countries</option>
      <?php foreach ($countries as $c): ?>
      <option value="<?= (int)$c['id'] ?>" <?= (int)($_GET['country_id']??0)==$c['id']?'selected':'' ?>>
        <?= e($c['country_name']) ?>
      </option>
      <?php endforeach; ?>
    </select>
    <button class="button secondary small">Filter</button>
  </form>
  <?php endif; ?>
  <div class="table-scroll">
    <table class="data-table">
      <thead>
        <tr>
          <th>
            <?= $section==='types'?'Part':'Phone' ?>
          </th>
          <?php if ($section==='costs'): ?>
          <th>Part</th>
          <th>Country</th>
          <th>Repair</th>
          <th>Replacement</th>
          <?php endif; ?>
          <th>Status</th>
          <th>
          </th>
        </tr>
      </thead>
      <tbody>
        <?php
        $page = max(1, min(1000, (int) ($_GET['page'] ?? 1)));
        $offset = ($page - 1) * 20;
        if ($section==='types') {
            $list = rows($pdo,"SELECT * FROM part_types ORDER BY part_name LIMIT 21 OFFSET $offset");
        }
        else {
            $filterModel = (int)($_GET['model_id']??0);
            $filterCountry = (int)($_GET['country_id']??0);
            $list = rows($pdo,
              "SELECT p.*,m.model_name,b.brand_name,pt.part_name,c.country_name
               FROM model_part_prices p JOIN phone_models m ON m.id=p.model_id JOIN brands b ON b.id=m.brand_id
               JOIN part_types pt ON pt.id=p.part_type_id JOIN countries c ON c.id=p.country_id
               WHERE (?=0 OR p.model_id=?) AND (?=0 OR p.country_id=?)
               ORDER BY b.brand_name,m.model_name,c.country_name,pt.part_name LIMIT 21 OFFSET $offset",
               [$filterModel,$filterModel,$filterCountry,$filterCountry]);
        }
        $hasMore = count($list) > 20;
        $list = array_slice($list, 0, 20);
        foreach ($list as $r): ?>
        <tr>
          <td><?= e($section==='types'?$r['part_name']:$r['brand_name'].' '.$r['model_name']) ?>
          </td>
          <?php if ($section==='costs'): ?>
          <td>
            <?= e($r['part_name']) ?>
          </td>
          <td>
            <?= e($r['country_name']) ?>
          </td>
          <td>
            <?= e($r['repair_cost']??'—') ?>
          </td>
          <td>
            <?= e($r['replacement_cost']??'—') ?>
          </td>
          <?php endif; ?>
          <td>
            <?= active_label($r['is_active']) ?>
          </td>
          <td>
            <a class="mini-button" href="?section=<?= e($section) ?>&edit=<?= (int)$r['id'] ?>">Edit</a>
<?php toggle_form($r['id'],$r['is_active']); ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php admin_pages($page, $hasMore, [
    'section' => $section,
    'model_id' => (int) ($_GET['model_id'] ?? 0),
    'country_id' => (int) ($_GET['country_id'] ?? 0)
  ]); ?>
  <p class="subtle">Showing 20 records per page. Filter by model and country.</p>
</div>
<?php admin_footer(); ?>
