<?php
require_once __DIR__ . '/../includes/layout.php';
site_header('How it works');
?>
<main class="container content-page">
  <div class="page-title">
    <span class="eyebrow dark">THE METHOD</span>
    <h1>How PhoneWorth works</h1>
    <p>No AI, no hidden predictions. Just prices and rules stored in MySQL.</p>
  </div>
  <div class="step-grid">
    <article class="step">
      <span class="step-number">01</span>
      <h3>Local base price</h3>
      <p>
        PHP finds the selected used-phone variant's base value for its country. Pakistan has separate PTA
        and Non-PTA prices.
      </p>
    </article>
    <article class="step">
      <span class="step-number">02</span>
      <h3>Condition questions</h3>
      <p>
        Choose Quick Check (essential questions) or Detailed Check (every available condition question).
        Damage answers may show an optional comment field.
      </p>
    </article>
    <article class="step">
      <span class="step-number">03</span>
      <h3>Repair adjustments</h3>
      <p>
        PHP uses model and country-specific costs, or a clear fallback rule, to calculate deductions
        without double-counting overlapping faults.
      </p>
    </article>
  </div>
  <div class="panel text-panel">
    <h2>Simple price formula</h2>
    <p>
      <strong>Estimated value = good-condition base price − allowed deductions.</strong>
    </p>
    <p>
      Example repair rule: if a screen costs 40,000 to replace and its rule is 80%,
      the screen deduction is 32,000. The 80% is an example rule, not a guaranteed
      resale-market fact.
    </p>
    <p>
      Actual sale prices can vary. All initial records in this university project are <strong>fictional
        examples</strong> until the administrator replaces them with researched reference prices.
    </p>
    <a class="button primary" href="select-phone.php">Try the estimator &rarr;</a>
  </div>
</main>
<?php site_footer(); ?>
