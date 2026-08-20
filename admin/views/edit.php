<?php
/**
 * Generic edit/create form — one field loop driven entirely by schema.php.
 * @var \App\Auth $auth
 * @var array $schema
 * @var string $collectionKey
 * @var array $collectionDef
 * @var string|null $entryId       null when creating a new entry
 * @var array $data                current field values (posted values on validation failure, else loaded entry / defaults)
 * @var string[] $errors
 * @var array $referenceOptions    targetCollection => [id => title]
 */
require __DIR__ . '/partials/header.php';
?>
<h1><?= $entryId ? 'Bearbeiten: ' . h((string) ($data['title'] ?? $entryId)) : 'Neu: ' . h($collectionDef['labelSingular']) ?></h1>

<?php if ($errors): ?>
  <ul role="alert">
    <?php foreach ($errors as $error): ?>
      <li><?= h($error) ?></li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>

<form method="post" action="index.php?action=save">
  <input type="hidden" name="csrf_token" value="<?= h($auth->csrfToken()) ?>" />
  <input type="hidden" name="collection" value="<?= h($collectionKey) ?>" />
  <?php if ($entryId): ?>
    <input type="hidden" name="id" value="<?= h($entryId) ?>" />
  <?php endif; ?>

  <?php foreach ($collectionDef['fields'] as $field): ?>
    <?php
      $name = $field['name'];
      $value = $data[$name] ?? ($field['default'] ?? '');
      $required = !empty($field['required']);
      $isGroup = in_array($field['type'], ['multiselect', 'reference-multi'], true);
    ?>
    <?php if ($isGroup): ?>
      <fieldset>
        <legend><?= h($field['label']) ?><?= $required ? ' *' : '' ?></legend>
        <?php
          $options = $field['type'] === 'reference-multi'
              ? ($referenceOptions[$field['collection']] ?? [])
              : $field['options'];
          $selected = array_map('strval', is_array($value) ? $value : []);
        ?>
        <?php foreach ($options as $optValue => $optLabel): ?>
          <label>
            <input type="checkbox" name="fields[<?= h($name) ?>][]" value="<?= h((string) $optValue) ?>"
              <?= in_array((string) $optValue, $selected, true) ? 'checked' : '' ?> />
            <?= h($optLabel) ?>
          </label>
        <?php endforeach; ?>
      </fieldset>
    <?php else: ?>
      <p>
        <label>
          <?= h($field['label']) ?><?= $required ? ' *' : '' ?><br />

          <?php if ($field['type'] === 'string'): ?>
            <input type="text" name="fields[<?= h($name) ?>]" value="<?= h((string) $value) ?>" <?= $required ? 'required' : '' ?> />

          <?php elseif ($field['type'] === 'number'): ?>
            <input type="number" name="fields[<?= h($name) ?>]" value="<?= h((string) $value) ?>" <?= $required ? 'required' : '' ?> />

          <?php elseif ($field['type'] === 'date'): ?>
            <input type="date" name="fields[<?= h($name) ?>]" value="<?= h(substr((string) $value, 0, 10)) ?>" <?= $required ? 'required' : '' ?> />

          <?php elseif ($field['type'] === 'textarea'): ?>
            <textarea name="fields[<?= h($name) ?>]" rows="4" <?= $required ? 'required' : '' ?>><?= h((string) $value) ?></textarea>

          <?php elseif ($field['type'] === 'markdown'): ?>
            <textarea name="fields[<?= h($name) ?>]" rows="20" cols="80" <?= $required ? 'required' : '' ?>><?= h((string) $value) ?></textarea>

          <?php elseif ($field['type'] === 'taglist'): ?>
            <input type="text" name="fields[<?= h($name) ?>]"
              value="<?= h(is_array($value) ? implode(', ', $value) : (string) $value) ?>"
              placeholder="Kommagetrennt" <?= $required ? 'required' : '' ?> />

          <?php elseif ($field['type'] === 'boolean'): ?>
            <input type="checkbox" name="fields[<?= h($name) ?>]" value="1" <?= $value ? 'checked' : '' ?> />

          <?php elseif ($field['type'] === 'select'): ?>
            <select name="fields[<?= h($name) ?>]" <?= $required ? 'required' : '' ?>>
              <?php if (!$required): ?><option value="">—</option><?php endif; ?>
              <?php foreach ($field['options'] as $optValue => $optLabel): ?>
                <option value="<?= h((string) $optValue) ?>" <?= (string) $value === (string) $optValue ? 'selected' : '' ?>><?= h($optLabel) ?></option>
              <?php endforeach; ?>
            </select>

          <?php elseif ($field['type'] === 'reference'): ?>
            <select name="fields[<?= h($name) ?>]" <?= $required ? 'required' : '' ?>>
              <option value="">—</option>
              <?php foreach ($referenceOptions[$field['collection']] ?? [] as $optId => $optTitle): ?>
                <option value="<?= h((string) $optId) ?>" <?= (string) $value === (string) $optId ? 'selected' : '' ?>><?= h($optTitle) ?></option>
              <?php endforeach; ?>
            </select>
          <?php endif; ?>
        </label>
      </p>
    <?php endif; ?>
  <?php endforeach; ?>

  <button type="submit">Speichern</button>
  <a href="index.php?action=list&amp;collection=<?= h($collectionKey) ?>">Abbrechen</a>
</form>
<?php require __DIR__ . '/partials/footer.php'; ?>
