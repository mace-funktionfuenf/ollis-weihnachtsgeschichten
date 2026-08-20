<?php
/**
 * Shared chrome for every logged-in admin page.
 * @var \App\Auth $auth
 * @var array $schema
 */
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Verwaltung — Ollis Weihnachtsgeschichten</title>
</head>
<body>
  <header>
    <strong>Verwaltung</strong>
    <nav>
      <?php foreach ($schema as $key => $def): ?>
        <a href="index.php?action=list&amp;collection=<?= h($key) ?>"><?= h($def['label']) ?></a>
      <?php endforeach; ?>
      <?php if ($auth->isAdmin()): ?>
        <a href="index.php?action=users">Benutzer</a>
      <?php endif; ?>
    </nav>
    <span>Angemeldet als <?= h((string) $auth->currentUsername()) ?></span>
    <a href="index.php?action=logout">Abmelden</a>
  </header>
  <?php if ($flash): ?>
    <p role="status"><?= h($flash) ?></p>
  <?php endif; ?>
  <main>
