<?php
/**
 * Port of src/layouts/Layout.astro.
 * @var string $title
 * @var string|null $description
 * @var string $content
 */
?>
<!doctype html>
<html lang="de">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="icon" href="data:," />
    <link rel="stylesheet" href="/css/style.css" />
    <title><?= h($title) ?></title>
<?php if ($description): ?>
    <meta name="description" content="<?= h($description) ?>" />
<?php endif; ?>
  </head>
  <body>
    <header>
      <a href="/">Ollis Weihnachtsgeschichten</a>
      <nav>
        <a href="/">Geschichten</a>
        <a href="/adventskalender/">Adventskalender</a>
      </nav>
    </header>
    <main>
<?= $content ?>
    </main>
    <footer>
      <nav>
        <a href="/impressum/">Impressum</a>
      </nav>
      <p>Als Amazon-Partner verdient diese Seite an qualifizierten Käufen.</p>
    </footer>
  </body>
</html>
