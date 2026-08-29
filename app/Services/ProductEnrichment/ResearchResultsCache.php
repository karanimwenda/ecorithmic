<?php

namespace App\Services\ProductEnrichment;

use Illuminate\Support\Facades\Cache;

/**
 * Caches a product's established research facts/sources keyed by a stable hash
 * of its identifiers (sku, name, brand, gtin, mpn) per FR-026 and research.md §7.
 *
 * Distinct from QuoteVerifier's page-text cache (which is URL-keyed, short-TTL,
 * and only avoids re-fetching pages mid-run). This cache avoids repeating the
 * expensive sonar-pro-search call when a product is reprocessed within the TTL.
 */
class ResearchResultsCache
{
    private const string CACHE_PREFIX = 'research_results:v1:';

    /**
     * Get cached research facts for the given product identifiers.
     *
     * @param  array<string, string>  $identifiers  e.g. ['sku' => 'SKU123', 'name' => 'Widget']
     * @return array<string, mixed>|null null on cache miss
     */
    public function get(array $identifiers): ?array
    {
        $key = $this->buildKey($identifiers);
        /** @var array<string, mixed>|null $result */
        $result = Cache::get($key);

        return $result;
    }

    /**
     * Store research facts for the given product identifiers.
     *
     * @param  array<string, string>  $identifiers
     * @param  array<string, mixed>  $facts
     */
    public function put(array $identifiers, array $facts): void
    {
        $key = $this->buildKey($identifiers);
        $ttlDays = (int) config('product-enrichment.research_cache_ttl_days', 7);

        Cache::put($key, $facts, now()->addDays($ttlDays));
    }

    /**
     * Invalidate the cache for the given identifiers.
     *
     * @param  array<string, string>  $identifiers
     */
    public function forget(array $identifiers): void
    {
        Cache::forget($this->buildKey($identifiers));
    }

    /**
     * @param  array<string, string>  $identifiers
     */
    private function buildKey(array $identifiers): string
    {
        // Only use the stable identifier fields, sorted for consistency
        $stable = array_filter(
            array_intersect_key($identifiers, array_flip(['sku', 'name', 'brand', 'gtin', 'mpn'])),
            fn ($v) => $v !== '',
        );
        ksort($stable);

        return self::CACHE_PREFIX.md5(serialize($stable));
    }
}
