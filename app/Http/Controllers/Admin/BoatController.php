<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AmenityGroup;
use App\Enums\BlockType;
use App\Enums\BoatStatus;
use App\Enums\BoatType;
use App\Enums\CaptainSource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BoatBlockRequest;
use App\Http\Requests\Admin\BoatBookableRequest;
use App\Http\Requests\Admin\BoatPhotoRequest;
use App\Http\Requests\Admin\BoatPhotosRequest;
use App\Http\Requests\Admin\BoatRequest;
use App\Http\Requests\Admin\BoatStatusRequest;
use App\Http\Requests\Admin\RecordLifecycleRequest;
use App\Http\Resources\Admin\BoatAvailabilityResource;
use App\Models\Amenity;
use App\Models\Boat;
use App\Models\BoatBlock;
use App\Models\CancellationPolicy;
use App\Models\Experience;
use App\Models\Harbor;
use App\Models\Media;
use App\Services\BoatPhotoService;
use App\Services\BoatService;
use App\Services\TrashService;
use App\Support\Admin\ListQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Admin → Boats (PLAN 3.4.3–3.4.4): every boat, house and owner. List with filters, the Bookable switch (JSON),
 * add a house boat, edit any boat (details, pricing, rules, amenities, experiences, opening hours), publish /
 * hide, photos, blocks and the Trash.
 */
class BoatController extends Controller
{
    private const SORTS = ['sort' => 'sort', 'title' => 'title', 'hourly' => 'hourly_rate_cents', 'guests' => 'max_guests', 'updated' => 'updated_at'];

