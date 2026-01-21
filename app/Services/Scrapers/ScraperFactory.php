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
        $identifier = $platform->slug ?? $platform->name;

        return match ($identifier) {
            'inmuebles24' => new Inmuebles24Config,
            'vivanuncios' => new VivanunciosConfig,
            'propiedades' => new PropiedadesConfig,
            default => throw new InvalidArgumentException("No scraper config for platform: {$identifier}"),
        };
    }

    /**
     * Create a search parser instance for the given platform.
     */
    public function createSearchParser(Platform $platform, ?ScraperConfigInterface $config = null): SearchParserInterface
    {
        $config ??= $this->createConfig($platform);
        $identifier = $platform->slug ?? $platform->name;

        return match ($identifier) {
            'inmuebles24' => new Inmuebles24SearchParser($config),
            'vivanuncios' => new VivanunciosSearchParser($config),
            'propiedades' => new PropiedadesSearchParser($config),
            default => throw new InvalidArgumentException("No search parser for platform: {$identifier}"),
        };
    }

    /**
     * Create a listing parser instance for the given platform.
     */
    public function createListingParser(Platform $platform, ?ScraperConfigInterface $config = null): ListingParserInterface
    {
        $config ??= $this->createConfig($platform);
        $identifier = $platform->slug ?? $platform->name;

        return match ($identifier) {
            'inmuebles24' => new Inmuebles24ListingParser($config),
            'vivanuncios' => new VivanunciosListingParser($config),
            'propiedades' => new PropiedadesListingParser($config),
            default => throw new InvalidArgumentException("No listing parser for platform: {$identifier}"),
        };
    }

    /**
     * Detect platform from a URL.
     */
    public function detectPlatformFromUrl(string $url): Platform
    {
        $host = parse_url($url, PHP_URL_HOST) ?? '';

        $slugOrName = match (true) {
            str_contains($host, 'inmuebles24.com') => 'inmuebles24',
            str_contains($host, 'vivanuncios.com') => 'vivanuncios',
            str_contains($host, 'propiedades.com') => 'propiedades',
            str_contains($host, 'mercadolibre.com') => 'mercadolibre',
            str_contains($host, 'easybroker.com') => 'easybroker',
            default => null,
        };

        if (! $slugOrName) {
            throw new InvalidArgumentException("Could not detect platform from URL: {$url}");
        }

        $platform = Platform::where('slug', $slugOrName)
            ->orWhere('name', $slugOrName)
            ->first();

        if (! $platform) {
            throw new InvalidArgumentException("Platform not found for: {$slugOrName}");
        }

        return $platform;
    }
}
