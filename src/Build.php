<?php

declare(strict_types=1);

namespace App;

/**
 * The actual generator run, extracted from build.php so both the CLI script
 * and the admin's save-and-regenerate flow share one code path.
 */
final class Build
{
    /**
     * @param array{contentDir:string,assetsDir:string,outDir:string,templatesDir:string,siteUrl:string,legacyUrlsFile:string} $paths
     * @return array{collections: array<string,int>, routes: string[], legacyUrlsChecked: int}
     */
    public static function run(array $paths, bool $echoProgress = false): array
    {
        $log = static function (string $line) use ($echoProgress): void {
            if ($echoProgress) {
                echo $line . "\n";
            }
        };

        $log('Cleaning output directory...');
        self::cleanDir($paths['outDir']);

        $log('Loading content...');
        $collections = (new ContentLoader())->loadAll($paths['contentDir']);
        $counts = [];
        foreach ($collections as $name => $entries) {
            $counts[$name] = count($entries);
            $log("  {$name}: " . count($entries));
        }

        $log('Validating content...');
        (new Validator())->validate($collections);

        $log('Rendering pages...');
        $routes = (new Renderer($paths['templatesDir'], $paths['outDir']))->renderAll($collections);
        $log('  ' . count($routes) . ' pages written');

        $log('Writing sitemap...');
        (new Sitemap())->write($routes, $paths['siteUrl'], $paths['outDir'] . '/sitemap.xml');

        $log('Copying static assets...');
        copy_dir($paths['assetsDir'] . '/uploads', $paths['outDir'] . '/uploads');
        copy_dir($paths['assetsDir'] . '/products', $paths['outDir'] . '/products');
        copy_dir($paths['assetsDir'] . '/css', $paths['outDir'] . '/css');

        // Redirects any lingering /wp-content/uploads/... links (external
        // bookmarks, search results) to the renamed /uploads/ path.
        $htaccess = $paths['assetsDir'] . '/.htaccess';
        if (is_file($htaccess)) {
            copy($htaccess, $paths['outDir'] . '/.htaccess');
        }

        $log('Checking legacy URL parity...');
        $checked = (new UrlGuard())->check($routes, $paths['legacyUrlsFile']);
        $log("  {$checked} legacy URLs verified present");

        $log('Build complete: ' . $paths['outDir']);

        return ['collections' => $counts, 'routes' => $routes, 'legacyUrlsChecked' => $checked];
    }

    private static function cleanDir(string $dir): void
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
            return;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
    }
}
