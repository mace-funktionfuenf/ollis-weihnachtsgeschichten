<?php
/**
 * Port of src/pages/index.astro. $stories is already filtered to
 * kind === 'jahresgeschichte' and sorted by year descending (see Renderer).
 * @var array $stories
 */
?>
<h1>Ollis Weihnachtsgeschichten</h1>
<p>
  Jedes Jahr zur Weihnachtszeit schreibt unser Autor Olaf Taubert eine neue lustige
  Weihnachtsgeschichte rund um die Familie Staude.
</p>
<h2>Alle Geschichten</h2>
<ul>
<?php foreach ($stories as $story): ?>
  <li>
    <a href="/<?= h($story['id']) ?>/">
      <?= h((string) ($story['data']['year'] ?? '')) ?>: <?= h($story['data']['title'] ?? '') ?>
    </a>
  </li>
<?php endforeach; ?>
</ul>
