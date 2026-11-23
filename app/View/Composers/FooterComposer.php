<?php

namespace App\View\Composers;

use App\Services\PublicCatalog;
use Illuminate\View\View;

/**
 * Gives the footer its harbors and experience links.
 * From the database catalog since 3.5 (PublicCatalog): only shown harbors and experiences.
 */
class FooterComposer
{
    public function __construct(private PublicCatalog $catalog) {}

    public function compose(View $view): void
    {
        $view->with([
            'footerHarbors' => $this->catalog->harbors(),
            'footerExperiences' => $this->catalog->experiences()->take(7),
        ]);
    }
}
