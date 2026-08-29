<?php

namespace App\Services\ProductEnrichment;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Verifies that a verbatim quote actually appears in the source page content (FR-009).
 * Uses Laravel's HTTP Client for fetching; caches normalized page text by URL
 * with a short TTL to avoid re-fetching the same page in one research run.
 * This is distinct from the product-identifier-keyed ResearchResultsCache (FR-026).
 */
class QuoteVerifier
{
    /** Short TTL for the page-text cache (minutes). */
    private const int PAGE_CACHE_TTL_MINUTES = 10;

    /**
     * Returns true if $quote is found as a case-insensitive substring of the
     * normalized visible text at $sourceUrl.
     */
    public function verify(string $quote, string $sourceUrl): bool
    {
        $normalizedText = $this->fetchNormalizedText($sourceUrl);

        if ($normalizedText === null) {
            return false;
        }

        return str_contains(
            mb_strtolower($normalizedText),
            mb_strtolower($quote),
        );
    }

    private function fetchNormalizedText(string $url): ?string
    {
        $cacheKey = 'quote_verifier:page:'.md5($url);

        return Cache::remember($cacheKey, self::PAGE_CACHE_TTL_MINUTES * 60, function () use ($url) {
            try {
                $response = Http::timeout(15)->get($url);

                if (! $response->successful()) {
                    return null;
                }

                return $this->normalizeHtml($response->body());
            } catch (\Throwable) {
                return null;
            }
        });
    }

    /**
     * Strips HTML tags, scripts, and styles, then collapses whitespace.
     */
    private function normalizeHtml(string $html): string
    {
        // Remove script and style blocks
        $text = preg_replace('/<(script|style)[^>]*>.*?<\/\1>/si', ' ', $html) ?? $html;

        // Strip remaining tags
        $text = strip_tags($text);

        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Collapse whitespace
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;

        return trim($text);
    }
}
