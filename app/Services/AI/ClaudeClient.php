<?php

namespace App\Services\AI;

use App\Services\ApiUsageTracker;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ClaudeClient
{
    protected string $apiKey;

    protected string $model;

    protected int $maxTokens;

    protected int $timeout;

    protected string $baseUrl = 'https://api.anthropic.com/v1';

    protected ?ApiUsageTracker $tracker = null;

    protected ?ClaudeCallContext $context = null;

    public function __construct()
    {
        $this->apiKey = config('services.anthropic.api_key') ?? '';
        $this->model = config('services.anthropic.model', 'claude-sonnet-4-20250514');
        $this->maxTokens = config('services.anthropic.max_tokens', 4096);
        $this->timeout = config('services.anthropic.timeout', 60);

        if (empty($this->apiKey)) {
            throw new \RuntimeException('ANTHROPIC_API_KEY is not configured. Please add it to your .env file.');
        }
    }

    /**
     * Return a new ClaudeClient instance with tracking enabled.
     * Usage is automatically logged for both success and failure.
     */
    public function withTracking(ApiUsageTracker $tracker, ClaudeCallContext $context): self
    {
        $clone = clone $this;
        $clone->tracker = $tracker;
        $clone->context = $context;

        return $clone;
    }

    /**
     * Send a message to Claude with optional tool use.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @param  array<int, array<string, mixed>>  $tools
     * @return array{id: string, type: string, role: string, content: array, model: string, stop_reason: string, usage: array{input_tokens: int, output_tokens: int}}
     *
     * @throws \RuntimeException
     */
    public function message(array $messages, array $tools = [], ?string $system = null): array
    {
        $startTime = microtime(true);

        $payload = [
            'model' => $this->model,
            'max_tokens' => $this->maxTokens,
            'messages' => $messages,
        ];

        if (! empty($tools)) {
            $payload['tools'] = $tools;
            $payload['tool_choice'] = ['type' => 'auto'];
        }

        if ($system) {
            // Use prompt caching for system prompt - 90% cost reduction on cached tokens
            // and cached tokens don't count toward rate limits
            $payload['system'] = [
                [
                    'type' => 'text',
                    'text' => $system,
                    'cache_control' => ['type' => 'ephemeral'],
                ],
            ];
        }

        Log::debug('Claude API request', [
            'model' => $this->model,
            'messages_count' => count($messages),
            'tools_count' => count($tools),
        ]);

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'x-api-key' => $this->apiKey,
                    'anthropic-version' => '2023-06-01',
                    'content-type' => 'application/json',
                ])
                ->post("{$this->baseUrl}/messages", $payload);

            $response->throw();

            $data = $response->json();

            Log::debug('Claude API response', [
                'id' => $data['id'] ?? null,
                'stop_reason' => $data['stop_reason'] ?? null,
                'input_tokens' => $data['usage']['input_tokens'] ?? 0,
                'output_tokens' => $data['usage']['output_tokens'] ?? 0,
                'cache_creation_tokens' => $data['usage']['cache_creation_input_tokens'] ?? 0,
                'cache_read_tokens' => $data['usage']['cache_read_input_tokens'] ?? 0,
            ]);

            $this->logIfTracking($data['usage'] ?? [], null, $startTime);

            return $data;
        } catch (ConnectionException $e) {
            Log::error('Claude API connection failed', [
                'error' => $e->getMessage(),
            ]);

            $this->logIfTracking([], 'connection_error', $startTime);

            throw new \RuntimeException("Failed to connect to Claude API: {$e->getMessage()}", 0, $e);
        } catch (RequestException $e) {
            Log::error('Claude API request failed', [
                'status' => $e->response?->status(),
                'error' => $e->getMessage(),
                'body' => $e->response?->body(),
            ]);

            $this->logIfTracking([], $this->classifyError($e), $startTime);

            throw new \RuntimeException("Claude API request failed: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Log API call if tracking is enabled.
     *
     * @param  array<string, int>  $usage
     */
    private function logIfTracking(array $usage, ?string $errorType, float $startTime): void
    {
        if ($this->tracker && $this->context) {
            $this->tracker->logClaudeUsage(
                $this->context->operation,
                $usage,
                $this->model,
                $this->context,
                $errorType,
                (int) ((microtime(true) - $startTime) * 1000)
            );
        }
    }

    /**
     * Classify an error based on HTTP status and response body.
     */
    private function classifyError(RequestException $e): string
    {
        $status = $e->response?->status();
        $body = $e->response?->body() ?? '';

        if ($status === 429) {
            return 'rate_limit';
        }
        if ($status === 529) {
            return 'overloaded';
        }
        if (str_contains($body, 'credit balance')) {
            return 'credit_balance';
        }
        if ($status === 401) {
            return 'auth_error';
        }
        if ($status === 403) {
            return 'forbidden';
        }
        if ($status >= 500) {
            return 'server_error';
        }

        return 'unknown';
    }

    /**
     * Extract tool use result from Claude response.
     *
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>|null
     */
    public function extractToolUse(array $response, string $toolName): ?array
    {
        foreach ($response['content'] ?? [] as $block) {
            if (($block['type'] ?? '') === 'tool_use' && ($block['name'] ?? '') === $toolName) {
                return $block['input'] ?? null;
            }
        }

        return null;
    }

    /**
     * Extract text content from Claude response.
     */
    public function extractText(array $response): string
    {
        $texts = [];
        foreach ($response['content'] ?? [] as $block) {
            if (($block['type'] ?? '') === 'text') {
                $texts[] = $block['text'] ?? '';
            }
        }

        return implode("\n", $texts);
    }

    /**
     * Get token usage from response.
     *
     * @return array{input_tokens: int, output_tokens: int, cache_creation_input_tokens: int, cache_read_input_tokens: int}
     */
    public function getUsage(array $response): array
    {
        return [
            'input_tokens' => $response['usage']['input_tokens'] ?? 0,
            'output_tokens' => $response['usage']['output_tokens'] ?? 0,
            'cache_creation_input_tokens' => $response['usage']['cache_creation_input_tokens'] ?? 0,
            'cache_read_input_tokens' => $response['usage']['cache_read_input_tokens'] ?? 0,
        ];
    }
}
