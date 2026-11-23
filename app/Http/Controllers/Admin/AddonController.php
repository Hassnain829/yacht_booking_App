<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AddonUnit;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AddonRequest;
use App\Http\Requests\Admin\RecordLifecycleRequest;
use App\Models\Addon;
use App\Services\RecordWriter;
use App\Services\TrashService;
use App\Support\Admin\ListQuery;
use App\Support\IconNames;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Admin → Add-ons (PLAN 3.4.2): extras guests add to a booking. List, add, edit, show / hide, Trash. */
class AddonController extends Controller
{
    private const SORTS = ['sort' => 'sort', 'name' => 'name', 'price' => 'price_cents', 'updated' => 'updated_at'];

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Addon::class);

        $list = new ListQuery($request);
        $q = $list->search();
        $inTrash = $list->inTrash();
        [$sort, $column, $dir] = $list->sort(self::SORTS, 'sort');

        $addons = Addon::query()
            ->when($inTrash, fn ($query) => $query->onlyTrashed())
            ->when($q !== '', fn ($query) => $query->where(fn ($where) => $where
                ->where('name', 'like', '%'.addcslashes($q, '%_\\').'%')
                ->orWhere('description', 'like', '%'.addcslashes($q, '%_\\').'%')))
            ->orderBy($column, $dir)
            ->orderBy('name')
            ->paginate(ListQuery::PER_PAGE)
            ->withQueryString();

        return view('admin.addons.index', [
            'addons' => $addons,
            'q' => $q,
            'inTrash' => $inTrash,
            'sort' => $sort,
            'dir' => $dir,
            'counts' => ['active' => Addon::query()->count(), 'trash' => Addon::onlyTrashed()->count()],
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Addon::class);

        return $this->form(new Addon([
            'is_active' => true,
            'icon' => 'gift',
            'unit' => AddonUnit::Flat,
            'max_quantity' => 1,
            'sort' => Addon::withTrashed()->max('sort') + 1,
        ]));
    }

    public function store(AddonRequest $request, RecordWriter $writer): RedirectResponse
    {
        $addon = new Addon;
        $writer->save($addon, $request->addonData(), $request->user());

        return redirect()->route('admin.addons.edit', $addon)->with('status', "{$addon->name} was added.");
    }

    public function edit(Addon $addon): View
    {
        Gate::authorize('viewAny', Addon::class);

        return $this->form($addon);
    }

    public function update(AddonRequest $request, Addon $addon, RecordWriter $writer): RedirectResponse
    {
        $writer->save($addon, $request->addonData(), $request->user());

        return redirect()->route('admin.addons.edit', $addon)->with('status', 'Changes saved.');
    }

    public function trash(RecordLifecycleRequest $request, Addon $addon, TrashService $trash): RedirectResponse
    {
        $trash->trash($addon, $request->reason(), $request->user());

        return redirect()->route('admin.addons.index')->with('status', "{$addon->name} was moved to the Trash. You can restore it from the Trash tab.");
    }

    public function restore(RecordLifecycleRequest $request, Addon $addon, TrashService $trash): RedirectResponse
    {
        $trash->restore($addon, $request->reason(), $request->user());

        return redirect()->route('admin.addons.edit', $addon)->with('status', "{$addon->name} was restored.");
    }

    public function destroy(RecordLifecycleRequest $request, Addon $addon, TrashService $trash): RedirectResponse
    {
        $trash->forceDelete($addon, $request->reason(), $request->user());

        return redirect()->route('admin.addons.index', ['view' => 'trash'])->with('status', "{$addon->name} was deleted permanently.");
    }

    private function form(Addon $addon): View
    {
        return view('admin.addons.form', [
            'addon' => $addon,
            'icons' => IconNames::options(),
            'units' => collect(AddonUnit::cases())->mapWithKeys(fn (AddonUnit $unit) => [$unit->value => $unit === AddonUnit::Flat ? 'Per booking (flat)' : 'Per guest'])->all(),
            'trashBlockers' => $addon->exists && ! $addon->trashed() ? $addon->trashBlockers() : [],
            'deleteBlockers' => $addon->trashed() ? $addon->permanentDeleteBlockers() : [],
        ]);
    }
}
