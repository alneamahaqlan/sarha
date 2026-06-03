<?php

namespace App\Enums;

/**
 * The 6 admin-monitored sources of clinic/service impressions.
 *
 * - SEARCH         — user typed a query on /search
 * - FILTER         — user opened /search with only city/category filters
 * - HOME           — clinic/service surfaced on the public homepage
 * - SIMILAR        — clinic/service appeared in another clinic's
 *                    "similar services" deep-link
 * - AI             — clinic/service mentioned in an AI assistant reply
 * - COMPARE        — clinic shown in the /compare side-by-side view
 * - PROFILE        — the clinic profile page was opened (ANY source: direct
 *                    link, share, preview, etc.) — every view counts.
 *
 * The values are stored verbatim in `clinic_impressions.source` and
 * `service_impressions.source`. Don't rename without a data migration.
 */
final class ImpressionSource
{
    public const SEARCH   = 'search';
    public const FILTER   = 'filter';
    public const HOME     = 'home';
    public const SIMILAR  = 'similar_services';
    public const AI       = 'ai';
    public const COMPARE  = 'compare';
    public const PROFILE  = 'profile';

    /** Every supported source, in canonical UI order. */
    public const ALL = [
        self::SEARCH,
        self::FILTER,
        self::HOME,
        self::SIMILAR,
        self::COMPARE,
        self::PROFILE,
        self::AI,
    ];

    /**
     * Sources shown in the CLINIC-facing stats screen. AI is excluded
     * by design — the spec asks for AI's contribution to flow into the
     * total silently, without a separate row that names the source.
     */
    public const CLINIC_VISIBLE = [
        self::SEARCH,
        self::FILTER,
        self::HOME,
        self::SIMILAR,
        self::COMPARE,
        self::PROFILE,
    ];
}
