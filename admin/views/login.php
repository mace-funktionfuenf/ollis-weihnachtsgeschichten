<?php
/**
 * @var \App\Auth $auth
 * @var string|null $error
 */
?>
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Anmelden — Ollis Weihnachtsgeschichten</title>
</head>
<body>
  <main>
    <h1>Verwaltung — Anmelden</h1>
    <?php if ($error): ?>
      <p role="alert"><?= h($error) ?></p>
    <?php endif; ?>
    <form method="post" action="index.php?action=login">
      <input type="hidden" name="csrf_token" value="<?= h($auth->csrfToken()) ?>" />
      <p>
        <label>Benutzername<br />
          <input type="text" name="username" required autofocus />
        </label>
      </p>
      <p>
        <label>Passwort<br />
          <input type="password" name="password" required />
        </label>
      </p>
      <button type="submit">Anmelden</button>
    </form>
  </main>
</body>
</html>
