<?php

declare(strict_types=1);

namespace BothDecks\Taxonomy\Internal\Esco;

use BothDecks\Taxonomy\Internal\Configuration\TaxonomyConfig;

/**
 * The source's HTTP API, reduced to the two calls a snapshot needs.
 *
 * Every request names the release. The API answers for an old release when none is named, and nothing in
 * the answer says so; a snapshot taken that way would be of the wrong data with the right label on it. The
 * release is therefore not a default here but a value written on every URL, and the manifest records it.
 *
 * Resources are fetched in batches, several batches at a time, with each batch retried on its own. The
 * API's cost is linear in the number of resources whatever the batching, so the batch size and concurrency
 * bound how hard the source is hit, not how fast the snapshot completes.
 */
final readonly class EscoApi
{
    /** @var \Closure(int): void */
    private \Closure $pause;

    /**
     * The pause between attempts is the process sleeping unless a caller wants the waits observed instead.
     *
     * @param non-empty-string $userAgent Names the product to the source, as its terms of use ask.
     * @param (\Closure(int): void)|null $pause Waits the given number of seconds.
     */
    public function __construct(
        private TaxonomyConfig $config,
        private string $userAgent,
        ?\Closure $pause = null,
    ) {
        $this->pause = $pause ?? static function (int $seconds): void {
            sleep($seconds);
        };
    }

    /**
     * The identifier of every concept of a type, in the order the search endpoint lists them.
     *
     * @param string $type `occupation`, `skill`, or `concept` for the members of a scheme.
     * @param string|null $scheme The concept scheme to restrict the listing to; the group hierarchies need one.
     *
     * @return list<string>
     */
    public function listAll(string $type, ?string $scheme = null): array
    {
        $found = [];
        $seen = [];
        $pageNumber = 0;
        $total = null;

        do {
            // The endpoint's `offset` counts pages of `limit` results, not records: page 1 is the second
            // hundred. Read as a record offset it lists the first page and then nothing, which the count
            // check below refuses.
            $query = [
                'language' => 'en',
                'type' => $type,
                'limit' => $this->config->fetchPageSize,
                'offset' => $pageNumber,
                'selectedVersion' => $this->config->sourceVersion,
            ];

            if ($scheme !== null) {
                $query['isInScheme'] = $scheme;
            }

            $page = $this->getJson($this->config->sourceApi . '/search?' . http_build_query($query));

            $reported = $page['total'] ?? null;

            if (!\is_int($reported)) {
                throw new EscoApiException('The search endpoint answered without a total for type ' . $type);
            }

            // The page number is multiplied by the page size asked for; a source paging by another size
            // would be walked with the wrong stride and the listing would be short or doubled.
            $limit = $page['limit'] ?? null;

            if ($limit !== $this->config->fetchPageSize) {
                throw new EscoApiException(\sprintf(
                    'The search endpoint paged type %s by %s where %d was asked.',
                    $type,
                    \is_scalar($limit) ? (string) $limit : 'nothing',
                    $this->config->fetchPageSize,
                ));
            }

            if ($total !== null && $reported !== $total) {
                throw new EscoApiException(\sprintf(
                    'The total for type %s changed from %d to %d while paging; the source changed under the snapshot.',
                    $type,
                    $total,
                    $reported,
                ));
            }

            $total = $reported;
            $embedded = $page['_embedded'] ?? null;
            $results = \is_array($embedded) ? ($embedded['results'] ?? null) : null;

            if (!\is_array($results)) {
                throw new EscoApiException('The search endpoint answered without results for type ' . $type);
            }

            foreach ($results as $result) {
                if (!\is_array($result) || !\is_string($result['uri'] ?? null)) {
                    throw new EscoApiException('A search result carries no URI for type ' . $type);
                }

                // A page served twice would make the count come out right with a hole elsewhere.
                if (isset($seen[$result['uri']])) {
                    throw new EscoApiException('The search endpoint listed ' . $result['uri'] . ' twice for type ' . $type);
                }

                $seen[$result['uri']] = true;
                $found[] = $result['uri'];
            }

            $pageNumber++;
        } while ($pageNumber * $this->config->fetchPageSize < $total);

        if (\count($found) !== $total) {
            throw new EscoApiException(\sprintf('The search endpoint announced %d %s concepts and listed %d.', $total, $type, \count($found)));
        }

        return $found;
    }

    /**
     * Full resources by identifier, keyed by identifier, in no particular order.
     *
     * @param string $endpoint `occupation`, `skill` or `concept`: the resource endpoint the identifiers belong to.
     * @param list<string> $uris An identifier given more than once is fetched once.
     * @param callable(int $fetched, int $total): void|null $progress
     *
     * @return array<string, array<string, mixed>>
     */
    public function resources(string $endpoint, array $uris, ?callable $progress = null): array
    {
        $records = [];
        $uris = array_values(array_unique($uris));
        $batches = array_chunk($uris, $this->config->fetchBatchSize);
        $total = \count($uris);

        foreach (array_chunk($batches, $this->config->fetchConcurrency) as $wave) {
            $urls = [];

            foreach ($wave as $batch) {
                $urls[] = $this->config->sourceApi . '/resource/' . $endpoint . '?'
                    . implode('&', array_map(static fn(string $uri): string => 'uris=' . rawurlencode($uri), $batch))
                    . '&selectedVersion=' . rawurlencode($this->config->sourceVersion);
            }

            $answers = $this->getJsonConcurrently($urls);

            foreach ($wave as $index => $batch) {
                $url = $urls[$index] ?? throw new \LogicException('One URL is built per batch.');
                $answer = $answers[$index] ?? throw new EscoApiException('A batch went unanswered: ' . $url);
                $embedded = $answer['_embedded'] ?? null;

                if (!\is_array($embedded)) {
                    throw new EscoApiException('A batch answer carries no resources: ' . $url);
                }

                foreach ($batch as $uri) {
                    $record = $embedded[$uri] ?? null;

                    if (!\is_array($record)) {
                        throw new EscoApiException('The source listed ' . $uri . ' and then did not return it.');
                    }

                    /** @var array<string, mixed> $record */
                    $records[$uri] = $record;
                }
            }

            if ($progress !== null) {
                $progress(\count($records), $total);
            }
        }

        return $records;
    }

    /**
     * @return array<string, mixed>
     */
    private function getJson(string $url): array
    {
        $answers = $this->getJsonConcurrently([$url]);

        return $answers[0] ?? throw new EscoApiException('No answer for ' . $url);
    }

    /**
     * Fetch several URLs at once, each retried on its own until it answers or the attempts run out.
     *
     * @param list<string> $urls
     *
     * @return array<int, array<string, mixed>> Keyed like the input.
     */
    private function getJsonConcurrently(array $urls): array
    {
        $answers = [];
        $pending = $urls;
        $attempt = 0;

        while ($pending !== [] && $attempt < $this->config->fetchAttempts) {
            $attempt++;

            if ($attempt > 1) {
                // Exponential backoff, in whole seconds, so a source under load is not hit harder for it.
                ($this->pause)(2 ** ($attempt - 1));
            }

            $failures = [];

            foreach ($this->fetchAll($pending) as $index => $outcome) {
                if ($outcome['body'] === null) {
                    $failures[$index] = $outcome['error'];

                    continue;
                }

                $decoded = json_decode($outcome['body'], true);

                if (!\is_array($decoded)) {
                    // A truncated body or an error page is a failed attempt like a transport error, retried.
                    $failures[$index] = 'the body is not a JSON object';

                    continue;
                }

                /** @var array<string, mixed> $decoded */
                $answers[$index] = $decoded;
            }

            $pending = array_intersect_key($pending, $failures);

            if ($pending !== [] && $attempt === $this->config->fetchAttempts) {
                $lines = [];

                foreach ($pending as $index => $url) {
                    $lines[] = ($failures[$index] ?? 'no answer') . ' for ' . $url;
                }

                throw new EscoApiException(\sprintf(
                    "%d request(s) still failing after %d attempts:\n  %s",
                    \count($pending),
                    $attempt,
                    implode("\n  ", $lines),
                ));
            }
        }

        return $answers;
    }

    /**
     * One round of concurrent requests. Never throws: each URL reports a body or a reason.
     *
     * @param array<int, string> $urls
     *
     * @return array<int, array{body: string|null, error: string}>
     */
    private function fetchAll(array $urls): array
    {
        $multi = curl_multi_init();
        $handles = [];

        foreach ($urls as $index => $url) {
            $handle = curl_init($url);

            if ($handle === false) {
                throw new EscoApiException('Could not initialise a request for ' . $url);
            }

            curl_setopt_array($handle, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => ['Accept: application/json'],
                CURLOPT_USERAGENT => $this->userAgent,
                CURLOPT_TIMEOUT => $this->config->fetchTimeoutSeconds,
                CURLOPT_CONNECTTIMEOUT => 20,
                CURLOPT_ENCODING => '',
                CURLOPT_FOLLOWLOCATION => false,
                // The configuration allows plain HTTP to the machine itself and nowhere else.
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS | CURLPROTO_HTTP,
            ]);

            curl_multi_add_handle($multi, $handle);
            $handles[$index] = $handle;
        }

        do {
            $status = curl_multi_exec($multi, $running);

            if ($running > 0) {
                curl_multi_select($multi, 1.0);
            }
        } while ($running > 0 && $status === CURLM_OK);

        $outcomes = [];

        foreach ($handles as $index => $handle) {
            $body = curl_multi_getcontent($handle);
            $code = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
            $error = curl_error($handle);

            if ($error !== '') {
                $outcomes[$index] = ['body' => null, 'error' => 'transport: ' . $error];
            } elseif ($code !== 200 || !\is_string($body)) {
                $outcomes[$index] = ['body' => null, 'error' => 'HTTP ' . $code];
            } else {
                $outcomes[$index] = ['body' => $body, 'error' => ''];
            }

            curl_multi_remove_handle($multi, $handle);
        }

        curl_multi_close($multi);

        return $outcomes;
    }
}
