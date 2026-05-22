<?php

namespace App\Policies;

use App\Models\Article;
use App\Models\Clinic;

class ArticlePolicy
{
    public function viewAny(Clinic $clinic): bool
    {
        return true;
    }

    public function view(Clinic $clinic, Article $article): bool
    {
        return $clinic->id === $article->clinic_id;
    }

    public function create(Clinic $clinic): bool
    {
        return true;
    }

    public function update(Clinic $clinic, Article $article): bool
    {
        return $clinic->id === $article->clinic_id;
    }

    public function delete(Clinic $clinic, Article $article): bool
    {
        return $clinic->id === $article->clinic_id;
    }
}
