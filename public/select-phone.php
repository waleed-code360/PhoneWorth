<?php

require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/db.php';

$allCountries = rows($pdo,
    "SELECT c.id, c.country_name, c.currency_code, c.iso_code
     FROM countries c
     WHERE c.is_active=1
       AND EXISTS (
         SELECT 1 FROM market_prices p
         WHERE p.country_id=c.id AND p.is_active=1
           AND ((c.iso_code='PK' AND p.market_type IN ('pta_approved','non_pta'))
             OR (c.iso_code<>'PK' AND p.market_type='standard'))
       )
     ORDER BY c.country_name, c.id"
);

$countries = [];
$seenCountries = [];
foreach ($allCountries as $countryRow) {
    $key = strtolower(trim($countryRow['country_name']));
    if (!isset($seenCountries[$key])) {
        $countries[] = $countryRow;
        $seenCountries[$key] = count($countries) - 1;
    } elseif ($countryRow['iso_code'] === 'PK') {
        $countries[$seenCountries[$key]] = $countryRow;
    }
}
$brands = rows($pdo, 'SELECT id, brand_name FROM brands WHERE is_active=1 ORDER BY brand_name');
$models = rows(
    $pdo,
    'SELECT m.id, m.brand_id, m.model_name
        FROM phone_models m
        JOIN brands b ON b.id=m.brand_id
        WHERE m.is_active=1
        AND b.is_active=1
        ORDER BY m.model_name'
);
$variants = rows(
    $pdo,
    'SELECT v.id, v.model_id, v.ram_gb, v.storage_gb
        FROM phone_variants v
        JOIN phone_models m ON m.id=v.model_id
        WHERE v.is_active=1
        AND m.is_active=1
        ORDER BY v.storage_gb'
);


$prices = rows($pdo,
    "SELECT DISTINCT p.country_id, p.variant_id
     FROM market_prices p
     JOIN countries c ON c.id = p.country_id
     WHERE p.is_active = 1
       AND ((c.iso_code = 'PK' AND p.market_type IN ('pta_approved', 'non_pta'))
         OR (c.iso_code <> 'PK' AND p.market_type = 'standard'))"
);
$data = ['countries'=>$countries, 'brands'=>$brands, 'models'=>$models, 'variants'=>$variants, 'prices'=>$prices];

site_header('Choose Phone');
?>
<main class="container content-page">
  <div class="page-title">
    <p class="eyebrow dark">STEP 1 OF 3</p>
    <h1>Choose your phone</h1>
    <p>Choose the country first. The next options will appear automatically.</p>
  </div>
  <div class="form-layout">
    <div class="panel">
      <form action="check.php" method="get" id="phone-form">
        <div class="field">
          <label for="country">Country *</label>
          <select id="country" name="country_id" required>
            <option value="">Choose country</option>
          </select>
        </div>
        <div class="field">
          <label for="currency">Currency</label>
          <input id="currency" type="text" placeholder="Selected automatically" readonly>
        </div>
        <div class="field">
          <label for="brand">Brand *</label>
          <select id="brand" required disabled>
            <option value="">Choose brand</option>
          </select>
        </div>
        <div class="field">
          <label for="model">Model *</label>
          <select id="model" required disabled>
            <option value="">Choose model</option>
          </select>
        </div>
        <div class="field">
          <label for="variant">RAM / Storage *</label>
          <select id="variant" name="variant_id" required disabled>
            <option value="">Choose storage</option>
          </select>
        </div>
        <p class="subtle">
          Only countries and phones with a saved local base price are shown. Estimates depend on the entered
          data.
        </p>
        <button type="submit" class="button primary">Next: Phone condition</button>
      </form>
    </div>
  </div>
</main>

<script type="application/json" id="phone-data">
  <?= json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
</script>
<script src="js/phone.js" defer></script>
<?php site_footer(); ?>
