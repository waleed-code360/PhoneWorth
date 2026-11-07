<?php
require_once __DIR__ . '/_start.php';
require_once __DIR__ . '/_layout.php';
$section = $_GET['section']??'options';
if (!in_array($section,['questions','options'],true)) {
    $section = 'options';
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
        $targetTable = $section==='questions'?'condition_questions':'condition_options';
        if ($id && !one($pdo, "SELECT id FROM $targetTable WHERE id=?", [$id])) {
            throw new Exception('Record not found.');
        }
        if (($_POST['action'] ?? '') === 'toggle' && $id < 1) {
            throw new Exception('Invalid record selected.');
        }
        if ($section==='questions') {
            if (($_POST['action']??'')==='toggle') {
                $questionRow = one($pdo,'SELECT is_active FROM condition_questions WHERE id=?',[$id]);
                if (!$questionRow) {
                    throw new Exception('Question not found.');
                }
                if (!(int)$questionRow['is_active']) {
                    $available = one(
                        $pdo,
                        'SELECT COUNT(*) AS total FROM condition_options WHERE question_id=? AND is_active=1',
                        [$id]
                    );
                    if (!(int)$available['total']) {
                        throw new Exception('Add at least one active answer option before enabling this question.');
                    }
                }
                execute_sql($pdo,'UPDATE condition_questions SET is_active=1-is_active WHERE id=?',[$id]);
            } else {
                $text = field_text($_POST['question_text'] ?? '', 'Question text', 255);
                $category = field_text($_POST['category'] ?? '', 'Category', 50);
                $group = field_text($_POST['damage_group'] ?? '', 'Damage group', 50);
                $country = ($_POST['country_id'] ?? '') === '' ? null : field_int($_POST['country_id'], 'Country');
                $brand = ($_POST['brand_id'] ?? '') === '' ? null : field_int($_POST['brand_id'], 'Brand');
                $model = ($_POST['model_id'] ?? '') === '' ? null : field_int($_POST['model_id'], 'Model');
                $sort = field_int($_POST['sort_order'] ?? '0', 'Question order', 0, 65535);
                if ($model) {
                    $selectedModel = one($pdo, 'SELECT brand_id FROM phone_models WHERE id=?', [$model]);
                    if (!$selectedModel) {
                        throw new Exception('Invalid phone model.');
                    }
                    if ($brand && (int)$selectedModel['brand_id'] !== $brand) {
                        throw new Exception('Selected model does not belong to the chosen brand.');
                    }
                    // If only a model was selected, still limit it to that model.
                }
                if ($id) execute_sql($pdo,
                    'UPDATE condition_questions
                     SET question_text=?, category=?, damage_group=?,
                         country_id=?, brand_id=?, model_id=?, sort_order=?
                     WHERE id=?',
                    [$text,$category,$group,$country,$brand,$model,$sort,$id]);
                else execute_sql($pdo,
                    'INSERT INTO condition_questions
                     (question_text, category, damage_group, country_id, brand_id, model_id, sort_order, is_active)
                     VALUES (?,?,?,?,?,?,?,0)',
                    [$text,$category,$group,$country,$brand,$model,$sort]);
            }
        } else {
            if (($_POST['action']??'')==='toggle') {
                $current = one($pdo,'SELECT question_id,is_active FROM condition_options WHERE id=?',[$id]);
                if (!$current) {
                    throw new Exception('Answer option not found.');
                }
                if ((int)$current['is_active']) {
                    $questionRow = one(
                        $pdo,
                        'SELECT is_active FROM condition_questions WHERE id=?',
                        [$current['question_id']]
                    );
                    $available = one(
                        $pdo,
                        'SELECT COUNT(*) AS total FROM condition_options WHERE question_id=? AND is_active=1',
                        [$current['question_id']]
                    );
                    if ($questionRow && (int)$questionRow['is_active'] && (int)$available['total'] <= 1) {
                        throw new Exception('This is the last active option. Disable the question first, or add another answer.');
                    }
                }
                execute_sql($pdo,'UPDATE condition_options SET is_active=1-is_active WHERE id=?',[$id]);
            } else {
                $question = field_int($_POST['question_id'] ?? '', 'Question');
                $text = field_text($_POST['option_text'] ?? '', 'Answer text', 150);
                $type = $_POST['rule_type'] ?? 'none';
                $percent = field_decimal($_POST['percentage_value'] ?? '0', 'Base price deduction %', 0, 100);
                $factor = field_decimal($_POST['part_cost_percent'] ?? '0', 'Part cost deduction %', 0, 100);
                $part = ($_POST['part_type_id'] ?? '') === '' ? null : field_int($_POST['part_type_id'], 'Part');
                $kind = ($_POST['cost_kind'] ?? '') ?: null;
                $fallback = field_decimal($_POST['fallback_percent'] ?? '0', 'Fallback %', 0, 100);
                $score = field_int($_POST['score_penalty'] ?? '0', 'Score penalty', 0, 100);
                $note = isset($_POST['needs_note']) ? 1 : 0;
                $block = isset($_POST['blocks_valuation']) ? 1 : 0;
                $market = ($_POST['market_type'] ?? '') ?: null;
                $sort = field_int($_POST['sort_order'] ?? '0', 'Option order', 0, 65535);
                if (!is_string($type) || !in_array($type, ['none','percentage','part_cost'], true)) {
                    throw new Exception('Please choose a valid deduction method.');
                }
                if ($type==='part_cost' && (!$part || !in_array($kind,['repair','replacement'],true))) {
                    throw new Exception('Part-cost rule needs a part and repair/replacement selection.');
                }
                if (
                    $market !== null &&
                    (!is_string($market) ||
                    !in_array($market,['pta_approved','non_pta','standard'],true))
                ) {
                    throw new Exception('Invalid market category.');
                }
                if ($market !== null) {
                    $questionRow = one(
                        $pdo,
                        'SELECT country_id FROM condition_questions WHERE id=?',
                        [$question]
                    );
                    $pk = one($pdo, "SELECT id FROM countries WHERE iso_code='PK'");
                    if (
                        !$questionRow ||
                        !$pk ||
                        (int)$questionRow['country_id'] !== (int)$pk['id'] ||
                        !in_array($market, ['pta_approved','non_pta'],true)
                    ) {
                        throw new Exception('PTA categories can only be set on a Pakistan-only question.');
                    }
                    if ($type !== 'none') {
                        throw new Exception('PTA selection must not also deduct a percentage.');
                    }
                }
                if ($type === 'part_cost' && $fallback <= 0) {
                    throw new Exception('Provide a fallback percentage for missing local part prices.');
                }
                if ($type !== 'part_cost') { $part=null; $kind=null; }
                if ($type !== 'percentage') {
                    $percent = 0;
                }
                if ($type !== 'part_cost') {
                    $factor = 0;
                }
                $values = [$question,$text,$type,$percent,$factor,$part,$kind,$fallback,$score,$note,$block,$market,$sort];
                if ($id) {
                    $values[]=$id;
                    execute_sql($pdo,
                        'UPDATE condition_options
                         SET question_id=?, option_text=?, rule_type=?,
                             percentage_value=?, part_cost_percent=?, part_type_id=?,
                             cost_kind=?, fallback_percent=?, score_penalty=?,
                             needs_note=?, blocks_valuation=?, market_type=?, sort_order=?
                         WHERE id=?',
                        $values
                    );
                } else {
                    execute_sql($pdo,
                        'INSERT INTO condition_options
                         (question_id, option_text, rule_type, percentage_value,
                          part_cost_percent, part_type_id, cost_kind, fallback_percent,
                          score_penalty, needs_note, blocks_valuation, market_type,
                          sort_order)
                         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
                        $values
                    );
                }
            }
        }
        flash('Question or rule saved to MySQL.');
        go('rules.php?section='.$section);
    } catch (PDOException $ex) {
        $error = 'Could not save. Check duplicate option text, references and data types.';
    }
    catch (Exception $ex) {$error=$ex->getMessage();}
}
$id = (int)($_GET['edit']??0);
$table = $section==='questions'?'condition_questions':'condition_options';
$edit = $id?one($pdo,"SELECT * FROM $table WHERE id=?",[$id]):null;
$questions = rows($pdo,'SELECT id,question_text FROM condition_questions ORDER BY sort_order,id');
$parts = rows($pdo,'SELECT id,part_name FROM part_types ORDER BY part_name');
$countries = rows($pdo,'SELECT id,country_name FROM countries ORDER BY country_name');
$brands = rows($pdo,'SELECT id,brand_name FROM brands ORDER BY brand_name');
$models = rows(
    $pdo,
    'SELECT m.id,m.model_name,b.brand_name
        FROM phone_models m
        JOIN brands b ON b.id=m.brand_id
        ORDER BY b.brand_name,m.model_name'
);
admin_header('Questions & rules','rules.php');
admin_tabs(['options'=>'Answer options & deductions','questions'=>'Question text & conditions'],$section);
if ($error) {
    echo shown_error($error);
}
?>
<details class="admin-card help-details">
  <summary>How do Questions & Rules work? (examples and field explanations)</summary>
  <h2>Understand Questions & Rules</h2>
  <p>
    <strong>Question text & conditions:</strong> Write what the customer is asked,
    and which phones/countries should see it.</p>
  <p>
    <strong>Answer options & deductions:</strong> Add answers to that question
    and decide whether each answer reduces money, score, or stops the estimate.</p>
  <details>
    <summary>
      <strong>Simple explanation of every rule field (click to open)</strong>
    </summary>
    <ul class="guide-steps">
      <li>
        <strong>None:</strong> no monetary deduction. Use for 'Working' answers or PTA status.</li>
      <li>
        <strong>% of base price:</strong> a fixed share of the used-phone base price.</li>
      <li>
        <strong>% of local part cost:</strong> uses the model/country repair or replacement price.</li>
      <li>
        <strong>Part and cost kind:</strong> for example Screen + Replacement.</li>
      <li>
        <strong>Fallback %:</strong> used only when the selected local part price is missing.</li>
      <li>
        <strong>Score penalty:</strong> reduces the 100-point condition score, separate from money.</li>
      <li>
        <strong>Market selector:</strong> PTA Approved / Non-PTA for a Pakistan-only PTA question.</li>
      <li>
        <strong>Optional comment:</strong> shows a text box for the chosen abnormal answer.</li>
      <li>
        <strong>Stop valuation:</strong> physical inspection instead of a normal price.</li>
      <li>
        <strong>Damage group:</strong> overlapping faults such as screen/touch use the
        highest deduction within their group, not both added together.</li>
      <li>
        <strong>Sort order:</strong> smaller numbers appear earlier.</li>
    </ul>
  </details>
  <p class="subtle">Example only: base price Rs 80,000; screen replacement Rs 20,000;
    part-cost rule 80% => deduction Rs 16,000 and estimate Rs 64,000
    before other deductions.</p>
