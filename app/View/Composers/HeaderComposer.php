<?php

namespace App\View\Composers;

use Illuminate\Http\Request;
use Illuminate\Support\Fluent;
use Illuminate\View\View;

/**
 * Gives the header and mobile menu the signed-in account (or null for a guest).
 * Only display fields are passed to the views — never the model itself.
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
        $user = $this->request->user();

        return $user ? new Fluent([
            'name' => $user->name,
            'first_name' => $user->first_name,
            'email' => $user->email,
            'initials' => $user->initials,
            'role' => $user->role->value,
        ]) : null;
    }
}
