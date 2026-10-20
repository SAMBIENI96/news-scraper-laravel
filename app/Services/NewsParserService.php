<?php

declare(strict_types=1);

namespace App\Services;

use App\DTO\News;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;

class NewsParserService
{
    private const BASE_URL = 'https://kaktus.media';

    private Client $httpClient;

    public function __construct()
    {
        $this->httpClient = new Client([
            'timeout'         => 20,
            'connect_timeout' => 10,
            'headers'         => [
                'User-Agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'ru-RU,ru;q=0.9,en;q=0.8',
            ],
            'verify' => false,
        ]);
    }

    /** @return News[] */
    public function getNews(string $date, ?string $search = null): array
    {
        try {
            $news = $this->fetchNewsByDate($date);
            if (!empty($search)) {
                $news = $this->filterBySearch($news, $search);
            }
            return $news;
        } catch (\Throwable $e) {
            Log::error('NewsParserService error', ['message' => $e->getMessage(), 'date' => $date]);
            return [];
        }
    }

    /** @return News[] */
    private function fetchNewsByDate(string $date): array
    {
        $url  = self::BASE_URL . '/';
        $html = $this->fetchHtml($url);
        if ($html === null) return [];
        return $this->parse($html, $date);
    }

    /** @return News[] */
    private function parse(string $html, string $date): array
    {
        $crawler = new Crawler($html);
        $news    = [];

        $crawler->filter('a[href*="/doc/"]')->each(function (Crawler $link) use (&$news, $date) {
            $href  = $link->attr('href') ?? '';
            $title = trim(preg_replace('/\s+/', ' ', $link->text()));
            if (empty($title) || mb_strlen($title) < 10) return;

            $url         = $this->buildAbsoluteUrl($href);
            $image       = null;
            $articleDate = null; // inconnu par défaut

            $node   = $link->getNode(0);
            $parent = $node?->parentNode;

            for ($i = 0; $i < 3; $i++) {
                if (!$parent) break;
                $parentCrawler = new Crawler($parent);
                $img = $parentCrawler->filter('img')->first();
                if ($img->count() > 0) {
                    $src = $img->attr('data-src') ?? $img->attr('src') ?? null;
                    if ($src && str_contains($src, 'kaktus')) {
                        $image = $this->buildAbsoluteUrl($src);
                        // Extraire la date depuis l'URL de l'image
                        if (preg_match('/(\d{4}-\d{2}-\d{2})_/', $src, $matches)) {
                            $articleDate = $matches[1];
                        }
                        break;
                    }
                }
                $parent = $parent->parentNode;
            }

            // Si on a trouvé une date et qu'elle ne correspond pas → ignorer
            if ($articleDate !== null && $articleDate !== $date) return;

            // Si pas de date trouvée → inclure seulement si c'est aujourd'hui
            if ($articleDate === null && $date !== now()->format('Y-m-d')) return;

            $news[] = new News(date: $date, title: $title, url: $url, image: $image);
        });

        return $this->deduplicate($news);
    }

    /** @param News[] $news @return News[] */
    private function filterBySearch(array $news, string $search): array
    {
        $query = mb_strtolower(trim($search));
        return array_values(array_filter($news, fn(News $item) => str_contains(mb_strtolower($item->title), $query)));
    }

    /** @param News[] $news @return News[] */
    private function deduplicate(array $news): array
    {
        $seen   = [];
        $result = [];
        foreach ($news as $item) {
            if (!isset($seen[$item->url])) {
                $seen[$item->url] = true;
                $result[]         = $item;
            }
        }
        return $result;
    }

    private function fetchHtml(string $url): ?string
    {
        try {
            $response = $this->httpClient->get($url);
            return (string) $response->getBody();
        } catch (GuzzleException $e) {
            Log::warning('Failed to fetch URL', ['url' => $url, 'error' => $e->getMessage()]);
            return null;
        }
    }

    private function buildAbsoluteUrl(string $href): string
    {
        $href = trim($href);
        if (str_starts_with($href, 'http://') || str_starts_with($href, 'https://')) return $href;
        if (str_starts_with($href, '//')) return 'https:' . $href;
        if (str_starts_with($href, '/')) return self::BASE_URL . $href;
        return self::BASE_URL . '/' . $href;
    }
}