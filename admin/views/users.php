<?php
/**
 * Admin-only: create editor/admin accounts, toggle active state. No hard
 * delete anywhere — an admin can't accidentally lock everyone out.
 * @var \App\Auth $auth
 * @var array $schema
 * @var array $users
 * @var string[] $errors
 */
require __DIR__ . '/partials/header.php';
?>
<h1>Benutzer</h1>

<?php if ($errors): ?>
  <ul role="alert">
    <?php foreach ($errors as $error): ?>
      <li><?= h($error) ?></li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>

<table>
  <thead>
    <tr><th>Benutzername</th><th>Rolle</th><th>Status</th><th></th></tr>
  </thead>
  <tbody>
    <?php foreach ($users as $user): ?>
      <tr>
        <td><?= h($user['username']) ?></td>
        <td><?= h($user['role']) ?></td>
        <td><?= (int) $user['active'] === 1 ? 'Aktiv' : 'Deaktiviert' ?></td>
        <td>
          <form method="post" action="index.php?action=toggle-user" style="display:inline">
            <input type="hidden" name="csrf_token" value="<?= h($auth->csrfToken()) ?>" />
            <input type="hidden" name="id" value="<?= (int) $user['id'] ?>" />
            <input type="hidden" name="active" value="<?= (int) $user['active'] === 1 ? '0' : '1' ?>" />
            <button type="submit"><?= (int) $user['active'] === 1 ? 'Deaktivieren' : 'Aktivieren' ?></button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<h2>Neuen Benutzer anlegen</h2>
<form method="post" action="index.php?action=save-user">
  <input type="hidden" name="csrf_token" value="<?= h($auth->csrfToken()) ?>" />
  <p>
    <label>Benutzername<br />
      <input type="text" name="username" required />
    </label>
  </p>
  <p>
    <label>Passwort<br />
      <input type="password" name="password" required minlength="8" />
    </label>
  </p>
  <p>
    <label>Rolle<br />
      <select name="role">
        <option value="editor">Redakteur</option>
        <option value="admin">Administrator</option>
      </select>
    </label>
  </p>
  <button type="submit">Anlegen</button>
</form>
<?php require __DIR__ . '/partials/footer.php'; ?>
