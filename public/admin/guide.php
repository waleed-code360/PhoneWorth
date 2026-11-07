<?php
require_once __DIR__ . '/_start.php';
require_once __DIR__ . '/_layout.php';
admin_header('Admin guide', 'guide.php');
?>
<div class="admin-card">
  <h2>How to manage a new phone</h2>
  <p>Follow these steps in order. You don't need to change PHP source code.</p>
  <ol class="guide-steps">
    <li>
      <strong>Brand:</strong>
      <a href="phones.php?section=brands">Add the brand</a> if it does not exist.</li>
    <li>
      <strong>Model:</strong>
      <a href="phones.php?section=models">Add a model</a> under that brand.</li>
    <li>
      <strong>Storage:</strong>
      <a href="phones.php?section=variants">Add RAM and storage</a> for the model.</li>
    <li>
      <strong>Local price:</strong>
      <a href="market.php">Enter the good-condition used-phone price</a> for a country and market status.</li>
    <li>
      <strong>Repairs:</strong>
      <a href="parts.php?section=costs">Enter the model's local repair costs</a> (screen, battery, camera, etc.).</li>
    <li>
      <strong>Rules:</strong>
      <a href="rules.php?section=options">Review the deduction rules</a> only if necessary.</li>
  </ol>
  <div class="alert info">
    A phone appears on the public side only after it has an active model, variant and local market price.
    New valuations use the latest rules; old results stay saved.
  </div>
</div>
<div class="admin-card">
  <h2>How visitors use PhoneWorth</h2>
  <p>Home &rarr; Select phone &rarr; Answer condition questions &rarr; View estimated price.</p>
  <p>
    Visitors never need an account. The additional comment box appears only for answers marked as
    needing explanation.
  </p>
</div>
<div class="admin-card">
  <h2>Admin access</h2>
  <p>
    There is no public admin-registration page or admin link in the website navigation. Administrators
    sign in directly using <code>/admin/login.php</code>.
  </p>
  <p>
    The project owner can add or deactivate admins from Manage Admins.
    The owner's email is set once in the private config/database.php file.
    Separate localhost installations have separate databases.
    Accounts work across devices only when both access the same hosted website.
  </p>
  <?php if (!empty($_SESSION['is_owner'])): ?>
  <p>
    <a href="admins.php">Open Manage Admins</a>
  </p>
  <?php endif; ?>
</div>
<div class="admin-card">
  <h2>Example: local repair cost, step by step</h2>
  <ol class="guide-steps">
    <li>
      <a href="parts.php?section=types">Part Types</a>: confirm Screen exists. Add only if missing.</li>
    <li>
      <a href="parts.php?section=costs">Local Repair Costs</a>: choose phone model, Screen and Pakistan.</li>
    <li>Enter the amount to repair and/or replace that screen, in PKR. Save.</li>
    <li>
      <a href="rules.php?section=options">Answer Options</a>: edit the Cracked Screen answer.</li>
    <li>Choose Part Cost, Screen, Replacement and a factor such as 80%.</li>
    <li>Choose a fallback base-price percentage if local part cost is unavailable.</li>
    <li>Run a NEW customer valuation and check the breakdown.</li>
  </ol>
  <p class="subtle">Illustrative numbers only. The actual model/country records in MySQL
    determine the deduction; old historical valuations remain unchanged.</p>
</div>
<div class="admin-card">
  <h2>Questions & Rules: two tabs, two jobs</h2>
  <p>
    <strong>Questions tab:</strong> question text, category, damage group,
    optional country/brand/model filter and order. New questions start disabled.
    Add answer options first, then enable the question.</p>
  <p>
    <strong>Answer Options tab:</strong> choose a question, write each answer,
    select None / % of Base / % of Part Cost, then set a score penalty.
    Use Optional Comment for unusual answers and Stop Valuation when an
    inspection is required.</p>
  <p>For Quick Check, only the configured main damage groups are used.
    Detailed Check includes all active questions that match the phone.</p>
</div>
<div class="admin-card">
  <h2>Country and admin troubleshooting</h2>
  <p>Country codes use letters: Pakistan PK / PKR, United Arab Emirates AE / AED.
    Estimate range 5% creates a 5% range above and below the calculated estimate.</p>
  <p>If Pakistan appears twice, open <a href="countries.php">Countries</a>.
    Compare IDs, ISO codes and how many linked prices each row has.
    Disable the extra entry only after verifying which country is used.</p>
  <p>All admin/history timestamps are displayed in PKT (UTC+05:00).</p>
  <p>For forgotten passwords, the project owner can reset another admin's password
    on <a href="admins.php">Manage Admins</a>. Separate localhost servers do not
    share the same users table. Never give out the owner or database password.</p>
</div>
<?php admin_footer(); ?>
