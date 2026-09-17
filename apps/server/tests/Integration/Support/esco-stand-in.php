<?php

declare(strict_types=1);

/**
 * A stand-in for the source's API, run by PHP's built-in server during the snapshot test.
 *
 * It serves the fixture records with the two behaviours of the real API that the client is built around:
 * `offset` on the search endpoint counts pages of `limit` results, and a request that does not name the
 * release is answered for an older one, here with an empty listing. Resource requests take several `uris`
 * and answer with the records keyed by identifier, each padded with an `_embedded` copy of something, as
 * the real answers are, so the snapshot builder's stripping of it is exercised.
 *
 * Every request is appended to the log file named by the STAND_IN_LOG environment variable, one line per
 * request holding the URL, a tab, and the user agent, so a test can assert what the client asked and as
 * whom. The file named by STAND_IN_MODE, when it exists, holds one of the misbehaviours a test may switch
 * on:
 *
 *   duplicate-page      every listing page is the first page, so a concept is listed twice
 *   total-drifts        the announced total grows by one on every page after the first
 *   drop-a-resource     the first identifier asked of a resource endpoint is left out of the answer
 *   result-without-uri  the first result of every listing page has no identifier
 *   pages-of-twenty     listings are paged by twenty whatever `limit` asks, and say so
 *   garbage-body        every answer is a truncated document rather than JSON
 *   server-error        every answer is a 500
 *   redirect            every answer is a 302 to the same URL with `moved=1` added, which is then served
 *   fail-first-batch    the first resource request carrying the first occupation is a 500, once
 *   garbage-first-batch the same request is a truncated document, once
 */

$release = 'v1.2.1';
$fixture = __DIR__ . '/../../Fixtures/taxonomy/snapshot';

$files = [
    'occupation' => 'occupations.ndjson',
    'skill' => 'skills.ndjson',
    'isco' => 'isco_groups.ndjson',
    'skills-hierarchy' => 'skill_groups.ndjson',
];

/**
 * @return list<array<string, mixed>>
 */
$records = static function (string $file) use ($fixture): array {
    $list = [];

    foreach (file($fixture . '/' . $file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $record = json_decode($line, true);

        if (is_array($record) && is_string($record['uri'] ?? null)) {
            /** @var array<string, mixed> $record */
            $list[] = $record;
        }
    }

    return $list;
};

$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$requestUri = is_string($requestUri) ? $requestUri : '/';
$path = (string) parse_url($requestUri, PHP_URL_PATH);

// Parsed by hand: the source takes `uris` repeated, which PHP's own parser would collapse to the last one.
$query = [];
$uris = [];

foreach (explode('&', (string) parse_url($requestUri, PHP_URL_QUERY)) as $pair) {
    if ($pair === '') {
        continue;
    }

    $parts = explode('=', $pair, 2);
    $key = urldecode($parts[0]);
    $value = urldecode($parts[1] ?? '');

    if ($key === 'uris') {
        $uris[] = $value;
    } else {
        $query[$key] = $value;
    }
}

$log = getenv('STAND_IN_LOG');

if (is_string($log) && $log !== '') {
    $agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    file_put_contents($log, $requestUri . "\t" . (is_string($agent) ? $agent : '') . "\n", FILE_APPEND);
}

$modeFile = getenv('STAND_IN_MODE');
$mode = is_string($modeFile) && is_file($modeFile) ? trim((string) file_get_contents($modeFile)) : '';

if ($mode === 'server-error') {
    http_response_code(500);
    echo 'no';

    return;
}

if ($mode === 'redirect' && !isset($query['moved'])) {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    http_response_code(302);
    header('Location: http://' . (is_string($host) ? $host : '') . $requestUri . '&moved=1');

    return;
}

if (in_array($mode, ['fail-first-batch', 'garbage-first-batch'], true) && is_string($modeFile) && str_starts_with($path, '/resource/') && !is_file($modeFile . '.fired')) {
    $first = $records('occupations.ndjson')[0]['uri'] ?? null;

    if (is_string($first) && in_array($first, $uris, true)) {
        file_put_contents($modeFile . '.fired', '1');

        if ($mode === 'fail-first-batch') {
            http_response_code(500);
            echo 'no';
        } else {
            header('Content-Type: application/json');
            echo '{"count": 3, "_embedded": {';
        }

        return;
    }
}

if ($mode === 'garbage-body') {
    header('Content-Type: application/json');
    echo '{"total": 3, "_embedded": {"results": [';

    return;
}

header('Content-Type: application/json');

$json = static function (array $payload): void {
    echo json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
};

if ($path === '/search') {
    $type = $query['type'] ?? '';
    $scheme = isset($query['isInScheme']) ? basename($query['isInScheme']) : '';
    $limit = $mode === 'pages-of-twenty' ? 20 : max(1, (int) ($query['limit'] ?? 20));
    $page = max(0, (int) ($query['offset'] ?? 0));
    $servedPage = $mode === 'duplicate-page' ? 0 : $page;
    $drift = $mode === 'total-drifts' ? $page : 0;

    if (($query['selectedVersion'] ?? null) !== $release) {
        $json(['total' => 0, 'offset' => $page, 'limit' => $limit, '_embedded' => ['results' => []]]);

        return;
    }

    $file = $type === 'concept' ? ($files[$scheme] ?? null) : ($files[$type] ?? null);

    if ($file === null) {
        http_response_code(400);
        $json(['message' => 'unknown type or scheme']);

        return;
    }

    $all = $records($file);
    $slice = array_slice($all, $servedPage * $limit, $limit);
    $results = array_map(static fn(array $r): array => ['uri' => $r['uri'] ?? '', 'title' => $r['title'] ?? ''], $slice);

    if ($mode === 'result-without-uri' && isset($results[0])) {
        unset($results[0]['uri']);
    }

    $json([
        'total' => count($all) + $drift,
        'offset' => $page,
        'limit' => $limit,
        '_embedded' => ['results' => array_values($results)],
    ]);

    return;
}

if (str_starts_with($path, '/resource/')) {
    if (($query['selectedVersion'] ?? null) !== $release) {
        $json(['count' => 0, '_embedded' => []]);

        return;
    }

    $byUri = [];

    foreach ($files as $file) {
        foreach ($records($file) as $record) {
            $uri = $record['uri'] ?? null;

            if (is_string($uri)) {
                $byUri[$uri] = $record;
            }
        }
    }

    $embedded = [];

    if ($mode === 'drop-a-resource') {
        array_shift($uris);
    }

    foreach ($uris as $wanted) {
        if (isset($byUri[$wanted])) {
            $record = $byUri[$wanted];
            $record['_embedded'] = ['padding' => ['uri' => $wanted, 'note' => 'a copy the snapshot must not keep']];
            $embedded[$wanted] = $record;
        }
    }

    $json(['count' => count($embedded), 'endpoint' => substr($path, strlen('/resource/')), '_embedded' => $embedded]);

    return;
}

http_response_code(404);
$json(['message' => 'not found']);
