<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CancellationPolicyRequest;
use App\Models\CancellationPolicy;
use App\Services\RecordWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Admin → Cancellation policies (PLAN §7.4, 3.4.5): Flexible / Moderate / Strict. Edit only — the text guests
 * read and the refund rules. Saving needs a reason and the password; bookings keep their own snapshot.
 */
class CancellationPolicyController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', CancellationPolicy::class);

        return view('admin.policies.index', [
            'policies' => CancellationPolicy::query()->withCount(['boats' => fn ($query) => $query->withTrashed()])->orderBy('sort')->get(),
        ]);
    }

    public function edit(CancellationPolicy $policy): View
    {
        Gate::authorize('viewAny', CancellationPolicy::class);

        return view('admin.policies.form', ['policy' => $policy]);
    }

    public function update(CancellationPolicyRequest $request, CancellationPolicy $policy, RecordWriter $writer): RedirectResponse
    {
        $writer->save($policy, $request->policyData(), $request->user(), reason: $request->reason());

        return redirect()->route('admin.policies.edit', $policy)->with('status', 'Policy saved. New bookings use it; bookings already made keep theirs.');
    }
}
