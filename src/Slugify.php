<?php

declare(strict_types=1);

namespace App;

final class Slugify
{
    private const TRANSLIT = [
        'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss',
        'Ä' => 'ae', 'Ö' => 'oe', 'Ü' => 'ue',
        'é' => 'e', 'è' => 'e', 'á' => 'a', 'à' => 'a',
    ];

    public static function slugify(string $title): string
    {
        $slug = strtr($title, self::TRANSLIT);
        $slug = mb_strtolower($slug, 'UTF-8');
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
        $slug = trim($slug, '-');
        return $slug !== '' ? $slug : 'eintrag';
    }

    /** Appends -2, -3, ... until the id doesn't collide with an existing entry. */
    public static function uniqueId(string $title, array $existingIds): string
    {
        // Purely-numeric filenames (e.g. a story titled just "2027") become
        // int array keys when PHP loads them — cast back to string so the
        // collision check below isn't fooled by a type mismatch.
        $existingIds = array_map('strval', $existingIds);

        $base = self::slugify($title);
        $id = $base;
        $n = 2;
        while (in_array($id, $existingIds, true)) {
            $id = $base . '-' . $n;
            $n++;
        }
        return $id;
    }
}
