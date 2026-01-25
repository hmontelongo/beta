<?php

namespace App\Livewire\Agents\Properties\Upload;

use App\Enums\OperationType;
use App\Enums\PropertyType;
use App\Jobs\ExtractPropertyDescriptionJob;
use App\Services\AI\PropertyDescriptionExtractionService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.agent')]
#[Title('Revisar datos')]
class Review extends Component
{
    /** @var array<string, mixed> */
    public array $extractedData = [];

    public string $originalDescription = '';

    public string $extractionId = '';

    public string $extractionStatus = 'pending';

    public string $extractionStage = '';

    public int $extractionProgress = 0;

    public ?string $extractionError = null;

    public int $qualityScore = 0;

    public ?int $extractionStartedAt = null;

    /**
     * Maximum time to wait for extraction before timing out (5 minutes).
     */
    protected const EXTRACTION_TIMEOUT_SECONDS = 300;

    public function mount(): void
    {
        $this->originalDescription = session('property_upload.description', '');

        if (! $this->originalDescription) {
            $this->redirectRoute('agents.properties.upload.describe', navigate: true);

            return;
        }

        // Check if we have previously saved data in session (back navigation)
        $savedData = session('property_upload.extracted_data');

        if ($savedData) {
            $this->extractedData = $savedData;
            $this->qualityScore = session('property_upload.quality_score', 0);
            $this->extractionStatus = 'completed';

            return;
        }

        // Check for in-progress extraction (page reload recovery)
        $existingExtractionId = session('property_upload.extraction_id');

        if ($existingExtractionId) {
            $cacheKey = ExtractPropertyDescriptionJob::getCacheKey($existingExtractionId);
            $status = Cache::get($cacheKey);

            if ($status) {
                // Recovery: resume polling existing job
                $this->extractionId = $existingExtractionId;
                $this->extractionStatus = $status['status'];
                $this->extractionStage = $status['stage'] ?? '';
                $this->extractionProgress = $status['progress'] ?? 0;
                $this->extractionError = $status['error'] ?? null;
                $this->extractionStartedAt = session('property_upload.extraction_started_at');

                if ($status['status'] === 'completed' && $status['data']) {
                    $this->extractedData = $status['data'];
                    $this->qualityScore = $status['data']['quality_score'] ?? 0;
                    $this->cleanupExtraction();
                } elseif ($status['status'] === 'failed') {
                    $this->extractedData = $this->getEmptyStructure();
                    $this->qualityScore = 0;
                    $this->cleanupExtraction();
                }

                return;
            }

            // Cache expired - clear stale session data and start fresh
            session()->forget(['property_upload.extraction_id', 'property_upload.extraction_started_at']);
        }

        // No existing job, start new extraction
        $this->startExtraction();
    }

    protected function startExtraction(): void
    {
        $this->extractionId = Str::uuid()->toString();
        $this->extractionStatus = 'queued';
        $this->extractionStage = 'Preparando analisis...';
        $this->extractionProgress = 5;
        $this->extractionStartedAt = now()->timestamp;

        // Initialize status in cache
        ExtractPropertyDescriptionJob::initializeStatus($this->extractionId);

        // Dispatch the job to ai-enrichment queue (configured in Horizon)
        ExtractPropertyDescriptionJob::dispatch($this->extractionId, $this->originalDescription)
            ->onQueue('ai-enrichment');

        // Store extraction ID and start time in session for recovery
        session([
            'property_upload.extraction_id' => $this->extractionId,
            'property_upload.extraction_started_at' => $this->extractionStartedAt,
        ]);
    }

