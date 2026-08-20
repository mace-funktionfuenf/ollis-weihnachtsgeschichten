<?php

declare(strict_types=1);

/**
 * Field definitions per collection, driving the generic list/edit views.
 * German labels throughout. Deliberately simpler than what's actually
 * possible in the content files (no nested `seo` object, no `legacyId`):
 * those fields, if already present on an entry, are preserved on save (see
 * the admin/index.php save handler), just not editable here.
 */

$FORMATS = [
    'vorlesen' => 'Vorlesen',
    'hoerbuch' => 'Hörbuch',
    'hoerspiel' => 'Hörspiel',
    'dvd' => 'DVD',
    'buch' => 'Buch',
    'plattdeutsch' => 'Plattdeutsch',
    'lokal' => 'Lokal',
];

$AUDIENCES = [
    'kinder' => 'Kinder',
    'familie' => 'Familie',
    'erwachsene' => 'Erwachsene',
];

$TOPICS = [
    'schnee' => 'Schnee',
    'wetter' => 'Wetter',
    'familie-staude' => 'Familie Staude',
    'humor' => 'Humor',
    'mundart' => 'Mundart',
    'rezepte' => 'Rezepte',
    'geschenke' => 'Geschenke',
    'film' => 'Film',
    'tradition' => 'Tradition',
    'recht' => 'Recht',
];

