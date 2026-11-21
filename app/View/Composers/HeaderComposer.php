<?php

namespace App\View\Composers;

use App\Support\Demo\DemoCustomer;
use App\Support\Demo\DemoOwner;
use Illuminate\Http\Request;
use Illuminate\Support\Fluent;
use Illuminate\View\View;

/**
 * Gives the header and mobile menu the signed-in account (or null for a guest).
 *
 * PHASE 1 PREVIEW: there is no login yet, so pages that will require one show a demo account —
 * the owner area shows the demo owner, the customer account shows the demo customer, every other
 * page shows the guest menu. Phase 2: `$request->user()` (with its role) replaces all of this.
 */
class HeaderComposer
{
    public function __construct(private Request $request) {}

    public function compose(View $view): void
    {
        $view->with('headerAccount', $this->account());
    }

    private function account(): ?Fluent
    {
        if ($user = $this->request->user()) {
            return new Fluent([
                'name' => $user->name,
                'first_name' => $user->first_name,
                'email' => $user->email,
                'initials' => $user->initials,
                'role' => 'customer', // Phase 2.2: $user->role
            ]);
        }

        // PHASE 1 PREVIEW (until Phase 2.3 puts these areas behind login).
        $profile = match (true) {
            $this->request->routeIs('owner.*') => [app(DemoOwner::class)->profile(), 'owner'],
            $this->request->routeIs('account.*') => [app(DemoCustomer::class)->profile(), 'customer'],
            default => null,
        };

        if (! $profile) {
            return null;
        }

        [$p, $role] = $profile;

        return new Fluent([
            'name' => $p->name,
            'first_name' => $p->first_name,
            'email' => $p->email,
            'initials' => $p->initials,
            'role' => $role,
        ]);
    }
}
