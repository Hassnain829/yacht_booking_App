<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AmenityGroup;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AmenityRequest;
use App\Http\Requests\Admin\RecordLifecycleRequest;
use App\Models\Amenity;
use App\Services\AuditService;
use App\Services\RecordWriter;
use App\Support\IconNames;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Admin → Amenities (PLAN 3.4.5): the lists boats pick from (highlights, features, water toys, navigation).
 * Deleting one removes it from the boats that had it ticked (database cascade); it needs a reason and the
 * password, and the audit entry records how many boats had it.
 */
class AmenityController extends Controller
{
    public const GROUP_LABELS = [
        'highlight' => 'Highlights (shown on the boat page)',
        'feature' => 'Features',
        'extra' => 'Water toys & extras',
        'navigation' => 'Navigation',
    ];

    public function index(): View
    {
        Gate::authorize('viewAny', Amenity::class);

        return view('admin.amenities.index', [
            'groups' => Amenity::query()->withCount('boats')->orderBy('sort')->orderBy('name')->get()->groupBy(fn (Amenity $a) => $a->group->value),
            'labels' => self::GROUP_LABELS,
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Amenity::class);

        $group = request()->query('group');

        return $this->form(new Amenity(['group' => is_string($group) && AmenityGroup::tryFrom($group) ? $group : AmenityGroup::Feature->value, 'sort' => 0]));
    }

    public function store(AmenityRequest $request, RecordWriter $writer): RedirectResponse
    {
        $amenity = new Amenity;
        $writer->save($amenity, $request->validated(), $request->user());

        return redirect()->route('admin.amenities.index')->with('status', "{$amenity->name} was added.");
    }

    public function edit(Amenity $amenity): View
    {
        Gate::authorize('viewAny', Amenity::class);

        return $this->form($amenity->loadCount('boats'));
    }

    public function update(AmenityRequest $request, Amenity $amenity, RecordWriter $writer): RedirectResponse
    {
        $writer->save($amenity, $request->validated(), $request->user());

        return redirect()->route('admin.amenities.index')->with('status', 'Changes saved.');
    }

    public function destroy(RecordLifecycleRequest $request, Amenity $amenity, AuditService $audit): RedirectResponse
    {
        DB::transaction(function () use ($amenity, $request, $audit): void {
            $boats = DB::table('amenity_boat')->where('amenity_id', $amenity->getKey())->count();
            $amenity->delete();
            $audit->record('amenity.deleted', $amenity, ['name' => $amenity->name, 'group' => $amenity->group->value, 'boats' => $boats], [], $request->reason(), $request->user());
        });

        return redirect()->route('admin.amenities.index')->with('status', "{$amenity->name} was deleted.");
    }

    private function form(Amenity $amenity): View
    {
        return view('admin.amenities.form', [
            'amenity' => $amenity,
            'labels' => self::GROUP_LABELS,
            'icons' => IconNames::options(),
        ]);
    }
}
