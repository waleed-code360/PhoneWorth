<?php
require_once __DIR__ . '/_start.php';
require_once __DIR__ . '/_layout.php';
$section = $_GET['section'] ?? 'brands';
if (!in_array($section, ['brands','models','variants'], true)) {
    $section = 'brands';
}
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = $_POST['action'] ?? 'save';
    try {
        $id = field_int($_POST['id'] ?? '0', 'Record ID', 0);
        if (!in_array($action, ['save','toggle','delete'], true)) {
            throw new Exception('Unknown action.');
        }
        $targetTable = ['brands'=>'brands','models'=>'phone_models','variants'=>'phone_variants'][$section];
        if ($id && !one($pdo, "SELECT id FROM $targetTable WHERE id=?", [$id])) {
            throw new Exception('Record not found.');
        }
        if (in_array($action, ['toggle', 'delete'], true) && $id < 1) {
            throw new Exception('Invalid record selected.');
        }

        if ($action === 'delete') {
            
            if ($section === 'brands') {
                $checks = [
                    'SELECT COUNT(*) AS total FROM phone_models WHERE brand_id=?',
                    'SELECT COUNT(*) AS total FROM condition_questions WHERE brand_id=?'
                ];
                $deleteSql = 'DELETE FROM brands WHERE id=?';
            } elseif ($section === 'models') {
                $checks = [
                    'SELECT COUNT(*) AS total FROM phone_variants WHERE model_id=?',
                    'SELECT COUNT(*) AS total FROM model_part_prices WHERE model_id=?',
                    'SELECT COUNT(*) AS total FROM condition_questions WHERE model_id=?'
                ];
                $deleteSql = 'DELETE FROM phone_models WHERE id=?';
            } else {
                $checks = [
                    'SELECT COUNT(*) AS total FROM market_prices WHERE variant_id=?',
                    'SELECT COUNT(*) AS total FROM valuations WHERE variant_id=?'
                ];
                $deleteSql = 'DELETE FROM phone_variants WHERE id=?';
            }

            foreach ($checks as $sql) {
                $result = one($pdo, $sql, [$id]);
                if ((int) $result['total'] > 0) {
                    throw new Exception('Cannot delete: linked records exist. Edit or Disable instead.');
                }
            }
            execute_sql($pdo, $deleteSql, [$id]);
            flash('Unused catalog record deleted.');
            go('phones.php?section=' . $section);
        }
        if ($section === 'brands') {
            if ($action === 'toggle') {
                execute_sql($pdo,'UPDATE brands SET is_active=1-is_active WHERE id=?',[$id]);
            } else {
                $name = field_text($_POST['brand_name'] ?? '', 'Brand name', 100);
                if ($id) {
                    execute_sql($pdo,'UPDATE brands SET brand_name=? WHERE id=?',[$name,$id]);
                }
                else {
                    execute_sql($pdo,'INSERT INTO brands (brand_name) VALUES (?)',[$name]);
                }
            }
        } elseif ($section === 'models') {
            if ($action === 'toggle') {
                execute_sql($pdo,'UPDATE phone_models SET is_active=1-is_active WHERE id=?',[$id]);
            } else {
                $brandId = field_int($_POST['brand_id'] ?? '', 'Brand');
                $name = field_text($_POST['model_name'] ?? '', 'Model name', 150);
                if ($id) {
                    execute_sql(
                        $pdo,
                        'UPDATE phone_models SET brand_id=?,model_name=? WHERE id=?',
                        [$brandId,$name,$id]
                    );
                }
                else {
                    execute_sql(
                        $pdo,
                        'INSERT INTO phone_models (brand_id,model_name) VALUES (?,?)',
                        [$brandId,$name]
                    );
                }
            }
        } else {
            if ($action === 'toggle') {
                execute_sql($pdo,'UPDATE phone_variants SET is_active=1-is_active WHERE id=?',[$id]);
            } else {
                $modelId = field_int($_POST['model_id'] ?? '', 'Model');
                $ram = field_int($_POST['ram_gb'] ?? '', 'RAM (GB)', 0, 128);
                $storage = field_int($_POST['storage_gb'] ?? '', 'Storage (GB)', 1, 4096);
                if ($id) {
                    execute_sql(
                        $pdo,
                        'UPDATE phone_variants SET model_id=?,ram_gb=?,storage_gb=? WHERE id=?',
                        [$modelId,$ram,$storage,$id]
                    );
                }
                else {
                    execute_sql(
                        $pdo,
                        'INSERT INTO phone_variants (model_id,ram_gb,storage_gb) VALUES (?,?,?)',
                        [$modelId,$ram,$storage]
                    );
                }
            }
        }
        flash('Saved in MySQL successfully.');
        go('phones.php?section='.$section);
    } catch (PDOException $ex) {
        $error = $action === 'delete'
            ? 'Cannot delete a catalog record that has linked data. Disable it instead.'
            : 'This entry could not be saved. Check for duplicates and valid references.';
    } catch (Exception $ex) {
        $error = $ex->getMessage();
    }
}