    /**
     * Poll for extraction status updates.
     */
    public function checkExtractionStatus(): void
    {
        if ($this->extractionStatus === 'completed' || $this->extractionStatus === 'failed' || ! $this->extractionId) {
            return;
        }

        // Check for timeout
        if ($this->extractionStartedAt && (now()->timestamp - $this->extractionStartedAt) > self::EXTRACTION_TIMEOUT_SECONDS) {
            $this->extractionStatus = 'failed';
            $this->extractionError = 'El proceso tomo demasiado tiempo. Por favor intenta de nuevo.';
            $this->extractedData = $this->getEmptyStructure();
            $this->qualityScore = 0;
            $this->cleanupExtraction();

            return;
        }

        $cacheKey = ExtractPropertyDescriptionJob::getCacheKey($this->extractionId);
        $status = Cache::get($cacheKey);

        if (! $status) {
            return;
        }

        $this->extractionStatus = $status['status'];
        $this->extractionStage = $status['stage'];
        $this->extractionProgress = $status['progress'];
        $this->extractionError = $status['error'];

        if ($status['status'] === 'completed' && $status['data']) {
            $this->extractedData = $status['data'];
            $this->qualityScore = $status['data']['quality_score'] ?? 0;
            $this->cleanupExtraction();
        } elseif ($status['status'] === 'failed') {
            $this->extractedData = $this->getEmptyStructure();
            $this->qualityScore = 0;
            $this->cleanupExtraction();
        }
    }

    /**
     * Clean up extraction cache and session data.
     */
    protected function cleanupExtraction(): void
    {
        $cacheKey = ExtractPropertyDescriptionJob::getCacheKey($this->extractionId);
        Cache::forget($cacheKey);
        session()->forget(['property_upload.extraction_id', 'property_upload.extraction_started_at']);
    }

    #[Computed]
    public function isExtracting(): bool
    {
        return in_array($this->extractionStatus, ['pending', 'queued', 'processing']);
    }

    public function reextract(): void
    {
        session()->forget('property_upload.extracted_data');
        $this->extractedData = [];
        $this->qualityScore = 0;
        $this->extractionError = null;
        $this->startExtraction();
    }

    /**
     * Update a nested value in the extracted data.
     */
    public function updateValue(string $path, mixed $value): void
    {
        $keys = explode('.', $path);
        $data = &$this->extractedData;

        foreach ($keys as $i => $key) {
            if ($i === count($keys) - 1) {
                // Convert empty strings to null for optional fields
                $data[$key] = $value === '' ? null : $value;
            } else {
                if (! isset($data[$key]) || ! is_array($data[$key])) {
                    $data[$key] = [];
                }
                $data = &$data[$key];
            }
        }
    }

    /**
     * Add an item to an array field.
     */
    public function addToArray(string $path, string $value): void
    {
        if (trim($value) === '') {
            return;
        }

        $keys = explode('.', $path);
        $data = &$this->extractedData;

        foreach ($keys as $key) {
            if (! isset($data[$key])) {
                $data[$key] = [];
            }
            $data = &$data[$key];
        }

        if (is_array($data) && ! in_array($value, $data, true)) {
            $data[] = $value;
        }
    }

    /**
     * Remove an item from an array field.
     */
    public function removeFromArray(string $path, int $index): void
    {
        $keys = explode('.', $path);
        $data = &$this->extractedData;

        foreach ($keys as $key) {
            if (! isset($data[$key])) {
                return;
            }
            $data = &$data[$key];
        }

        if (is_array($data) && isset($data[$index])) {
            unset($data[$index]);
            $data = array_values($data);
        }
    }

    public function back(): void
    {
        $this->saveToSession();
        $this->redirectRoute('agents.properties.upload.describe', navigate: true);
    }

    public function continue(): void
    {
        // Validate required fields
        $property = $this->extractedData['property'] ?? [];
        $pricing = $this->extractedData['pricing'] ?? [];

        if (empty($property['property_type'])) {
            $this->addError('property.property_type', 'Selecciona el tipo de propiedad.');

            return;
        }

        if (empty($property['operation_type'])) {
            $this->addError('property.operation_type', 'Selecciona el tipo de operacion.');

            return;
        }

        if (empty($pricing['price']) || $pricing['price'] < 1000) {
            $this->addError('pricing.price', 'Ingresa un precio valido.');

            return;
        }

        $this->saveToSession();
        $this->redirectRoute('agents.properties.upload.photos', navigate: true);
    }

    protected function saveToSession(): void
    {
        session([
            'property_upload.extracted_data' => $this->extractedData,
            'property_upload.quality_score' => $this->qualityScore,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getEmptyStructure(): array
    {
        return PropertyDescriptionExtractionService::getEmptyStructure();
    }

    public function render(): View
    {
        return view('livewire.agents.properties.upload.review', [
            'propertyTypes' => PropertyType::cases(),
            'operationTypes' => OperationType::cases(),
        ]);
    }
}
