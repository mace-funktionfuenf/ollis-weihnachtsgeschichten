<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Auth;
use App\Build;
use App\ContentLoader;
use App\Database;
use App\Slugify;
use App\Validator;
use Symfony\Component\Yaml\Yaml;

$configFile = __DIR__ . '/../config.php';
if (!is_file($configFile)) {
    http_response_code(500);
    exit('Fehlende Konfiguration: bitte config.example.php nach config.php kopieren und anpassen.');
}
$config = require $configFile;

session_name($config['session_name']);
session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
    // Only marked Secure when actually served over HTTPS — a plain `Secure`
    // cookie is silently dropped by the browser over local http:// dev.
    'secure' => !empty($_SERVER['HTTPS']),
]);
session_start();

$db = Database::connect($config);
$auth = new Auth($db);
/** @var array<string, array> $schema */
$schema = require __DIR__ . '/schema.php';

$paths = $config['paths'];
$paths['siteUrl'] = $config['siteUrl'];

$action = $_GET['action'] ?? 'list';

function display_field(string $targetCollection): string
{
    return $targetCollection === 'authors' ? 'displayName' : 'title';
}

/** @return array<string, array<string,string>> targetCollection => [id => label] */
function reference_options(array $fields, array $collections): array
{
    $out = [];
    foreach ($fields as $field) {
        if (!in_array($field['type'], ['reference', 'reference-multi'], true)) {
            continue;
        }
        $target = $field['collection'];
        if (isset($out[$target])) {
            continue;
        }
        $displayField = display_field($target);
        $options = [];
        foreach ($collections[$target] ?? [] as $id => $entry) {
            $options[$id] = $entry['data'][$displayField] ?? $id;
        }
        $out[$target] = $options;
    }
    return $out;
}

function flash(string $message): void
{
    $_SESSION['flash'] = $message;
}

// ---- routes that don't require login ----

if ($action === 'login') {
    $error = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $auth->checkCsrf($_POST['csrf_token'] ?? null);
        if ($auth->attemptLogin((string) ($_POST['username'] ?? ''), (string) ($_POST['password'] ?? ''))) {
            header('Location: index.php?action=list&collection=stories');
            exit;
        }
        $error = 'Benutzername oder Passwort ist falsch, oder das Konto ist deaktiviert.';
    }
    require __DIR__ . '/views/login.php';
    exit;
}

if ($action === 'logout') {
    $auth->logout();
    header('Location: index.php?action=login');
    exit;
}

// ---- everything below requires login ----
$auth->requireLogin();

if ($action === 'list') {
    $collectionKey = $_GET['collection'] ?? '';
    if (!isset($schema[$collectionKey])) {
        http_response_code(404);
        exit('Unbekannte Sammlung.');
    }
    $collectionDef = $schema[$collectionKey];
    $entries = (new ContentLoader())->loadAll($paths['contentDir'])[$collectionKey];
    require __DIR__ . '/views/list.php';
    exit;
}

if ($action === 'edit') {
    $collectionKey = $_GET['collection'] ?? '';
    if (!isset($schema[$collectionKey])) {
        http_response_code(404);
        exit('Unbekannte Sammlung.');
    }
    $collectionDef = $schema[$collectionKey];
    $collections = (new ContentLoader())->loadAll($paths['contentDir']);
    $entryId = $_GET['id'] ?? null;

    if ($entryId === null && ($collectionDef['allowCreate'] ?? true) === false) {
        flash('Für diese Sammlung können keine neuen Einträge angelegt werden.');
        header('Location: index.php?action=list&collection=' . urlencode($collectionKey));
        exit;
    }

    if ($entryId !== null) {
        $entry = $collections[$collectionKey][$entryId] ?? null;
        if ($entry === null) {
            http_response_code(404);
            exit('Eintrag nicht gefunden.');
        }
        $data = $entry['data'];
        $data['body'] = $entry['body'];
    } else {
        $data = ['body' => ''];
    }

    $referenceOptions = reference_options($collectionDef['fields'], $collections);
    $errors = [];
    require __DIR__ . '/views/edit.php';
    exit;
}