    /** List filter → statuses. */
    private const STATUS_FILTERS = [
        'published' => [BoatStatus::Published],
        'hidden' => [BoatStatus::Paused, BoatStatus::Suspended, BoatStatus::Archived],
        'draft' => [BoatStatus::Draft],
        'review' => [BoatStatus::PendingReview, BoatStatus::ChangesRequested, BoatStatus::Approved, BoatStatus::Rejected],
    ];

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Boat::class);

        $list = new ListQuery($request);
        $q = $list->search();
        $inTrash = $list->inTrash();
        $ownership = $list->pick('ownership', ['house', 'owner']);
        $status = $list->pick('status', array_keys(self::STATUS_FILTERS));
        $bookable = $list->pick('bookable', ['yes', 'no']);
        [$sort, $column, $dir] = $list->sort(self::SORTS, 'sort');

        $boats = Boat::query()
            ->when($inTrash, fn ($query) => $query->onlyTrashed())
            ->with(['photos', 'harbor' => fn ($query) => $query->withTrashed(), 'owner'])
            ->when($ownership, fn ($query) => $query->where('ownership', $ownership))
            ->when($status, fn ($query) => $query->whereIn('status', self::STATUS_FILTERS[$status]))
            ->when($bookable, fn ($query) => $query->where('booking_enabled', $bookable === 'yes'))
            ->when($q !== '', fn ($query) => $query->where(fn ($where) => $where
                ->where('title', 'like', '%'.addcslashes($q, '%_\\').'%')
                ->orWhere('category', 'like', '%'.addcslashes($q, '%_\\').'%')))
            ->orderBy($column, $dir)
            ->orderBy('title')
            ->paginate(ListQuery::PER_PAGE)
            ->withQueryString();

        return view('admin.boats.index', [
            'boats' => $boats,
            'filters' => compact('q', 'ownership', 'status', 'bookable'),
            'inTrash' => $inTrash,
            'sort' => $sort,
            'dir' => $dir,
            'counts' => ['active' => Boat::query()->count(), 'trash' => Boat::onlyTrashed()->count()],
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Boat::class);

        return $this->form(new Boat([
            'type' => BoatType::Power,
            'captain_source' => CaptainSource::Platform,
            'max_guests' => 6,
            'min_hours' => 2,
            'crew_count' => 1,
            'reviews_count' => 0,
            'sort' => Boat::withTrashed()->max('sort') + 1,
        ]));
    }

    public function store(BoatRequest $request, BoatService $boats, BoatPhotoService $photos): RedirectResponse
    {
        $boat = new Boat;
        $boats->save($boat, $request->boatData(), $request->relationData(), $request->user(), $request->boolean('publish'));
        $added = $this->addChosenPhotos($request, $boat, $photos);

        return redirect()->route('admin.boats.edit', $boat)->with('status', $added > 0
            ? "{$boat->title} was added with ".trans_choice('{1} 1 photo.|[2,*] :count photos.', $added)
            : "{$boat->title} was added. Now add its photos.");
    }

    /** Also opens a boat in the Trash (read-only, with Restore / Delete permanently). */
    public function edit(Boat $boat): View
    {
        Gate::authorize('viewAny', Boat::class);

        return $this->form($boat->load(['photos', 'owner', 'amenities', 'experiences', 'availabilityRules']));
    }

    public function update(BoatRequest $request, Boat $boat, BoatService $boats, BoatPhotoService $photos): RedirectResponse
    {
        $boats->save($boat, $request->boatData(), $request->relationData(), $request->user());
        $added = $this->addChosenPhotos($request, $boat, $photos);

        return redirect()->route('admin.boats.edit', $boat)->with('status', 'Changes saved.'.($added > 0
            ? ' '.trans_choice('{1} 1 photo added.|[2,*] :count photos added.', $added)
            : ''));
    }

    /** The photos chosen in the form's Photos card go up with the save (one Save for everything). */
    private function addChosenPhotos(BoatRequest $request, Boat $boat, BoatPhotoService $photos): int
    {
        $files = $request->photoFiles();

        return $files === [] ? 0 : $photos->add($boat, $files, $request->user());
    }

    public function status(BoatStatusRequest $request, Boat $boat, BoatService $boats): RedirectResponse
    {
        $boats->setStatus($boat, $request->validated('action'), $request->user(), $request->validated('note'));

        return redirect()->route('admin.boats.edit', $boat)->with('status', $boat->status === BoatStatus::Published
            ? "{$boat->title} is live on the site."
            : "{$boat->title} is hidden from the site.");
    }

    public function bookable(BoatBookableRequest $request, Boat $boat, BoatService $boats): BoatAvailabilityResource
    {
        $boats->setBookable($boat, $request->boolean('booking_enabled'), $request->user(), $request->validated('reason'));

        return new BoatAvailabilityResource($boat);
    }

    public function storePhotos(BoatPhotosRequest $request, Boat $boat, BoatPhotoService $photos): RedirectResponse
    {
        $count = $photos->add($boat, $request->file('photos'), $request->user());

        return redirect()->to(route('admin.boats.edit', $boat).'#photos')->with('status', trans_choice('{1} 1 photo added.|[2,*] :count photos added.', $count));
    }

    public function updatePhoto(BoatPhotoRequest $request, Boat $boat, string $photo, BoatPhotoService $photos): RedirectResponse
    {
        $media = $this->photo($boat, $photo);
        $photos->update($boat, $media, $request->has('alt') ? (string) $request->validated('alt') : null, $request->validated('move'), $request->user());

        return redirect()->to(route('admin.boats.edit', $boat).'#photos')->with('status', 'Photo updated.');
    }

    public function destroyPhoto(Request $request, Boat $boat, string $photo, BoatPhotoService $photos): RedirectResponse
    {
        Gate::authorize('update', $boat);
        $photos->delete($boat, $this->photo($boat, $photo), $request->user());

        return redirect()->to(route('admin.boats.edit', $boat).'#photos')->with('status', 'Photo deleted.');
    }

    public function storeBlock(BoatBlockRequest $request, Boat $boat, BoatService $boats): RedirectResponse
    {
        $boats->addBlock($boat, $request->startsAt(), $request->endsAt(), BlockType::from($request->validated('type')), $request->validated('reason'), $request->user());

        return redirect()->to(route('admin.boats.edit', $boat).'#blocks')->with('status', 'Block added. The boat can\'t be booked during that time.');
    }

    public function destroyBlock(Request $request, Boat $boat, BoatBlock $block, BoatService $boats): RedirectResponse
    {
        Gate::authorize('update', $boat);
        $boats->deleteBlock($boat, $block, $request->user());

        return redirect()->to(route('admin.boats.edit', $boat).'#blocks')->with('status', 'Block removed.');
    }

    public function trash(RecordLifecycleRequest $request, Boat $boat, TrashService $trash): RedirectResponse
    {
        $trash->trash($boat, $request->reason(), $request->user());

        return redirect()->route('admin.boats.index')->with('status', "{$boat->title} was moved to the Trash. You can restore it from the Trash tab.");
    }

    public function restore(RecordLifecycleRequest $request, Boat $boat, TrashService $trash): RedirectResponse
    {
        $trash->restore($boat, $request->reason(), $request->user());

        return redirect()->route('admin.boats.edit', $boat)->with('status', "{$boat->title} was restored.");
    }

    public function destroy(RecordLifecycleRequest $request, Boat $boat, TrashService $trash): RedirectResponse
    {
        $trash->forceDelete($boat, $request->reason(), $request->user());

        return redirect()->route('admin.boats.index', ['view' => 'trash'])->with('status', "{$boat->title} was deleted permanently.");
    }

    /** A photo of this boat only — another boat's photo is a 404. */
    private function photo(Boat $boat, string $uuid): Media
    {
        return $boat->photos()->where('uuid', $uuid)->firstOrFail();
    }

    private function form(Boat $boat): View
    {
        $harbors = Harbor::query()->active()->get();
        if ($boat->harbor_id !== null && ! $harbors->contains('id', $boat->harbor_id)) {
            $current = Harbor::withTrashed()->find($boat->harbor_id);
            $harbors->push($current); // a hidden harbor stays selectable for the boat that already uses it
        }

        return view('admin.boats.form', [
            'boat' => $boat,
            'harbors' => $harbors->filter()->mapWithKeys(fn (Harbor $h) => [$h->slug => $h->name.($h->is_active ? '' : ' (hidden)')])->all(),
            'policies' => CancellationPolicy::query()->where('is_active', true)->orderBy('sort')->get()->mapWithKeys(fn (CancellationPolicy $p) => [$p->slug => $p->name])->all(),
            'amenityGroups' => Amenity::query()->orderBy('sort')->orderBy('name')->get()->groupBy(fn (Amenity $a) => $a->group->value),
            'groupLabels' => collect(AmenityGroup::forms())->mapWithKeys(fn (AmenityGroup $g) => [$g->value => $g->label()])->all(),
            'experiences' => Experience::query()->orderBy('sort')->orderBy('name')->get(),
            'checklist' => $this->checklist($boat),
            'blocks' => $boat->exists ? $boat->blocks()->where('ends_at', '>', now()->subDay())->orderBy('starts_at')->get() : collect(),
            'blockTypes' => collect([BlockType::Maintenance, BlockType::Blackout, BlockType::Weather])->mapWithKeys(fn (BlockType $t) => [$t->value => $t->label()])->all(),
            'trashBlockers' => $boat->exists && ! $boat->trashed() ? $boat->trashBlockers() : [],
            'deleteBlockers' => $boat->trashed() ? $boat->permanentDeleteBlockers() : [],
        ]);
    }

    /**
     * What a boat still needs to look right on the site (shown beside the form, on Add too; 3.7). Nothing here
     * blocks saving; the client sees at a glance what to fill in before publishing. The keys name the rules that
     * resources/js/pages/admin-boat-form.js runs on the form as it is filled in, so the list turns green live
     * (`photos` counts the saved photos + the ones chosen in the form). Keep both in step.
     *
     * @return array{items: array<string, array{0: bool, 1: string, 2: string}>, coi_warning: bool}
     */
    private function checklist(Boat $boat): array
    {
        $minPhotos = (int) config('listing.rules_engine.min_photos');

        return [
            'items' => [
                'description' => [filled($boat->description) && filled($boat->short), 'Description', 'A one-line summary and a full description.'],
                'highlights' => [count($boat->highlights ?? []) >= 3, 'Highlights', 'At least 3 short selling points for the boat page.'],
                'specs' => [$boat->category && $boat->length_ft && $boat->max_guests, 'Type, size and guests', 'Category, length and the guest limit.'],
                'harbor' => [$boat->harbor_id !== null, 'Departure harbor', 'Where guests board.'],
                'price' => [(int) $boat->hourly_rate_cents > 0, 'Hourly price', 'The rate guests pay per hour.'],
                'policy' => [$boat->cancellation_policy_id !== null, 'Cancellation policy', 'Flexible, moderate or strict.'],
                'photos' => [$boat->photos->count() >= $minPhotos, "{$minPhotos}+ photos", 'Clear, bright photos of this boat: outside, deck and cabin.'],
                'experiences' => [$boat->experiences->isNotEmpty(), 'Experiences offered', 'At least one, e.g. Sunset Cruise.'],
                'hours' => [$boat->availabilityRules->contains(fn ($rule) => $rule->openTimes() !== []), 'Start times', 'The days and times trips can depart.'],
            ],
            // A captained charter without a Coast Guard COI may carry at most 6 passengers.
            'coi_warning' => ! $boat->uscg_inspected && $boat->max_guests > 6,
        ];
    }
}