return [
    'stories' => [
        'label' => 'Weihnachtsgeschichten',
        'labelSingular' => 'Weihnachtsgeschichte',
        'listColumns' => ['year', 'title'],
        'fields' => [
            ['name' => 'title', 'label' => 'Titel', 'type' => 'string', 'required' => true],
            ['name' => 'kind', 'label' => 'Art', 'type' => 'select', 'required' => true, 'default' => 'jahresgeschichte', 'options' => [
                'jahresgeschichte' => 'Jahresgeschichte',
                'adventskalendergeschichte' => 'Adventskalendergeschichte',
            ]],
            ['name' => 'year', 'label' => 'Jahr', 'type' => 'number', 'required' => true],
            ['name' => 'pubDate', 'label' => 'Veröffentlicht am', 'type' => 'date', 'required' => true],
            ['name' => 'author', 'label' => 'Autor', 'type' => 'reference', 'collection' => 'authors', 'required' => true],
            ['name' => 'teaser', 'label' => 'Teaser', 'type' => 'textarea', 'required' => true],
            ['name' => 'heroImage', 'label' => 'Titelbild', 'type' => 'string'],
            ['name' => 'heroImageAlt', 'label' => 'Bildbeschreibung', 'type' => 'string'],
            ['name' => 'formats', 'label' => 'Formate', 'type' => 'multiselect', 'options' => $FORMATS],
            ['name' => 'audience', 'label' => 'Zielgruppe', 'type' => 'multiselect', 'options' => $AUDIENCES],
            ['name' => 'topics', 'label' => 'Themen', 'type' => 'multiselect', 'options' => $TOPICS],
            ['name' => 'products', 'label' => 'Produkte', 'type' => 'reference-multi', 'collection' => 'products'],
            ['name' => 'relatedManual', 'label' => 'Verwandte Geschichten', 'type' => 'reference-multi', 'collection' => 'stories'],
            ['name' => 'draft', 'label' => 'Entwurf', 'type' => 'boolean'],
            ['name' => 'body', 'label' => 'Text', 'type' => 'markdown', 'required' => true],
        ],
    ],

    'posts' => [
        'label' => 'Weihnachtsblog',
        'labelSingular' => 'Blogbeitrag',
        'listColumns' => ['title'],
        'fields' => [
            ['name' => 'title', 'label' => 'Titel', 'type' => 'string', 'required' => true],
            ['name' => 'pubDate', 'label' => 'Veröffentlicht am', 'type' => 'date', 'required' => true],
            ['name' => 'author', 'label' => 'Autor', 'type' => 'reference', 'collection' => 'authors', 'required' => true],
            ['name' => 'teaser', 'label' => 'Teaser', 'type' => 'textarea', 'required' => true],
            ['name' => 'heroImage', 'label' => 'Titelbild', 'type' => 'string'],
            ['name' => 'heroImageAlt', 'label' => 'Bildbeschreibung', 'type' => 'string'],
            ['name' => 'categories', 'label' => 'Kategorien', 'type' => 'taglist', 'required' => true],
            ['name' => 'formats', 'label' => 'Formate', 'type' => 'multiselect', 'options' => $FORMATS],
            ['name' => 'audience', 'label' => 'Zielgruppe', 'type' => 'multiselect', 'options' => $AUDIENCES],
            ['name' => 'topics', 'label' => 'Themen', 'type' => 'multiselect', 'options' => $TOPICS],
            ['name' => 'products', 'label' => 'Produkte', 'type' => 'reference-multi', 'collection' => 'products'],
            ['name' => 'relatedManual', 'label' => 'Verwandte Beiträge', 'type' => 'reference-multi', 'collection' => 'posts'],
            ['name' => 'draft', 'label' => 'Entwurf', 'type' => 'boolean'],
            ['name' => 'body', 'label' => 'Text', 'type' => 'markdown', 'required' => true],
        ],
    ],

    'products' => [
        'label' => 'Produkte (Affiliate)',
        'labelSingular' => 'Produkt',
        'listColumns' => ['title', 'network'],
        'fields' => [
            ['name' => 'title', 'label' => 'Titel', 'type' => 'string', 'required' => true],
            ['name' => 'brand', 'label' => 'Marke', 'type' => 'string'],
            ['name' => 'image', 'label' => 'Bild (Pfad unter /products/)', 'type' => 'string'],
            ['name' => 'imageAlt', 'label' => 'Bildbeschreibung', 'type' => 'string'],
            ['name' => 'blurb', 'label' => 'Kurzbeschreibung', 'type' => 'textarea'],
            ['name' => 'url', 'label' => 'Link', 'type' => 'string', 'required' => true],
            ['name' => 'network', 'label' => 'Netzwerk', 'type' => 'select', 'default' => 'amazon', 'options' => [
                'amazon' => 'Amazon', 'awin' => 'Awin', 'direkt' => 'Direkt',
            ]],
            ['name' => 'asin', 'label' => 'ASIN', 'type' => 'string'],
            ['name' => 'formats', 'label' => 'Formate', 'type' => 'multiselect', 'options' => $FORMATS],
            ['name' => 'audience', 'label' => 'Zielgruppe', 'type' => 'multiselect', 'options' => $AUDIENCES],
            ['name' => 'active', 'label' => 'Aktiv', 'type' => 'boolean', 'default' => true],
        ],
    ],

    'authors' => [
        'label' => 'Autoren',
        'labelSingular' => 'Autor',
        'listColumns' => ['displayName', 'name'],
        'fields' => [
            ['name' => 'name', 'label' => 'Voller Name', 'type' => 'string', 'required' => true],
            ['name' => 'displayName', 'label' => 'Anzeigename', 'type' => 'string', 'required' => true],
            ['name' => 'bio', 'label' => 'Biografie', 'type' => 'textarea'],
            ['name' => 'image', 'label' => 'Bild (Pfad unter /images/)', 'type' => 'string'],
        ],
    ],

    'pages' => [
        'label' => 'Seiten',
        'labelSingular' => 'Seite',
        'listColumns' => ['title'],
        'allowCreate' => false,
        'fields' => [
            ['name' => 'title', 'label' => 'Titel', 'type' => 'string', 'required' => true],
            ['name' => 'heroImage', 'label' => 'Titelbild', 'type' => 'string'],
            ['name' => 'heroImageAlt', 'label' => 'Bildbeschreibung', 'type' => 'string'],
            ['name' => 'products', 'label' => 'Produkte', 'type' => 'reference-multi', 'collection' => 'products'],
            ['name' => 'productsByAudience', 'label' => 'Alle Produkte nach Zielgruppe (Kinder/Erwachsene/Familie) anzeigen', 'type' => 'boolean'],
            ['name' => 'body', 'label' => 'Text', 'type' => 'markdown', 'required' => true],
        ],
    ],
];
