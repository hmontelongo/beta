<?php

namespace App\Services\Scrapers;

use App\Contracts\ListingParserInterface;
use App\Contracts\ScraperConfigInterface;
use App\Contracts\SearchParserInterface;
use App\Models\Platform;
use InvalidArgumentException;

class ScraperFactory
{
    /**
     * Create a config instance for the given platform.
     */
    public function createConfig(Platform $platform): ScraperConfigInterface
    {
        return match ($platform->slug) {
            'inmuebles24' => new Inmuebles24Config,
            'vivanuncios' => new VivanunciosConfig,
            default => throw new InvalidArgumentException("No scraper config for platform: {$platform->slug}"),
        };
    }

    /**
     * Create a search parser instance for the given platform.
     */
    public function createSearchParser(Platform $platform, ?ScraperConfigInterface $config = null): SearchParserInterface
    {
        $config ??= $this->createConfig($platform);

        return match ($platform->slug) {
            'inmuebles24' => new Inmuebles24SearchParser($config),
            'vivanuncios' => new VivanunciosSearchParser($config),
            default => throw new InvalidArgumentException("No search parser for platform: {$platform->slug}"),
        };
    }

    /**
     * Create a listing parser instance for the given platform.
     */
    public function createListingParser(Platform $platform, ?ScraperConfigInterface $config = null): ListingParserInterface
    {
        $config ??= $this->createConfig($platform);

        return match ($platform->slug) {
            'inmuebles24' => new Inmuebles24ListingParser($config),
            'vivanuncios' => new VivanunciosListingParser($config),
            default => throw new InvalidArgumentException("No listing parser for platform: {$platform->slug}"),
        };
    }

    /**
     * Detect platform from a URL.
     */
    public function detectPlatformFromUrl(string $url): Platform
    {
        $host = parse_url($url, PHP_URL_HOST) ?? '';

        $platform = match (true) {
            str_contains($host, 'inmuebles24.com') => Platform::where('slug', 'inmuebles24')->first(),
            str_contains($host, 'vivanuncios.com') => Platform::where('slug', 'vivanuncios')->first(),
            default => null,
        };

        if (! $platform) {
            throw new InvalidArgumentException("Could not detect platform from URL: {$url}");
        }

        return $platform;
    }
}
