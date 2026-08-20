<?php

declare(strict_types=1);

namespace App;

use Spatie\YamlFrontMatter\YamlFrontMatter;

/**
 * Parses every Markdown + YAML-frontmatter file under
 * content/<collection>/*.md into memory, keyed by collection name and by
 * filename — the id is always the raw filename, never re-slugified, since
 * filenames are frozen legacy WordPress slugs that existing URLs depend on.
 */
final class ContentLoader
{
    private const COLLECTIONS = ['stories', 'posts', 'products', 'authors', 'pages'];

    /** @return array<string, array<string, array{id:string,collection:string,file:string,data:array,body:string}>> */
    public function loadAll(string $contentDir): array
    {
        $collections = [];
        foreach (self::COLLECTIONS as $name) {
            $collections[$name] = $this->loadCollection($contentDir, $name);
        }
        return $collections;
    }

    private function loadCollection(string $contentDir, string $name): array
    {
        $entries = [];
        $files = glob($contentDir . '/' . $name . '/*.md') ?: [];
        sort($files);
        foreach ($files as $file) {
            $id = basename($file, '.md');
            $parsed = YamlFrontMatter::parse((string) file_get_contents($file));
            $entries[$id] = [
                'id' => $id,
                'collection' => $name,
                'file' => $file,
                'data' => $parsed->matter(),
                'body' => $parsed->body(),
            ];
        }
        return $entries;
    }
}