$editId = (int) ($_GET['edit'] ?? 0);
$table = ['brands'=>'brands','models'=>'phone_models','variants'=>'phone_variants'][$section];
$edit = $editId ? one($pdo, "SELECT * FROM $table WHERE id=?", [$editId]) : null;
$brands = rows($pdo,'SELECT id,brand_name FROM brands ORDER BY brand_name');
$models = rows(
    $pdo,
    'SELECT m.id,m.model_name,b.brand_name
        FROM phone_models m
        JOIN brands b ON b.id=m.brand_id
        ORDER BY b.brand_name,m.model_name'
);
admin_header('Phone catalog','phones.php');
admin_tabs(['brands'=>'Brands','models'=>'Models','variants'=>'RAM & storage'], $section);
if ($error) {
    echo shown_error($error);
}
?>
<details class="admin-card form-details" <?= ($edit || $error) ? 'open' : '' ?>>
  <summary><?= $edit ? 'Edit' : 'Add new' ?> <?= e(rtrim($section,'s')) ?>
  </summary>
  <p>First add a brand, then a phone model, then a storage variant. Add local market prices on the next page.</p>
  <form method="post" action="?section=<?= e($section) ?>" class="admin-form">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= (int)($edit['id']??0) ?>">
    <?php if ($section==='brands'): ?>
    <div class="field">
      <label>Brand name</label>
      <input
        name="brand_name"
        maxlength="100"
        value="<?= e($edit['brand_name']??'') ?>"
        required
        placeholder="Example: Tecno">
    </div>
    <?php elseif ($section==='models'): ?>
    <div class="field">
      <label>Brand</label>
      <select name="brand_id" required>
        <option value="">Choose brand</option>
        <?php foreach ($brands as $b): ?>
        <option value="<?= (int)$b['id'] ?>" <?= ($edit['brand_id']??0)==$b['id']?'selected':'' ?>>
          <?= e($b['brand_name']) ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label>Model name</label>
      <input
        name="model_name"
        value="<?= e($edit['model_name']??'') ?>"
        maxlength="150"
        placeholder="Galaxy S23"
        required>
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
      <label>RAM (GB, use 0 if unknown)</label>
      <input type="number" min="0" max="128" name="ram_gb" value="<?= (int)($edit['ram_gb']??8) ?>" required>
    </div>
    <div class="field">
      <label>Storage (GB)</label>
      <input
        type="number"
        min="1"
        max="4096"
        name="storage_gb"
        value="<?= (int)($edit['storage_gb']??128) ?>"
        required>
    </div>
    <?php endif; ?>
    <div class="form-actions">
      <button class="button primary small" type="submit">
        <?= $edit?'Update record':'Add new record' ?>
      </button>
      <?php if ($edit): ?>
      <a class="button secondary small" href="?section=<?= e($section) ?>">Cancel</a>
      <?php endif; ?>
    </div>
  </form>
</details>
<div class="admin-card">
  <h2>Existing records</h2>
  <p>Delete is for unused mistakes only. If a brand, model or variant is already linked
    to other records, use Edit or Disable so prices and history stay safe.</p>
  <div class="table-scroll">
    <table class="data-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Name / details</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $page = max(1, min(1000, (int) ($_GET['page'] ?? 1)));
        $offset = ($page - 1) * 20;
        if ($section==='brands') {
            $list = rows($pdo,"SELECT * FROM brands ORDER BY brand_name LIMIT 21 OFFSET $offset");
        }
        elseif ($section==='models') $list=rows($pdo,
            "SELECT m.*,b.brand_name FROM phone_models m JOIN brands b ON b.id=m.brand_id
             ORDER BY b.brand_name,m.model_name LIMIT 21 OFFSET $offset");
        else {
            $list = rows($pdo,
                "SELECT v.*, m.model_name, b.brand_name
                 FROM phone_variants v
                 JOIN phone_models m ON m.id = v.model_id
                 JOIN brands b ON b.id = m.brand_id
                 ORDER BY b.brand_name, m.model_name, v.storage_gb LIMIT 21 OFFSET $offset"
            );
        }
        $hasMore = count($list) > 20;
        $list = array_slice($list, 0, 20);
        foreach ($list as $r):
          if ($section==='brands') {
              $label = $r['brand_name'];
          }
          elseif ($section==='models') {
              $label = $r['brand_name'].' / '.$r['model_name'];
          }
          else {
              $label = $r['brand_name'].' '.$r['model_name'].' / '.$r['ram_gb'].'GB RAM / '.$r['storage_gb'].'GB';
          }
        ?>
        <tr>
          <td><?= (int)$r['id'] ?>
          </td>
          <td><?= e($label) ?>
          </td>
          <td><?= active_label($r['is_active']) ?>
          </td>
          <td>
            <a class="mini-button" href="?section=<?= e($section) ?>&edit=<?= (int)$r['id'] ?>">Edit</a>
            <?php toggle_form($r['id'],$r['is_active']); ?>
            <form method="post" class="inline-form" action="?section=<?= e($section) ?>"
              onsubmit="return confirm('Delete this unused record permanently?');">
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
  <?php admin_pages($page, $hasMore, ['section' => $section]); ?>
</div>
<?php admin_footer(); ?>
