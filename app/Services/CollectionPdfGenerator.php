<?php

namespace App\Services;

use App\Models\Collection;
use Spatie\Browsershot\Browsershot;

class CollectionPdfGenerator
{
    public function __construct(
        private CollectionPropertyPresenter $presenter,
    ) {}

    /**
     * Generate a PDF for the given collection.
     */
    public function generate(Collection $collection): string
    {
        $collection->load(['properties.listings', 'user', 'client']);

        $html = $this->renderHtml($collection);

        return $this->renderPdf($html);
    }

    private function renderHtml(Collection $collection): string
    {
        // Embed images as base64 for reliable PDF rendering
        $properties = $this->presenter->prepareProperties($collection->properties, embedImages: true);
        $agent = $collection->user;

        return view('pdf.collection', [
            'collection' => $collection,
            'properties' => $properties,
            'agent' => $agent,
            'brandColor' => $agent->brand_color ?? '#3b82f6',
            'generatedAt' => now(),
        ])->render();
    }

    private function renderPdf(string $html): string
    {
        $browsershot = Browsershot::html($html)
            ->format('letter')
            ->margins(0.5, 0.5, 0.75, 0.5, 'in') // Extra bottom margin for page numbers
            ->showBackground()
            ->showBrowserHeaderAndFooter()
            ->headerHtml('<div></div>')
            ->footerHtml($this->getFooterHtml())
            ->timeout(120);

        // Configure Node/NPM binaries from config
        if ($nodeBinary = config('browsershot.node_binary')) {
            $browsershot->setNodeBinary($nodeBinary);
        }
        if ($npmBinary = config('browsershot.npm_binary')) {
            $browsershot->setNpmBinary($npmBinary);
        }

        // Use config for Chrome path if set
        if ($chromePath = config('browsershot.chrome_path')) {
            $browsershot->setChromePath($chromePath);
        }

        // Enable no-sandbox mode for server environments
        if (config('browsershot.no_sandbox', false)) {
            $browsershot->setOption('args', ['--no-sandbox', '--disable-setuid-sandbox']);
        }

        return $browsershot->pdf();
    }

    private function getFooterHtml(): string
    {
        return '<div style="font-size: 9px; width: 100%; text-align: center; color: #888; padding: 0 0.5in;">
            <span class="pageNumber"></span> / <span class="totalPages"></span>
        </div>';
    }
}
