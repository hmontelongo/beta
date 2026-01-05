<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ScraperService
{
    protected string $baseUrl;

    protected int $timeout;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.scraper.url'), '/');
        $this->timeout = config('services.scraper.timeout', 30);
    }

    /**
     * Discover listings from a search page.
     *
     * @return array{total_results: int, total_pages: int, listings: array<array{url: string, external_id: string|null}>}
     *
     * @throws \RuntimeException
     */
    public function discoverPage(string $url, int $page = 1): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->retry(3, 1000)
                ->get("{$this->baseUrl}/discover", [
                    'url' => $url,
                    'page' => $page,
                ]);

            $response->throw();

            $data = $response->json();

            return [
                'total_results' => $data['total_results'] ?? 0,
                'total_pages' => $data['total_pages'] ?? 1,
                'listings' => $data['listings'] ?? [],
            ];
        } catch (ConnectionException $e) {
            Log::error('Scraper connection failed', [
                'url' => $url,
                'page' => $page,
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException("Failed to connect to scraper service: {$e->getMessage()}", 0, $e);
        } catch (RequestException $e) {
            Log::error('Scraper request failed', [
                'url' => $url,
                'page' => $page,
                'status' => $e->response?->status(),
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException("Scraper request failed: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Scrape a single listing page.
     *
     * @return array<string, mixed>
     *
     * @throws \RuntimeException
     */
    public function scrapeListing(string $url): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->retry(3, 1000)
                ->get("{$this->baseUrl}/scrape", [
                    'url' => $url,
                ]);

            $response->throw();

            return $response->json();
        } catch (ConnectionException $e) {
            Log::error('Scraper connection failed', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException("Failed to connect to scraper service: {$e->getMessage()}", 0, $e);
        } catch (RequestException $e) {
            Log::error('Scraper request failed', [
                'url' => $url,
                'status' => $e->response?->status(),
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException("Scraper request failed: {$e->getMessage()}", 0, $e);
        }
    }
}
