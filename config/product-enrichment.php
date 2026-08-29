<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Unmatched Row Rejection Threshold (FR-004)
    |--------------------------------------------------------------------------
    |
    | The fraction of uploaded spreadsheet rows that may have no matching photo
    | before the import is rejected outright. Default is 0.5 (50%). When more
    | than this share of rows are unmatched, no products are created or updated.
    |
    */
    'unmatched_row_threshold' => (float) env('PE_UNMATCHED_ROW_THRESHOLD', 0.5),

    /*
    |--------------------------------------------------------------------------
    | Research Results Cache TTL (FR-026)
    |--------------------------------------------------------------------------
    |
    | Number of days to cache a product's research results (keyed by a stable
    | hash of its identifiers: sku, name, brand, gtin, mpn) before repeating
    | the external research call.
    |
    */
    'research_cache_ttl_days' => (int) env('PE_RESEARCH_CACHE_TTL_DAYS', 7),
];
