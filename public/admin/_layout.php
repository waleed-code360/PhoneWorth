<?php

function admin_header($title, $active) {
    site_header($title, true);
    $pages = [
        'dashboard.php' => 'Dashboard',
        'guide.php' => 'Guide',
        'phones.php' => 'Phones',
        'countries.php' => 'Countries',
        'market.php' => 'Market Prices',
        'parts.php' => 'Repair Costs',
        'rules.php' => 'Questions & Rules',
        'history.php' => 'History',
        'account.php' => 'My Account'
    ];

    if (!empty($_SESSION['is_owner'])) {
        $pages['admins.php'] = 'Manage Admins';
    }
    ?>
<main class="container admin-content">
  <div class="admin-intro">
    <div>
      <p class="eyebrow dark">ADMIN DASHBOARD</p>
      <h1><?= e($title) ?>
      </h1>
    </div>
    <form method="post" action="logout.php">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <button class="button secondary small" type="submit">Log out</button>
    </form>
  </div>
  <div class="admin-layout">
    <nav class="admin-sidebar" aria-label="Admin pages">
      <?php foreach ($pages as $file => $label): ?>
      <a href="<?= e($file) ?>" class="<?= $active === $file ? 'active' : '' ?>"><?= e($label) ?>
      </a>
      <?php endforeach; ?>
    </nav>
    <div class="admin-main">
      <?php show_flash(); ?>
      <?php
      }

      function admin_footer() {
          echo '</div></div></main>';
          site_footer();
      }

      function active_label($value) {
          return (int) $value === 1 ? '<span class="pill on">Active</span>' : '<span class="pill off">Inactive</span>';
      }

      function admin_tabs($tabs, $selected) {
          echo '<div class="tabs">';
          foreach ($tabs as $value => $label) {
              $style = $selected === $value ? 'active' : '';
              echo '<a class="' . $style . '" href="?section=' . e($value) . '">' . e($label) . '</a>';
          }
          echo '</div>';
      }

      function toggle_form($id, $current) {
          ?>
      <form method="post" class="inline-form">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="toggle">
        <input type="hidden" name="id" value="<?= (int) $id ?>">
        <button class="mini-button" type="submit"><?= $current ? 'Disable' : 'Enable' ?>
        </button>
      </form>
      <?php
      }

      
      function admin_pages($page, $hasNext, $filters = []) {
          echo '<div class="page-links">';
          if ($page > 1) {
              $previous = http_build_query(array_merge($filters, ['page' => $page - 1]));
              echo '<a class="mini-button" href="?' . e($previous) . '">Previous</a>';
          }
          echo '<span>Page ' . (int) $page . '</span>';
          if ($hasNext) {
              $next = http_build_query(array_merge($filters, ['page' => $page + 1]));
              echo '<a class="mini-button" href="?' . e($next) . '">Next</a>';
          }
          echo '</div>';
      }
