<?php

require_once __DIR__ . '/../includes/layout.php';
site_header('Home');
?>
<main>
  <section class="hero">
    <div class="container">
      <p class="eyebrow">USED PHONE PRICE ESTIMATOR</p>
      <h1>Know what your phone is worth.</h1>
      <p class="hero-lead">
        Select your phone, answer a few condition questions and see an
        estimated resale price based on sample local market data.
      </p>
      <div class="button-row">
        <a class="button primary" href="select-phone.php">
          Check my phone value
        </a>
        <a class="button secondary" href="how.php">
          How it works
        </a>
      </div>
      <p class="hero-foot">No signup required. Country-specific prices. Clear deductions.</p>
    </div>
  </section>
  <section class="container section">
    <h2>Three simple steps</h2>
    <div class="step-grid">
      <div class="step">
        <h3>1. Choose your phone</h3>
        <p>Select country, brand, model and storage.</p>
      </div>
      <div class="step">
        <h3>2. Check its condition</h3>
        <p>Answer questions about the screen, battery and other parts.</p>
      </div>
      <div class="step">
        <h3>3. See the estimate</h3>
        <p>View the price, deductions and condition score.</p>
      </div>
    </div>
  </section>
</main>
<?php site_footer(); ?>
