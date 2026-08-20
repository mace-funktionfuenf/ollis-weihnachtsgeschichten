<?php
/**
 * Port of src/pages/[...slug].astro.
 * @var array $entry
 * @var string $kind
 * @var array|null $author
 * @var string $bodyHtml
 * @var string $productGridHtml
 */
$data = $entry['data'];
?>
<article>
  <h1><?= h($data['title'] ?? '') ?></h1>
<?php if ($kind !== 'pages'): ?>
  <p>
<?php if ($author): ?>
    Von <?= h($author['data']['displayName'] ?? $author['data']['name'] ?? '') ?> —
<?php endif; ?>
<?php if (array_key_exists('year', $data)): ?>
    <?= h((string) $data['year']) ?>
<?php endif; ?>
  </p>
<?php endif; ?>
<?php if (!empty($data['heroImage'])): ?>
  <img src="<?= h($data['heroImage']) ?>" alt="<?= h($data['heroImageAlt'] ?? '') ?>" loading="lazy" decoding="async" />
<?php endif; ?>
<?= $bodyHtml ?>
<?= $productGridHtml ?>
</article>
