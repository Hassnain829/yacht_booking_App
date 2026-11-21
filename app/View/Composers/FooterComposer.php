<?php

namespace App\View\Composers;

use App\Support\Demo\DemoCatalog;
use Illuminate\View\View;

/**
 * Gives the footer its harbors and experience links.
 * Phase 3: read from the Harbor / Experience models instead of the demo catalog.
 */
class FooterComposer
{
    public function __construct(private DemoCatalog $catalog) {}

    public function compose(View $view): void
    {
        $view->with([
            'footerHarbors' => $this->catalog->harbors(),
            'footerExperiences' => $this->catalog->experiences()->take(7),
        ]);
    }
}
