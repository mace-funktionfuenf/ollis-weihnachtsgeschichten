<?php

declare(strict_types=1);

namespace App;

/**
 * Deliberately lightweight content validation — no string length ranges, no
 * coerced-date parsing, that's a lot of ceremony for a site this size. This
 * only checks what actually prevents a broken page or a legal issue:
 * required fields, enum values, reference integrity, and the
 * GDPR-motivated image path prefixes.
 */
final class Validator
{
    private const FORMATS = ['vorlesen', 'hoerbuch', 'hoerspiel', 'dvd', 'buch', 'plattdeutsch', 'lokal'];
    private const AUDIENCES = ['kinder', 'familie', 'erwachsene'];
    private const TOPICS = [
        'schnee', 'wetter', 'familie-staude', 'humor', 'mundart',
        'rezepte', 'geschenke', 'film', 'tradition', 'recht',
    ];

    /** @param array<string, array<string, array>> $collections */
    public function validate(array $collections): void
    {
        $errors = [];

        foreach ($collections['stories'] as $entry) {
            $errors = [...$errors, ...$this->checkRequired($entry, ['title', 'kind', 'year', 'pubDate', 'author', 'teaser'])];
            $errors = [...$errors, ...$this->checkEnum($entry, 'kind', ['jahresgeschichte', 'adventskalendergeschichte'])];
            $errors = [...$errors, ...$this->checkEnumArray($entry, 'formats', self::FORMATS)];
            $errors = [...$errors, ...$this->checkEnumArray($entry, 'audience', self::AUDIENCES)];
            $errors = [...$errors, ...$this->checkEnumArray($entry, 'topics', self::TOPICS)];
            $errors = [...$errors, ...$this->checkReference($entry, 'author', $collections, 'authors')];
            $errors = [...$errors, ...$this->checkReferenceArray($entry, 'products', $collections, 'products')];
            $errors = [...$errors, ...$this->checkReferenceArray($entry, 'relatedManual', $collections, 'stories')];
        }

        foreach ($collections['posts'] as $entry) {
            $errors = [...$errors, ...$this->checkRequired($entry, ['title', 'pubDate', 'author', 'teaser'])];
            $errors = [...$errors, ...$this->checkNonEmptyArray($entry, 'categories')];
            $errors = [...$errors, ...$this->checkEnumArray($entry, 'formats', self::FORMATS)];
            $errors = [...$errors, ...$this->checkEnumArray($entry, 'audience', self::AUDIENCES)];
            $errors = [...$errors, ...$this->checkEnumArray($entry, 'topics', self::TOPICS)];
            $errors = [...$errors, ...$this->checkReference($entry, 'author', $collections, 'authors')];
            $errors = [...$errors, ...$this->checkReferenceArray($entry, 'products', $collections, 'products')];
            $errors = [...$errors, ...$this->checkReferenceArray($entry, 'relatedManual', $collections, 'posts')];
        }

        foreach ($collections['products'] as $entry) {
            $errors = [...$errors, ...$this->checkRequired($entry, ['title', 'url'])];
            $errors = [...$errors, ...$this->checkEnum($entry, 'network', ['amazon', 'awin', 'direkt'])];
            $errors = [...$errors, ...$this->checkPrefix($entry, 'image', '/products/')];
        }

        foreach ($collections['authors'] as $entry) {
            $errors = [...$errors, ...$this->checkRequired($entry, ['name', 'displayName'])];
            $errors = [...$errors, ...$this->checkPrefix($entry, 'image', '/uploads/')];
        }

        foreach ($collections['pages'] as $entry) {
            $errors = [...$errors, ...$this->checkRequired($entry, ['title'])];
            $errors = [...$errors, ...$this->checkReferenceArray($entry, 'products', $collections, 'products')];
        }

        if ($errors) {
            throw new \RuntimeException("Content validation failed:\n" . implode("\n", $errors));
        }
    }

    private function label(array $entry): string
    {
        return "{$entry['collection']}/{$entry['id']}.md";
    }

    private function checkRequired(array $entry, array $fields): array
    {
        $errors = [];
        foreach ($fields as $field) {
            $value = $entry['data'][$field] ?? null;
            if ($value === null || $value === '') {
                $errors[] = $this->label($entry) . ": missing required field '{$field}'";
            }
        }
        return $errors;
    }

    private function checkNonEmptyArray(array $entry, string $field): array
    {
        $value = $entry['data'][$field] ?? [];
        if (!is_array($value) || count($value) === 0) {
            return [$this->label($entry) . ": field '{$field}' must have at least one entry"];
        }
        return [];
    }

    private function checkEnum(array $entry, string $field, array $allowed): array
    {
        $value = $entry['data'][$field] ?? null;
        if ($value === null) {
            return [];
        }
        if (!in_array($value, $allowed, true)) {
            return [$this->label($entry) . ": field '{$field}' has invalid value '{$value}'"];
        }
        return [];
    }

    private function checkEnumArray(array $entry, string $field, array $allowed): array
    {
        $values = $entry['data'][$field] ?? [];
        if (!is_array($values)) {
            return [$this->label($entry) . ": field '{$field}' should be a list"];
        }
        $errors = [];
        foreach ($values as $value) {
            if (!in_array($value, $allowed, true)) {
                $errors[] = $this->label($entry) . ": field '{$field}' has invalid value '{$value}'";
            }
        }
        return $errors;
    }

    private function checkPrefix(array $entry, string $field, string $prefix): array
    {
        $value = $entry['data'][$field] ?? null;
        if ($value !== null && !str_starts_with((string) $value, $prefix)) {
            return [$this->label($entry) . ": field '{$field}' must start with '{$prefix}' (got '{$value}')"];
        }
        return [];
    }

    private function checkReference(array $entry, string $field, array $collections, string $targetCollection): array
    {
        $value = $entry['data'][$field] ?? null;
        if ($value === null) {
            return [];
        }
        if (!isset($collections[$targetCollection][$value])) {
            return [$this->label($entry) . ": field '{$field}' references unknown {$targetCollection} '{$value}'"];
        }
        return [];
    }

    private function checkReferenceArray(array $entry, string $field, array $collections, string $targetCollection): array
    {
        $values = $entry['data'][$field] ?? [];
        if (!is_array($values)) {
            return [$this->label($entry) . ": field '{$field}' should be a list"];
        }
        $errors = [];
        foreach ($values as $value) {
            if (!isset($collections[$targetCollection][$value])) {
                $errors[] = $this->label($entry) . ": field '{$field}' references unknown {$targetCollection} '{$value}'";
            }
        }
        return $errors;
    }
}
