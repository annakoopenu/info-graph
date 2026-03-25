<?php

declare(strict_types=1);

class ImageReplacementService
{
    private const USER_AGENT = 'InfoGraphImageReplacement/1.0';

    public function findReplacementUrl(array $item): ?string
    {
        $wikipediaTitle = $this->extractWikipediaTitle((string) ($item['link'] ?? ''));
        if ($wikipediaTitle !== null) {
            foreach ($this->preferredWikipediaTitles($item, $wikipediaTitle) as $candidateTitle) {
                $imageUrl = $this->fetchWikipediaThumbnailByTitle($candidateTitle);
                if ($imageUrl !== null) {
                    return $imageUrl;
                }
            }
        }

        foreach ($this->buildSearchQueries($item) as $query) {
            $imageUrl = $this->searchWikipediaThumbnail($item, $query);
            if ($imageUrl !== null) {
                return $imageUrl;
            }
        }

        return null;
    }

    private function extractWikipediaTitle(string $link): ?string
    {
        if ($link === '') {
            return null;
        }

        $parts = parse_url($link);
        $host = strtolower($parts['host'] ?? '');
        $path = $parts['path'] ?? '';

        if (!str_contains($host, 'wikipedia.org') || !str_starts_with($path, '/wiki/')) {
            return null;
        }

        $title = substr($path, strlen('/wiki/'));
        $title = rawurldecode($title);
        $title = str_replace('_', ' ', $title);

        return trim($title) !== '' ? trim($title) : null;
    }

    private function fetchWikipediaThumbnailByTitle(string $title): ?string
    {
        $response = $this->fetchJson('https://en.wikipedia.org/w/api.php?' . http_build_query([
            'action' => 'query',
            'format' => 'json',
            'formatversion' => '2',
            'redirects' => '1',
            'prop' => 'pageimages',
            'piprop' => 'thumbnail',
            'pithumbsize' => '800',
            'titles' => $title,
        ], '', '&', PHP_QUERY_RFC3986));

        if (!is_array($response['query']['pages'] ?? null)) {
            return null;
        }

        foreach ($response['query']['pages'] as $page) {
            $source = trim((string) ($page['thumbnail']['source'] ?? ''));
            if ($source !== '') {
                return $source;
            }
        }

        return null;
    }

    private function searchWikipediaThumbnail(array $item, string $query): ?string
    {
        $response = $this->fetchJson('https://en.wikipedia.org/w/api.php?' . http_build_query([
            'action' => 'query',
            'format' => 'json',
            'formatversion' => '2',
            'generator' => 'search',
            'gsrsearch' => $query,
            'gsrlimit' => '5',
            'gsrnamespace' => '0',
            'prop' => 'pageimages',
            'piprop' => 'thumbnail',
            'pithumbsize' => '800',
        ], '', '&', PHP_QUERY_RFC3986));

        if (!is_array($response['query']['pages'] ?? null)) {
            return null;
        }

        $pages = array_values(array_filter($response['query']['pages'], 'is_array'));
        usort($pages, fn(array $left, array $right): int => $this->rankWikipediaPage($right, $item) <=> $this->rankWikipediaPage($left, $item));

        foreach ($pages as $page) {
            $source = trim((string) ($page['thumbnail']['source'] ?? ''));
            if ($source !== '') {
                return $source;
            }
        }

        return null;
    }

    private function preferredWikipediaTitles(array $item, string $title): array
    {
        $itemName = trim((string) ($item['item_name'] ?? ''));
        $titles = [];

        if ($this->isFilm($item)) {
            if ($itemName !== '') {
                $titles[] = $itemName . ' (film)';
                $titles[] = $itemName . ' (movie)';
            }

            $titles[] = $title . ' (film)';
            $titles[] = $title . ' (movie)';
        }

        $titles[] = $title;

        return array_values(array_unique(array_filter(array_map('trim', $titles))));
    }

    private function buildSearchQueries(array $item): array
    {
        $itemName = trim((string) ($item['item_name'] ?? ''));
        $authorName = trim((string) ($item['author_name'] ?? ''));
        $category = trim((string) ($item['category'] ?? ''));

        if ($this->isFilm($item)) {
            $queries = [
                $itemName . ' film poster',
                $itemName . ' film',
                $itemName . ' movie poster',
                $itemName . ' movie',
                $itemName,
            ];

            return array_values(array_filter(array_unique(array_map('trim', $queries))));
        }

        $parts = [];
        foreach ([$itemName, $authorName, $category] as $value) {
            if ($value !== '') {
                $parts[] = $value;
            }
        }

        $query = trim(implode(' ', $parts));
        return $query !== '' ? [$query] : [];
    }

    private function isFilm(array $item): bool
    {
        $category = trim((string) ($item['category'] ?? ''));
        return strcasecmp($category, 'film') === 0;
    }

    private function rankWikipediaPage(array $page, array $item): int
    {
        $score = 0;
        $title = trim((string) ($page['title'] ?? ''));
        $normalizedTitle = $this->normalizeTitle($title);
        $normalizedItemName = $this->normalizeTitle(trim((string) ($item['item_name'] ?? '')));

        if ($normalizedTitle === $normalizedItemName) {
            $score += 40;
        }

        if ($normalizedItemName !== '' && str_starts_with($normalizedTitle, $normalizedItemName . ' (')) {
            $score += 80;
        }

        if ($this->isFilm($item)) {
            if (preg_match('/\(\d{4} film\)$/i', $title)) {
                $score += 120;
            } elseif (preg_match('/\((film|movie|tv series)\)$/i', $title)) {
                $score += 90;
            }

            if (stripos($title, 'soundtrack') !== false || stripos($title, 'novel') !== false) {
                $score -= 80;
            }
        }

        if (!empty($page['thumbnail']['source'])) {
            $score += 10;
        }

        return $score;
    }

    private function normalizeTitle(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;
        return function_exists('mb_strtolower') ? mb_strtolower($value) : strtolower($value);
    }

    private function fetchJson(string $url): ?array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 8,
                'ignore_errors' => true,
                'header' => implode("\r\n", [
                    'Accept: application/json',
                    'User-Agent: ' . self::USER_AGENT,
                ]),
            ],
        ]);

        $body = @file_get_contents($url, false, $context);
        if ($body === false || trim($body) === '') {
            return null;
        }

        $data = json_decode($body, true);
        return is_array($data) ? $data : null;
    }
}
