<?php
/**
 * Variant of product-grid.php for pages with `productsByAudience: true`
 * (currently just Geschenkideen): instead of resolving a hand-picked list
 * of refs, pulls the whole products collection and buckets every active
 * product by its existing `audience` tag into three fixed sections. A
 * product tagged for more than one audience appears in each matching
 * section.
 * @var array $collections
 */
$groups = [
    'kinder' => ['label' => 'Geschenkideen für Kinder', 'items' => []],
    'erwachsene' => ['label' => 'Geschenkideen für Erwachsene', 'items' => []],
    'familie' => ['label' => 'Geschenkideen für die Familie', 'items' => []],
];
foreach ($collections['products'] as $product) {
    $pd = $product['data'];
    if (($pd['active'] ?? true) === false) {
        continue;
    }
    foreach ($pd['audience'] ?? [] as $audience) {
        if (isset($groups[$audience])) {
            $groups[$audience]['items'][] = $product;
        }
    }
}
$hasAny = array_sum(array_map(static fn(array $g) => count($g['items']), $groups));
?>
<?php if ($hasAny > 0): ?>
<aside aria-label="Produktempfehlungen">
  <p><small>Anzeige — Als Amazon-Partner verdient diese Seite an qualifizierten Käufen.</small></p>
<?php foreach ($groups as $group): ?>
<?php if (count($group['items']) === 0) continue; ?>
  <h2><?= h($group['label']) ?></h2>
  <ul>
<?php foreach ($group['items'] as $product): $pd = $product['data']; ?>
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
<?php endforeach; ?>
</aside>
<?php endif; ?>
