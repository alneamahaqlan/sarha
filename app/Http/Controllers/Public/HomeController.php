<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\HomepageRenderService;

class HomeController extends Controller
{
    public function __construct(private readonly HomepageRenderService $renderer)
    {
    }

    /**
     * The homepage is now a CMS. Sections, their order, and per-section
     * config (title overrides, item limits, scheduling, mobile visibility)
     * all live in homepage_sections. The view just iterates and includes
     * `public.sections.<type>` for each renderable row.
     */
    public function index()
    {
        $sections = $this->renderer->build();
        $user     = auth('web')->user();

        return view('public.home', [
            'sections' => $sections,
            'user'     => $user,
        ]);
    }
}