if ($action === 'save') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        exit('Methode nicht erlaubt.');
    }
    $auth->checkCsrf($_POST['csrf_token'] ?? null);

    $collectionKey = $_POST['collection'] ?? '';
    if (!isset($schema[$collectionKey])) {
        http_response_code(404);
        exit('Unbekannte Sammlung.');
    }
    $collectionDef = $schema[$collectionKey];
    $collections = (new ContentLoader())->loadAll($paths['contentDir']);

    $postedId = $_POST['id'] ?? null;
    $isNew = $postedId === null || $postedId === '';
    $posted = $_POST['fields'] ?? [];

    // Build the new field values, respecting each field's type.
    $newData = [];
    $body = '';
    foreach ($collectionDef['fields'] as $field) {
        $name = $field['name'];
        $raw = $posted[$name] ?? null;

        $value = match ($field['type']) {
            'boolean' => $raw !== null,
            'multiselect', 'reference-multi' => is_array($raw) ? array_values($raw) : [],
            'number' => ($raw === null || $raw === '') ? null : (int) $raw,
            'taglist' => array_values(array_filter(array_map('trim', explode(',', (string) $raw)), fn($v) => $v !== '')),
            'markdown' => (string) $raw,
            default => ($raw === null || $raw === '') ? null : trim((string) $raw),
        };

        if ($field['type'] === 'markdown') {
            $body = $value;
        } else {
            $newData[$name] = $value;
        }
    }

    // Preserve any frontmatter fields this admin doesn't manage (legacyId, seo, ...).
    $existingRaw = (!$isNew && isset($collections[$collectionKey][$postedId]))
        ? $collections[$collectionKey][$postedId]['data']
        : [];
    $frontmatter = array_merge($existingRaw, $newData);
    // Drop nulls so optional/empty fields don't get written as `field: null`.
    $frontmatter = array_filter($frontmatter, fn($v) => $v !== null);

    if ($isNew) {
        if (($collectionDef['allowCreate'] ?? true) === false) {
            http_response_code(400);
            exit('Für diese Sammlung können keine neuen Einträge angelegt werden.');
        }
        $id = Slugify::uniqueId((string) ($newData['title'] ?? $newData['displayName'] ?? 'eintrag'), array_keys($collections[$collectionKey]));
    } else {
        $id = (string) $postedId;
        if (!preg_match('/^[a-z0-9-]+$/', $id) || !isset($collections[$collectionKey][$id])) {
            http_response_code(400);
            exit('Ungültiger Eintrag.');
        }
    }

    // Validate against a snapshot with this entry updated/inserted.
    $collections[$collectionKey][$id] = [
        'id' => $id,
        'collection' => $collectionKey,
        'file' => '',
        'data' => $frontmatter,
        'body' => $body,
    ];

    $errors = [];
    try {
        (new Validator())->validate($collections);
    } catch (\RuntimeException $e) {
        $lines = explode("\n", $e->getMessage());
        $errors = array_slice($lines, 1); // drop the "Content validation failed:" header line
    }

    if ($errors) {
        $collectionDef = $schema[$collectionKey];
        $data = $newData;
        $data['body'] = $body;
        $entryId = $isNew ? null : $id;
        $referenceOptions = reference_options($collectionDef['fields'], $collections);
        require __DIR__ . '/views/edit.php';
        exit;
    }

    $yaml = Yaml::dump($frontmatter, 4, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
    $fileContents = "---\n" . $yaml . "---\n\n" . $body . "\n";
    file_put_contents($paths['contentDir'] . '/' . $collectionKey . '/' . $id . '.md', $fileContents);

    Build::run($paths, false);

    flash('Gespeichert.');
    header('Location: index.php?action=list&collection=' . urlencode($collectionKey));
    exit;
}

if ($action === 'users') {
    $auth->requireAdmin();
    $users = $auth->listUsers();
    $errors = [];
    require __DIR__ . '/views/users.php';
    exit;
}

if ($action === 'save-user') {
    $auth->requireAdmin();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        exit('Methode nicht erlaubt.');
    }
    $auth->checkCsrf($_POST['csrf_token'] ?? null);

    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $role = ($_POST['role'] ?? 'editor') === 'admin' ? 'admin' : 'editor';

    $errors = [];
    if ($username === '') {
        $errors[] = 'Benutzername darf nicht leer sein.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Passwort muss mindestens 8 Zeichen lang sein.';
    }

    if (!$errors) {
        try {
            $auth->createUser($username, $password, $role);
            flash('Benutzer angelegt.');
            header('Location: index.php?action=users');
            exit;
        } catch (\PDOException $e) {
            $errors[] = 'Benutzername ist bereits vergeben.';
        }
    }

    $users = $auth->listUsers();
    require __DIR__ . '/views/users.php';
    exit;
}

if ($action === 'toggle-user') {
    $auth->requireAdmin();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        exit('Methode nicht erlaubt.');
    }
    $auth->checkCsrf($_POST['csrf_token'] ?? null);
    $targetId = (int) ($_POST['id'] ?? 0);
    if ($targetId === $auth->currentUserId()) {
        flash('Das eigene Konto kann nicht deaktiviert werden.');
        header('Location: index.php?action=users');
        exit;
    }
    $auth->setUserActive($targetId, ($_POST['active'] ?? '') === '1');
    header('Location: index.php?action=users');
    exit;
}

http_response_code(404);
exit('Unbekannte Aktion.');
