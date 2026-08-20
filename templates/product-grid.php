<?php
/**
 * Port of src/components/ProductGrid.astro: resolves product references,
 * drops unresolved/inactive ones, and renders nothing if none remain.
 * @var array $refs
 * @var array $collections
 */
$items = [];
foreach ($refs as $ref) {
    $product = $collections['products'][$ref] ?? null;
    if ($product !== null && ($product['data']['active'] ?? true) !== false) {
        $items[] = $product;
    }
}
?>
<?php if (count($items) > 0): ?>
<aside aria-label="Produktempfehlungen">
  <p><small>Anzeige — Als Amazon-Partner verdient diese Seite an qualifizierten Käufen.</small></p>
  <ul>
<?php foreach ($items as $product): $pd = $product['data']; ?>
    <li>
      <a href="<?= h($pd['url'] ?? '') ?>" rel="sponsored nofollow noopener" target="_blank">
<?php if (!empty($pd['image'])): ?>
        <img src="<?= h($pd['image']) ?>" alt="<?= h($pd['imageAlt'] ?? '') ?>" loading="lazy" decoding="async" />
<?php endif; ?>
        <?= h($pd['title'] ?? '') ?>
      </a>
<?php if (!empty($pd['blurb'])): ?>
      <p><?= h($pd['blurb']) ?></p>
<?php endif; ?>
    </li>
<?php endforeach; ?>
  </ul>
</aside>
<?php endif; ?>
