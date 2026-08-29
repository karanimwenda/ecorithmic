<?php

namespace App\Services\ProductEnrichment;

use Illuminate\Support\Facades\Http;

/**
 * Thin wrapper around OpenRouter's OpenAI-compatible chat completions endpoint.
 * Always uses Laravel's HTTP Client (never raw Guzzle) per Constitution VIII.
 * Computes USD cost from each response's usage data per research.md §1 pricing.
 */
class OpenRouterClient
{
    // Per-call search fee for sonar-pro-search (USD per 1k requests)
    private const float SONAR_PER_REQUEST_FEE = 0.018;

    // Input/output token rates per million tokens (USD)
    private const array RATES = [
        'perplexity/sonar-pro-search' => ['input' => 3.00, 'output' => 15.00],
        'ibm-granite/granite-4.1-8b' => ['input' => 0.05, 'output' => 0.10],
        'google/gemini-2.5-flash-lite' => ['input' => 0.10, 'output' => 0.40],
    ];

    private readonly string $apiKey;

    private readonly string $baseUrl;

    public function __construct(string $apiKey = '', string $baseUrl = '')
    {
        $this->apiKey = $apiKey ?: (string) config('services.openrouter.api_key', '');
        $this->baseUrl = $baseUrl ?: (string) config('services.openrouter.base_url', 'https://openrouter.ai/api/v1');
    }

    /**
     * Send a chat completions request.
     *
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<string, mixed>|null  $responseFormat  JSON schema response format
     * @param  array<string, mixed>|null  $imageAttachment  ['url' => ..., 'detail' => 'auto'] for vision
     * @return array{response: array<string, mixed>, cost_usd: float}
     */
    public function chat(
        string $model,
        array $messages,
        ?array $responseFormat = null,
        ?array $imageAttachment = null,
    ): array {
        if ($imageAttachment !== null && count($messages) > 0) {
            // Inject image into the last user message
            $lastIndex = array_key_last($messages);
            $lastContent = $messages[$lastIndex]['content'];
            $messages[$lastIndex]['content'] = [
                ['type' => 'text', 'text' => $lastContent],
                ['type' => 'image_url', 'image_url' => $imageAttachment],
            ];
        }

        $payload = [
            'model' => $model,
            'messages' => $messages,
        ];

        if ($responseFormat !== null) {
            $payload['response_format'] = $responseFormat;
        }

        $response = Http::withToken($this->apiKey)
            ->baseUrl($this->baseUrl)
            ->timeout(120)
            ->post('/chat/completions', $payload);

        $response->throw();

        $data = $response->json();
        $costUsd = $this->computeCost($model, $data['usage'] ?? []);

        return [
            'response' => $data,
            'cost_usd' => $costUsd,
        ];
    }

    /**
     * @param  array<string, int>  $usage
     */
    private function computeCost(string $model, array $usage): float
    {
        $rates = self::RATES[$model] ?? ['input' => 0.0, 'output' => 0.0];

        $inputTokens = $usage['prompt_tokens'] ?? 0;
        $outputTokens = $usage['completion_tokens'] ?? 0;

        $cost = ($inputTokens / 1_000_000) * $rates['input']
            + ($outputTokens / 1_000_000) * $rates['output'];

        // sonar-pro-search also charges per request
        if ($model === 'perplexity/sonar-pro-search') {
            $cost += self::SONAR_PER_REQUEST_FEE;
        }

        return round($cost, 6);
    }
}