</details>
<details class="admin-card form-details" <?= ($edit || $error) ? 'open' : '' ?>>
  <summary><?= $edit?'Edit':'Add new' ?> <?= $section==='questions'?'question':'answer option / pricing rule' ?>
  </summary>
  <p>
    Simple values only: percentages, part costs and whether an optional comment field is shown. New
    questions start disabled until you add answer options and enable them.
  </p>
  <form method="post" action="?section=<?= e($section) ?>" class="admin-form">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= (int)($edit['id']??0) ?>">
    <?php if ($section==='questions'): ?>
    <div class="field wide">
      <label>Question text</label>
      <input name="question_text" maxlength="255" value="<?= e($edit['question_text']??'') ?>" required>
    </div>
    <div class="field">
      <label>Category (page section)</label>
      <input name="category" maxlength="50" value="<?= e($edit['category']??'Hardware') ?>" required>
    </div>
    <div class="field">
      <label>Damage group (same issue shares highest deduction, e.g. display)</label>
      <input name="damage_group" maxlength="50" value="<?= e($edit['damage_group']??'') ?>" required>
    </div>
    <details class="wide advanced-fields" <?= ($edit || $error) ? 'open' : '' ?>>
      <summary>Advanced: show question only for certain phones/countries</summary>
      <div class="admin-form">
        <div class="field">
          <label>Country (optional)</label>
          <select name="country_id">
            <option value="">All countries</option>
            <?php foreach ($countries as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= ($edit['country_id']??0)==$c['id']?'selected':'' ?>>
              <?= e($c['country_name']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label>Brand (optional)</label>
          <select name="brand_id">
            <option value="">All brands</option>
            <?php foreach ($brands as $b): ?>
            <option value="<?= (int)$b['id'] ?>" <?= ($edit['brand_id']??0)==$b['id']?'selected':'' ?>>
              <?= e($b['brand_name']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label>Model (optional)</label>
          <select name="model_id">
            <option value="">All models</option>
            <?php foreach ($models as $m): ?>
            <option value="<?= (int)$m['id'] ?>" <?= ($edit['model_id']??0)==$m['id']?'selected':'' ?>>
              <?= e($m['brand_name'].' '.$m['model_name']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label>Question order</label>
          <input name="sort_order" type="number" min="0" max="65535" value="<?= e($edit['sort_order']??30) ?>">
        </div>
      </div>
    </details>
    <?php else: ?>
    <div class="field wide">
      <label>Question</label>
      <select name="question_id" required>
        <option value="">Choose question</option>
        <?php foreach ($questions as $q): ?>
        <option value="<?= (int)$q['id'] ?>" <?= ($edit['question_id']??0)==$q['id']?'selected':'' ?>>
          <?= e($q['question_text']) ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field wide">
      <label>Answer text</label>
      <input name="option_text" maxlength="150" required value="<?= e($edit['option_text']??'') ?>">
    </div>
    <div class="field">
      <label>Deduction method</label>
      <select name="rule_type">
        <?php foreach (['none'=>'No deduction','percentage'=>'% of base price','part_cost'=>'% of local part cost'] as $k=>$v): ?>
        <option value="<?= $k ?>" <?= ($edit['rule_type']??'none')===$k?'selected':'' ?>>
          <?= e($v) ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>
    <details class="wide advanced-fields" <?= ($edit || $error) ? 'open' : '' ?>>
      <summary>Set deduction amount (open when choosing a % rule)</summary>
      <div class="admin-form">
        <div class="field">
          <label>Percentage of base price</label>
          <input
            name="percentage_value"
            type="number"
            min="0"
            max="100"
            step="0.01"
            value="<?= e($edit['percentage_value']??0) ?>">
        </div>
        <div class="field">
          <label>Percentage of part cost</label>
          <input
            name="part_cost_percent"
            type="number"
            min="0"
            max="100"
            step="0.01"
            value="<?= e($edit['part_cost_percent']??0) ?>">
        </div>
        <div class="field">
          <label>Part (only for part-cost rule)</label>
          <select name="part_type_id">
            <option value="">No part</option>
            <?php foreach ($parts as $p): ?>
            <option value="<?= (int)$p['id'] ?>" <?= ($edit['part_type_id']??0)==$p['id']?'selected':'' ?>>
              <?= e($p['part_name']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label>Use repair or replacement cost?</label>
          <select name="cost_kind">
            <option value="">Not applicable</option>
            <option value="repair" <?= ($edit['cost_kind']??'')==='repair'?'selected':'' ?>>Repair cost</option>
            <option value="replacement" <?= ($edit['cost_kind']??'')==='replacement'?'selected':'' ?>>Replacement cost</option>
          </select>
        </div>
        <div class="field">
          <label>Fallback % of base price (if local part cost missing)</label>
          <input
            name="fallback_percent"
            type="number"
            min="0"
            max="100"
            step="0.01"
            value="<?= e($edit['fallback_percent']??0) ?>">
        </div>
      </div>
    </details>
    <div class="field">
      <label>Score penalty (0–100)</label>
      <input name="score_penalty" type="number" min="0" max="100" value="<?= e($edit['score_penalty']??0) ?>">
    </div>
    <details class="wide advanced-fields" <?= ($edit || $error) ? 'open' : '' ?>>
      <summary>Advanced: PTA selection, comments, stop estimate, display order</summary>
      <div class="admin-form">
        <div class="field">
          <label>Market selector (only Pakistan PTA question)</label>
          <select name="market_type">
            <option value="">None</option>
            <option value="pta_approved" <?= ($edit['market_type']??'')==='pta_approved'?'selected':'' ?>>PTA approved</option>
            <option value="non_pta" <?= ($edit['market_type']??'')==='non_pta'?'selected':'' ?>>Non-PTA</option>
            <option value="standard" <?= ($edit['market_type']??'')==='standard'?'selected':'' ?>>Standard</option>
          </select>
        </div>
        <div class="field">
          <label>Option order</label>
          <input name="sort_order" type="number" min="0" max="65535" value="<?= e($edit['sort_order']??1) ?>">
        </div>
        <div class="checkbox-field">
          <label>
            <input type="checkbox" name="needs_note" value="1" <?= !empty($edit['needs_note'])?'checked':'' ?>> Show optional comment box</label>
          <small>Tick for damaged, uncertain or special answers.</small>
        </div>
        <div class="checkbox-field">
          <label>
            <input
              type="checkbox"
              name="blocks_valuation"
              value="1"
              <?= !empty($edit['blocks_valuation'])?'checked':'' ?>>
            Stop normal valuation</label>
          <small>Use for activation locks, unsafe devices, and unknown ownership.</small>
        </div>
      </div>
    </details>
    <?php endif; ?>
    <div class="form-actions">
      <button class="button primary small">Save <?= $section==='questions'?'question':'rule' ?>
      </button>
      <?php if ($edit): ?>
      <a href="?section=<?= e($section) ?>" class="button secondary small">Cancel</a>
      <?php endif; ?>
    </div>
  </form>
</details>
<div class="admin-card">
  <h2>Existing <?= $section ?>
  </h2>
  <?php if ($section==='options'): ?>
  <form class="filter-row" method="get">
    <input type="hidden" name="section" value="options">
    <select name="question_id">
      <option value="0">All questions</option>
      <?php foreach ($questions as $q): ?>
      <option value="<?= (int)$q['id'] ?>" <?= (int)($_GET['question_id']??0)==$q['id']?'selected':'' ?>>
        <?= e($q['question_text']) ?>
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
            <?= $section==='options'?'Question':'Question text' ?>
          </th>
          <?php if ($section==='options'): ?>
          <th>Option</th>
          <th>Rule</th>
          <th>Details</th>
          <?php else: ?>
          <th>Category</th>
          <th>Group</th>
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
        if ($section==='questions') $list=rows($pdo,
          "SELECT * FROM condition_questions ORDER BY sort_order,id LIMIT 21 OFFSET $offset");
        else {
           $questionId = (int)($_GET['question_id']??0);
           $list = rows($pdo,
              "SELECT o.*,q.question_text FROM condition_options o JOIN condition_questions q ON q.id=o.question_id
               WHERE (?=0 OR o.question_id=?) ORDER BY q.sort_order,o.sort_order,o.id LIMIT 21 OFFSET $offset",[$questionId,$questionId]);
        }
        $hasMore = count($list) > 20;
        $list = array_slice($list, 0, 20);
        foreach ($list as $r): ?>
        <tr>
          <td><?= e($r['question_text']) ?>
          </td>
          <?php if ($section==='questions'): ?><td><?= e($r['category']) ?></td><td><?= e($r['damage_group']) ?></td>
          <?php else: ?>
          <td>
            <?= e($r['option_text']) ?>
          </td>
          <td>
            <?= e($r['rule_type']) ?>
          </td>
          <td>
            <small>
              Base: <?= e($r['percentage_value']) ?>% ·
              Part: <?= e($r['part_cost_percent']) ?>% ·
              Fallback: <?= e($r['fallback_percent']) ?>% ·
              Note: <?= $r['needs_note']?'Yes':'No' ?>
            </small>
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
    'question_id' => (int) ($_GET['question_id'] ?? 0)
  ]); ?>
</div>
<?php admin_footer(); ?>
