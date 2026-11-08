<?php
require_once __DIR__ . '/functions.php';


function site_header($title = 'Home', $admin = false) {
    $path = $admin ? '../' : '';
    ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title) ?> | PhoneWorth</title>
  <link rel="stylesheet" href="<?= $path ?>css/style.css">
</head>
<body>
  <header class="top-header">
    <div class="container top-inner">
      <a class="logo" href="<?= $path ?>index.php">Phone<span>Worth</span>
      </a>
      <nav class="top-nav" aria-label="Main navigation">
        <a href="<?= $path ?>index.php">Home</a>
        <a href="<?= $path ?>select-phone.php">Check Price</a>
        <a href="<?= $path ?>how.php">How It Works</a>
      </nav>
    </div>
  </header>
<?php
}

function site_footer() {
    ?>
</body>
</html>
<?php
}
