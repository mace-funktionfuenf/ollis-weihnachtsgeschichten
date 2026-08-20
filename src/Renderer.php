<?php

declare(strict_types=1);

namespace App;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\SmartPunct\SmartPunctExtension;
use League\CommonMark\MarkdownConverter;

/**
 * Equivalent of Astro's getStaticPaths() + Layout.astro + [...slug].astro +
 * index.astro: turns the in-memory content map into `slug/index.html` files
 * under trailingSlash:'always', build.format:'directory' conventions.
 */
final class Renderer
{
    private MarkdownConverter $markdown;

    public function __construct(
        private readonly string $templatesDir,
        private readonly string $outDir,
    ) {
        // Astro's default markdown pipeline applies smartypants (curly
        // quotes, en/em dashes) — SmartPunctExtension replicates that so
        // rendered prose matches the existing Astro output.
        $environment = new Environment();
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new SmartPunctExtension());
        $this->markdown = new MarkdownConverter($environment);
    }

    /** @return string[] every generated route, e.g. "/", "/impressum/" */
    public function renderAll(array $collections): array
    {
        $routes = [$this->renderHome($collections)];

        foreach (['stories', 'posts', 'pages'] as $kind) {
            foreach ($collections[$kind] as $entry) {
                $routes[] = $this->renderDetail($entry, $kind, $collections);
            }
        }

        return $routes;
    }

    private function renderHome(array $collections): string
    {
        $stories = array_values(array_filter(
            $collections['stories'],
            static fn(array $e) => ($e['data']['kind'] ?? null) === 'jahresgeschichte'
        ));
        usort($stories, static fn(array $a, array $b) => ($b['data']['year'] ?? 0) <=> ($a['data']['year'] ?? 0));

        $inner = $this->renderTemplate('home.php', ['stories' => $stories]);
        $html = $this->renderTemplate('layout.php', [
            'title' => 'Ollis Weihnachtsgeschichten',
            'description' => null,
            'content' => $inner,
        ]);

        $this->write('/', $html);
        return '/';
    }

    private function renderDetail(array $entry, string $kind, array $collections): string
    {
        $data = $entry['data'];

        $author = isset($data['author']) ? ($collections['authors'][$data['author']] ?? null) : null;
        $bodyHtml = (string) $this->markdown->convert($entry['body']);
        $productGridHtml = !empty($data['productsByAudience'])
            ? $this->renderTemplate('product-grid-by-audience.php', ['collections' => $collections])
            : $this->renderTemplate('product-grid.php', [
                'refs' => $data['products'] ?? [],
                'collections' => $collections,
            ]);

        $inner = $this->renderTemplate('detail.php', [
            'entry' => $entry,
            'kind' => $kind,
            'author' => $author,
            'bodyHtml' => $bodyHtml,
            'productGridHtml' => $productGridHtml,
        ]);

        $html = $this->renderTemplate('layout.php', [
            'title' => $data['title'] ?? '',
            'description' => $data['seo']['metaDescription'] ?? null,
            'content' => $inner,
        ]);

        $route = '/' . $entry['id'] . '/';
        $this->write($route, $html);
        return $route;
    }

    private function renderTemplate(string $name, array $vars): string
    {
        extract($vars, EXTR_SKIP);
        ob_start();
        require $this->templatesDir . '/' . $name;
        return (string) ob_get_clean();
    }

    private function write(string $route, string $html): void
    {
        $trimmed = trim($route, '/');
        $dir = $trimmed === '' ? $this->outDir : $this->outDir . '/' . $trimmed;
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        file_put_contents($dir . '/index.html', $html);
    }
}
