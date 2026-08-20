<?php
/**
 * Generic list view — works for any collection via schema.php.
 * @var \App\Auth $auth
 * @var array $schema
 * @var string $collectionKey
 * @var array $collectionDef
 * @var array $entries  id => entry (from ContentLoader)
 */
require __DIR__ . '/partials/header.php';
?>
<h1><?= h($collectionDef['label']) ?></h1>

<?php if ($collectionDef['allowCreate'] ?? true): ?>
  <p><a href="index.php?action=edit&amp;collection=<?= h($collectionKey) ?>">Neu anlegen</a></p>
<?php endif; ?>

<table>
  <thead>
    <tr>
      <?php foreach ($collectionDef['listColumns'] as $col): ?>
        <th><?= h($col) ?></th>
      <?php endforeach; ?>
      <th></th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($entries as $id => $entry): ?>
      <tr>
        <?php foreach ($collectionDef['listColumns'] as $col): ?>
          <td><?= h((string) ($entry['data'][$col] ?? '')) ?></td>
        <?php endforeach; ?>
        <td>
          <a href="index.php?action=edit&amp;collection=<?= h($collectionKey) ?>&amp;id=<?= h((string) $id) ?>">Bearbeiten</a>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (count($entries) === 0): ?>
      <tr><td colspan="<?= count($collectionDef['listColumns']) + 1 ?>">Noch keine Einträge.</td></tr>
    <?php endif; ?>
  </tbody>
</table>
<?php require __DIR__ . '/partials/footer.php'; ?>
