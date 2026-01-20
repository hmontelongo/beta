<?php

namespace App\Services;

use App\Enums\ApiOperation;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ZenRowsClient
{
    protected string $apiKey;

    protected string $baseUrl = 'https://api.zenrows.com/v1/';

    protected int $timeout;

    public function __construct(protected ApiUsageTracker $usageTracker)
    {
        $this->apiKey = config('services.zenrows.api_key') ?? '';
        $this->timeout = config('services.zenrows.timeout', 90);

        if (empty($this->apiKey)) {
            throw new \RuntimeException('ZENROWS_API_KEY is not configured. Please add it to your .env file.');
        }
    }

    /**
     * Fetch a search page with CSS extraction.
     * Uses networkidle event to wait for all JS content to load.
     *
     * @param  array<string, string>  $cssExtractor
     * @return array<string, mixed>
     *
     * @throws \RuntimeException
     */
    public function fetchSearchPage(string $url, array $cssExtractor): array
    {
        return $this->fetch($url, [
            'js_instructions' => json_encode([['wait_event' => 'networkidle']]),
            'block_resources' => 'image,font,media',
            'css_extractor' => json_encode($cssExtractor),
        ], 'json', ApiOperation::SearchScrape);
    }

    /**
     * Fetch a listing page with CSS extraction.
     * Uses networkidle event to wait for all JS content to load.
     *
     * @param  array<string, string>  $cssExtractor
     * @return array<string, mixed>
     *
     * @throws \RuntimeException
     */
    public function fetchListingPage(string $url, array $cssExtractor): array
    {
        return $this->fetch($url, [
            'js_instructions' => json_encode([['wait_event' => 'networkidle']]),
            'block_resources' => 'image,font,media',
            'css_extractor' => json_encode($cssExtractor),
        ], 'json', ApiOperation::ListingScrape);
    }

    /**
     * Fetch raw HTML for JavaScript variable extraction.
     * Uses networkidle event to wait for all JS content to load.
     *
     * @throws \RuntimeException
     */
    public function fetchRawHtml(string $url): string
    {
        return $this->fetch($url, [
            'js_instructions' => json_encode([['wait_event' => 'networkidle']]),
            'block_resources' => 'image,font,media',
        ], 'body', ApiOperation::RawHtmlFetch);
    }

    /**
     * Core fetch method with common parameters.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>|string
     *
     * @throws \RuntimeException
     */
    protected function fetch(string $url, array $options, string $responseType, ApiOperation $operation): array|string
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

            $cost = (int) ($response->header('X-Request-Cost') ?? 1);

            Log::debug('ZenRows response', [
                'url' => $url,
                'status' => $response->status(),
                'cost' => $cost,
            ]);

            // Track usage
            $this->usageTracker->logZenRowsUsage($operation, $cost, $url);

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
