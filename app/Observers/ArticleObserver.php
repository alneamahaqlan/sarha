<?php

namespace App\Observers;

use App\Models\Article;
use App\Services\FeatureGate;
use App\Services\NotificationService;

/**
 * Article publishing guard. Reads the publish ceiling from the
 * clinic's current package via FeatureGate — the old hard-coded
 * `BASIC_MONTHLY_LIMIT = 5` is now an admin-editable column on the
 * package row, so different tiers (free=2 / standard=10 / premium=∞)
 * are honoured without code changes.
 *
 * Warn behaviour: instead of warning at a fixed "3 used" mark, we
 * warn when the clinic has 2 publishes left in the bucket. Scales
 * naturally with whatever limit the admin sets per tier.
 */
class ArticleObserver
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly FeatureGate $gate,
    ) {}

    /**
     * Enforce the monthly publish limit. Unlimited tiers (premium)
     * short-circuit; bounded tiers force-draft when the bucket is
     * empty and fire a one-time notification.
     */
    public function creating(Article $article): void
    {
        if (! $article->clinic_id || ! $article->is_published) return;

        $clinic = $article->clinic;
        if (! $clinic) return;

        $limit = $this->gate->articlesLimit($clinic);
        if ($limit === null) return; // unlimited tier

        $used = $this->gate->articlesUsedThisMonth($clinic);

        if ($used >= $limit) {
            // Bucket empty — force draft + notify.
            $article->is_published = false;
            $this->notifications->push(
                $clinic,
                type: 'article_limit_hit',
                title: __('admin.notif.limit_hit_title'),
                body:  __('admin.notif.limit_hit_body', ['limit' => $limit]),
                icon: 'heroicon-o-no-symbol',
                priority: 'high',
            );
        } elseif (($limit - ($used + 1)) === 1) {
            // Two left after this one — fire the gentle warning.
            $this->notifications->push(
                $clinic,
                type: 'article_limit_warn',
                title: __('admin.notif.limit_warn_title'),
                body:  __('admin.notif.limit_warn_body', ['used' => $used + 1, 'limit' => $limit]),
                icon: 'heroicon-o-information-circle',
                priority: 'normal',
            );
        }
    }

    public function updating(Article $article): void
    {
        // Same gate runs on draft → publish transitions.
        if (! $article->isDirty('is_published')) return;
        if (! $article->is_published) return;

        $this->creating($article);
    }

    public function created(Article $article): void
    {
        if (! $article->is_published) return;
        $this->notifications->articlePublished($article);
    }

    public function updated(Article $article): void
    {
        if (! $article->wasChanged('is_published')) return;
        if (! $article->is_published) return;

        $this->notifications->articlePublished($article);
    }
}
