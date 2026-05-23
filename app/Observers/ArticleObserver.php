<?php

namespace App\Observers;

use App\Models\Article;
use App\Services\NotificationService;

class ArticleObserver
{
    public const BASIC_MONTHLY_LIMIT = 5;
    public const WARN_THRESHOLD = 3;

    public function __construct(private readonly NotificationService $notifications) {}

    /**
     * Enforce monthly publish limit for basic-plan complexes.
     * Premium has no limit.
     */
    public function creating(Article $article): void
    {
        if (! $article->clinic_id || ! $article->is_published) return;

        $clinic = $article->clinic;
        if (! $clinic || $clinic->subscription_type === 'premium') return;

        $usedThisMonth = Article::where('clinic_id', $clinic->id)
            ->where('is_published', true)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        if ($usedThisMonth >= self::BASIC_MONTHLY_LIMIT) {
            // Force draft + notify
            $article->is_published = false;
            $this->notifications->push(
                $clinic,
                type: 'article_limit_hit',
                title: __('admin.notif.limit_hit_title'),
                body:  __('admin.notif.limit_hit_body'),
                icon: 'heroicon-o-no-symbol',
                priority: 'high',
            );
        } elseif ($usedThisMonth + 1 === self::WARN_THRESHOLD) {
            $this->notifications->push(
                $clinic,
                type: 'article_limit_warn',
                title: __('admin.notif.limit_warn_title'),
                body:  __('admin.notif.limit_warn_body', ['used' => $usedThisMonth + 1]),
                icon: 'heroicon-o-information-circle',
                priority: 'normal',
            );
        }
    }

    public function updating(Article $article): void
    {
        // If switching from draft → published, run the same check
        if (! $article->isDirty('is_published')) return;
        if (! $article->is_published) return;

        $this->creating($article);
    }

    /**
     * Notify admins when an article is created already published.
     */
    public function created(Article $article): void
    {
        if (! $article->is_published) return;

        $this->notifications->articlePublished($article);
    }

    /**
     * Notify admins only on the draft → published transition (no double-fire).
     */
    public function updated(Article $article): void
    {
        if (! $article->wasChanged('is_published')) return;
        if (! $article->is_published) return;

        $this->notifications->articlePublished($article);
    }
}
