<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ZenRowsClient
{
    protected string $apiKey;

    protected string $baseUrl = 'https://api.zenrows.com/v1/';

    protected int $timeout;

    public function __construct()
    {
        $this->apiKey = config('services.zenrows.api_key') ?? '';
        $this->timeout = config('services.zenrows.timeout', 90);

        if (empty($this->apiKey)) {
            throw new \RuntimeException('ZENROWS_API_KEY is not configured. Please add it to your .env file.');
        }
    }

    /**
     * Fetch a search page with CSS extraction.
     *
     * @param  array<string, string>  $cssExtractor
     * @return array<string, mixed>
     *
     * @throws \RuntimeException
     */
    public function fetchSearchPage(string $url, array $cssExtractor): array
    {
        return $this->fetch($url, [
            'wait_for' => '[data-qa^="posting"]',
            'wait' => 5000,
            'block_resources' => 'image,font,media',
            'css_extractor' => json_encode($cssExtractor),
        ], 'json');
    }

    /**
     * Fetch a listing page with CSS extraction.
     *
     * @param  array<string, string>  $cssExtractor
     * @return array<string, mixed>
     *
     * @throws \RuntimeException
     */
    public function fetchListingPage(string $url, array $cssExtractor): array
    {
        return $this->fetch($url, [
            'wait_for' => '#longDescription',
            'wait' => 2000,
            'block_resources' => 'font,media',
            'css_extractor' => json_encode($cssExtractor),
        ], 'json');
    }

    /**
     * Fetch raw HTML for JavaScript variable extraction.
     *
     * @throws \RuntimeException
     */
    public function fetchRawHtml(string $url): string
    {
        return $this->fetch($url, [
            'wait_for' => 'h1',
            'wait' => 2000,
            'block_resources' => 'stylesheet,font,media',
        ], 'body');
    }

    /**
     * Core fetch method with common parameters.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>|string
     *
     * @throws \RuntimeException
     */
    protected function fetch(string $url, array $options, string $responseType): array|string
    {
        $params = array_merge([
            'apikey' => $this->apiKey,
            'url' => $url,
            'js_render' => 'true',
            'premium_proxy' => 'true',
        ], $options);

        Log::debug('ZenRows request', ['url' => $url, 'has_css_extractor' => isset($options['css_extractor'])]);

        try {
            $response = Http::timeout($this->timeout)
                ->retry(2, 5000)
                ->get($this->baseUrl, $params);

            $response->throw();

            Log::debug('ZenRows response', [
                'url' => $url,
                'status' => $response->status(),
                'cost' => $response->header('X-Request-Cost'),
            ]);

            return $responseType === 'json' ? $response->json() : $response->body();
        } catch (ConnectionException $e) {
            Log::error('ZenRows connection failed', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException("Failed to connect to ZenRows: {$e->getMessage()}", 0, $e);
        } catch (RequestException $e) {
            Log::error('ZenRows request failed', [
                'url' => $url,
                'status' => $e->response?->status(),
                'error' => $e->getMessage(),
                'body' => $e->response?->body(),
            ]);

            throw new \RuntimeException("ZenRows request failed: {$e->getMessage()}", 0, $e);
        }
    }
}
