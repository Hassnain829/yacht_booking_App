<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ExperienceController as PublicExperienceController;
use App\Http\Requests\Admin\ExperienceRequest;
use App\Http\Requests\Admin\RecordLifecycleRequest;
use App\Models\Experience;
use App\Services\RecordWriter;
use App\Services\TrashService;
use App\Support\Admin\ListQuery;
use App\Support\IconNames;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Admin → Experiences (PLAN 3.4.2): list, add, edit, image, show / hide, featured, Trash. */
class ExperienceController extends Controller
{
    private const SORTS = ['sort' => 'sort', 'name' => 'name', 'fee' => 'package_fee_cents', 'boats' => 'boats_count', 'updated' => 'updated_at'];

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Experience::class);

        $list = new ListQuery($request);
        $q = $list->search();
        $inTrash = $list->inTrash();
        $category = $list->pick('category', array_keys($this->categories()));
        [$sort, $column, $dir] = $list->sort(self::SORTS, 'sort');

        $experiences = Experience::query()
            ->when($inTrash, fn ($query) => $query->onlyTrashed())
            ->withCount('boats')
            ->when($category, fn ($query) => $query->where('category', $category))
            ->when($q !== '', fn ($query) => $query->where(fn ($where) => $where
                ->where('name', 'like', '%'.addcslashes($q, '%_\\').'%')
                ->orWhere('tagline', 'like', '%'.addcslashes($q, '%_\\').'%')))
            ->orderBy($column, $dir)
            ->orderBy('name')
            ->paginate(ListQuery::PER_PAGE)
            ->withQueryString();

        return view('admin.experiences.index', [
            'experiences' => $experiences,
            'q' => $q,
            'category' => $category,
            'categories' => $this->categories(),
            'inTrash' => $inTrash,
            'sort' => $sort,
            'dir' => $dir,
            'counts' => ['active' => Experience::query()->count(), 'trash' => Experience::onlyTrashed()->count()],
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Experience::class);

        return $this->form(new Experience([
            'is_active' => true,
            'is_featured' => false,
            'icon' => 'sunset',
            'category' => 'sightseeing',
            'durations' => [2, 3, 4],
            'time_slots' => [],
            'package_fee_cents' => 0,
            'sort' => Experience::withTrashed()->max('sort') + 1,
        ]));
    }

    public function store(ExperienceRequest $request, RecordWriter $writer): RedirectResponse
    {
        $experience = new Experience;
        $writer->save($experience, $request->experienceData(), $request->user(), ['file' => $request->file('image'), 'directory' => 'experiences']);

        return redirect()->route('admin.experiences.edit', $experience)->with('status', "{$experience->name} was added.");
    }

    /** Also opens an experience in the Trash (read-only, with Restore / Delete permanently). */
    public function edit(Experience $experience): View
    {
        Gate::authorize('viewAny', Experience::class);

        return $this->form($experience->loadCount('boats'));
    }

    public function update(ExperienceRequest $request, Experience $experience, RecordWriter $writer): RedirectResponse
    {
        $writer->save($experience, $request->experienceData(), $request->user(), ['file' => $request->file('image'), 'remove' => $request->boolean('remove_image'), 'directory' => 'experiences']);

        return redirect()->route('admin.experiences.edit', $experience)->with('status', 'Changes saved.');
    }

    public function trash(RecordLifecycleRequest $request, Experience $experience, TrashService $trash): RedirectResponse
    {
        $trash->trash($experience, $request->reason(), $request->user());

        return redirect()->route('admin.experiences.index')->with('status', "{$experience->name} was moved to the Trash. You can restore it from the Trash tab.");
    }

    public function restore(RecordLifecycleRequest $request, Experience $experience, TrashService $trash): RedirectResponse
    {
        $trash->restore($experience, $request->reason(), $request->user());

        return redirect()->route('admin.experiences.edit', $experience)->with('status', "{$experience->name} was restored.");
    }

    public function destroy(RecordLifecycleRequest $request, Experience $experience, TrashService $trash): RedirectResponse
    {
        $trash->forceDelete($experience, $request->reason(), $request->user());

        return redirect()->route('admin.experiences.index', ['view' => 'trash'])->with('status', "{$experience->name} was deleted permanently.");
    }

    private function form(Experience $experience): View
    {
        return view('admin.experiences.form', [
            'experience' => $experience,
            'categories' => $this->categories(),
            'icons' => IconNames::options(),
            'durations' => ExperienceRequest::DURATIONS,
            'trashBlockers' => $experience->exists && ! $experience->trashed() ? $experience->trashBlockers() : [],
            'deleteBlockers' => $experience->trashed() ? $experience->permanentDeleteBlockers() : [],
        ]);
    }

    /** @return array<string, string> */
    private function categories(): array
    {
        return collect(PublicExperienceController::CATEGORIES)->except('all')->all();
    }
}
