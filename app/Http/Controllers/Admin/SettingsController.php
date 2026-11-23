<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SettingsRequest;
use App\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Admin → Settings (PLAN 3.4.5): business rules and details, saved through SettingsService with a reason + password. */
class SettingsController extends Controller
{
    public function show(Request $request, SettingsService $settings): View
    {
        Gate::authorize('manage-settings');

        return view('admin.system.settings', [
            'twoFactorOn' => $request->user()->hasEnabledTwoFactorAuthentication(),
            'settings' => $settings->all(),
            'gratuityChoices' => SettingsRequest::GRATUITY_CHOICES,
        ]);
    }

    public function update(SettingsRequest $request, SettingsService $settings): RedirectResponse
    {
        $changed = $settings->update($request->values(), $request->user(), $request->reason());

        return redirect()->route('admin.settings')->with('status', $changed === []
            ? 'Nothing changed.'
            : trans_choice('{1} 1 setting saved.|[2,*] :count settings saved.', count($changed)).' New bookings use the new values; existing bookings keep theirs.');
    }
}
