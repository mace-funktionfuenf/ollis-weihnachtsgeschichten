<?php

declare(strict_types=1);

namespace App;

/**
 * SEO regression guard: fails the build if any URL in data/legacy-urls.txt
 * is missing from the generated output. Soft-skips if the file doesn't
 * exist yet, hard-fails if it does and something's missing.
 */
final class UrlGuard
{
    /** @param string[] $routes */
    public function check(array $routes, string $legacyUrlsFile): int
    {
        if (!is_file($legacyUrlsFile)) {
            fwrite(STDERR, "legacy-urls.txt not present — skipping URL-integrity check.\n");
            return 0;
        }

        $built = array_fill_keys($routes, true);
        $legacy = array_values(array_filter(array_map('trim', (array) file($legacyUrlsFile))));
        $missing = array_values(array_filter($legacy, static fn(string $u) => !isset($built[$u])));

        if ($missing) {
            throw new \RuntimeException(
                'SEO-Regression: ' . count($missing) . " Bestands-URL(s) fehlen im Build:\n" . implode("\n", $missing)
            );
        }

        return count($legacy);
    }
}
