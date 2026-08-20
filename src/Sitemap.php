<?php

declare(strict_types=1);

namespace App;

/**
 * Replacement for @astrojs/sitemap. The site is ~115 URLs total, nowhere near
 * the 50k-per-file limit, so a single flat sitemap.xml (rather than the
 * sitemap-index + shards Astro's integration produces) is simpler and
 * equivalent.
 */
final class Sitemap
{
    /** @param string[] $routes */
    public function write(array $routes, string $siteUrl, string $outFile): void
    {
        $siteUrl = rtrim($siteUrl, '/');
        sort($routes);

        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>');
        foreach ($routes as $route) {
            $url = $xml->addChild('url');
            $url->addChild('loc', htmlspecialchars($siteUrl . $route, ENT_XML1, 'UTF-8'));
        }

        $xml->asXML($outFile);
    }
}
