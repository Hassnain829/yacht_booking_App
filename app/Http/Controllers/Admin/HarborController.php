<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\HarborRequest;
use App\Http\Requests\Admin\RecordLifecycleRequest;
use App\Models\Harbor;
use App\Services\RecordWriter;
use App\Services\TrashService;
use App\Support\Admin\ListQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Admin → Harbors (PLAN 3.4.1): list, add, edit, image, show / hide, Trash, restore, delete permanently. */
class HarborController extends Controller
{
    private const SORTS = ['sort' => 'sort', 'name' => 'name', 'boats' => 'boats_count', 'updated' => 'updated_at'];

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Harbor::class);

        $list = new ListQuery($request);
        $q = $list->search();
        $inTrash = $list->inTrash();
        [$sort, $column, $dir] = $list->sort(self::SORTS, 'sort');

        $harbors = Harbor::query()
            ->when($inTrash, fn ($query) => $query->onlyTrashed())
            ->withCount('boats')
            ->when($q !== '', fn ($query) => $query->where(fn ($where) => $where
                ->where('name', 'like', '%'.addcslashes($q, '%_\\').'%')
                ->orWhere('area', 'like', '%'.addcslashes($q, '%_\\').'%')))
            ->orderBy($column, $dir)
            ->orderBy('name')
            ->paginate(ListQuery::PER_PAGE)
            ->withQueryString();

        return view('admin.harbors.index', [
            'harbors' => $harbors,
            'q' => $q,
            'inTrash' => $inTrash,
            'sort' => $sort,
            'dir' => $dir,
            'counts' => ['active' => Harbor::query()->count(), 'trash' => Harbor::onlyTrashed()->count()],
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Harbor::class);

        return view('admin.harbors.form', ['harbor' => new Harbor(['is_active' => true, 'sort' => Harbor::withTrashed()->max('sort') + 1])]);
    }

    public function store(HarborRequest $request, RecordWriter $writer): RedirectResponse
    {
        $harbor = new Harbor;
        $writer->save($harbor, $request->harborData(), $request->user(), ['file' => $request->file('image'), 'directory' => 'harbors']);

        return redirect()->route('admin.harbors.edit', $harbor)->with('status', "{$harbor->name} was added.");
    }

    /** Also opens a harbor in the Trash (read-only, with Restore / Delete permanently). */
    public function edit(Harbor $harbor): View
    {
        Gate::authorize('viewAny', Harbor::class);

        return view('admin.harbors.form', [
            'harbor' => $harbor->loadCount('boats'),
            'trashBlockers' => $harbor->trashed() ? [] : $harbor->trashBlockers(),
            'deleteBlockers' => $harbor->trashed() ? $harbor->permanentDeleteBlockers() : [],
        ]);
    }

    public function update(HarborRequest $request, Harbor $harbor, RecordWriter $writer): RedirectResponse
    {
        $writer->save($harbor, $request->harborData(), $request->user(), ['file' => $request->file('image'), 'remove' => $request->boolean('remove_image'), 'directory' => 'harbors']);

        return redirect()->route('admin.harbors.edit', $harbor)->with('status', 'Changes saved.');
    }

    public function trash(RecordLifecycleRequest $request, Harbor $harbor, TrashService $trash): RedirectResponse
    {
        $trash->trash($harbor, $request->reason(), $request->user());

        return redirect()->route('admin.harbors.index')->with('status', "{$harbor->name} was moved to the Trash. You can restore it from the Trash tab.");
    }

    public function restore(RecordLifecycleRequest $request, Harbor $harbor, TrashService $trash): RedirectResponse
    {
        $trash->restore($harbor, $request->reason(), $request->user());

        return redirect()->route('admin.harbors.edit', $harbor)->with('status', "{$harbor->name} was restored.");
    }

    public function destroy(RecordLifecycleRequest $request, Harbor $harbor, TrashService $trash): RedirectResponse
    {
        $trash->forceDelete($harbor, $request->reason(), $request->user());

        return redirect()->route('admin.harbors.index', ['view' => 'trash'])->with('status', "{$harbor->name} was deleted permanently.");
    }
}
