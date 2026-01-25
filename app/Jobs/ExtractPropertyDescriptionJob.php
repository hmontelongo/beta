<?php

namespace App\Jobs;

use App\Services\AI\PropertyDescriptionExtractionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ExtractPropertyDescriptionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 2;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 120;

    public function __construct(
        public string $extractionId,
        public string $description,
    ) {}

    public function handle(PropertyDescriptionExtractionService $service): void
    {
        $cacheKey = "property_extraction:{$this->extractionId}";

        // Update status to processing
        Cache::put($cacheKey, [
            'status' => 'processing',
            'stage' => 'Analizando descripcion con IA...',
            'progress' => 30,
            'data' => null,
            'error' => null,
        ], now()->addMinutes(30));

        try {
            $result = $service->extract($this->description);

            // Update with completed status
            Cache::put($cacheKey, [
                'status' => 'completed',
                'stage' => 'Datos extraidos',
                'progress' => 100,
                'data' => $result,
                'error' => null,
            ], now()->addMinutes(30));

            Log::info('Property extraction completed', [
                'extraction_id' => $this->extractionId,
                'quality_score' => $result['quality_score'] ?? 0,
            ]);
        } catch (\Throwable $e) {
            Cache::put($cacheKey, [
                'status' => 'failed',
                'stage' => 'Error al procesar',
                'progress' => 0,
                'data' => null,
                'error' => $e->getMessage(),
            ], now()->addMinutes(30));

            Log::error('Property extraction failed', [
                'extraction_id' => $this->extractionId,
                'error' => $e->getMessage(),
            ]);

            report($e);
        }
    }

    /**
     * Get the cache key for this extraction.
     */
    public static function getCacheKey(string $extractionId): string
    {
        return "property_extraction:{$extractionId}";
    }

    /**
     * Initialize the extraction status in cache.
     */
    public static function initializeStatus(string $extractionId): void
    {
        Cache::put(self::getCacheKey($extractionId), [
            'status' => 'queued',
            'stage' => 'En cola de procesamiento...',
            'progress' => 10,
            'data' => null,
            'error' => null,
        ], now()->addMinutes(30));
    }
}
