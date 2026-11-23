<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LicenseType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CaptainDocumentRequest;
use App\Http\Requests\Admin\CaptainRequest;
use App\Http\Requests\Admin\RecordLifecycleRequest;
use App\Models\Captain;
use App\Services\AuditService;
use App\Services\MediaService;
use App\Services\RecordWriter;
use App\Services\TrashService;
use App\Support\Admin\ListQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Admin → Captains (PLAN §6.4, 3.4.6): captain profiles — photo, contact, license (number encrypted, documents
 * private), passengers allowed, pay rate — and the Trash. Accounts, applications and trip offers: Phase 8.
 */
class CaptainController extends Controller
{
    private const SORTS = ['sort' => 'sort', 'name' => 'name', 'expires' => 'license_expires_on', 'pay' => 'pay_rate_cents'];

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Captain::class);

        $list = new ListQuery($request);
        $q = $list->search();
        $inTrash = $list->inTrash();
        $status = $list->pick('status', ['active', 'inactive']);
        $license = $list->pick('license', ['expiring', 'expired']);
        [$sort, $column, $dir] = $list->sort(self::SORTS, 'sort');

        $captains = Captain::query()
            ->when($inTrash, fn ($query) => $query->onlyTrashed())
            ->when($status, fn ($query) => $query->where('is_active', $status === 'active'))
            ->when($license === 'expired', fn ($query) => $query->whereDate('license_expires_on', '<', today()))
            ->when($license === 'expiring', fn ($query) => $query->whereDate('license_expires_on', '>=', today())->whereDate('license_expires_on', '<=', today()->addDays(30)))
            ->when($q !== '', fn ($query) => $query->where(fn ($where) => $where
                ->where('name', 'like', '%'.addcslashes($q, '%_\\').'%')
                ->orWhere('email', 'like', '%'.addcslashes($q, '%_\\').'%')))
            ->orderBy($column, $dir)
            ->orderBy('name')
            ->paginate(ListQuery::PER_PAGE)
            ->withQueryString();

        return view('admin.captains.index', [
            'captains' => $captains,
            'filters' => compact('q', 'status', 'license'),
            'inTrash' => $inTrash,
            'sort' => $sort,
            'dir' => $dir,
            'counts' => ['active' => Captain::query()->count(), 'trash' => Captain::onlyTrashed()->count()],
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Captain::class);

        return $this->form(new Captain([
            'license_type' => LicenseType::Master,
            'max_passengers_licensed' => 6,
            'pay_rate_cents' => 0,
            'is_active' => true,
            'is_public' => true,
            'sort' => Captain::withTrashed()->max('sort') + 1,
        ]));
    }

    public function store(CaptainRequest $request, RecordWriter $writer): RedirectResponse
    {
        $captain = new Captain;
        $writer->save($captain, $request->captainData(), $request->user(), ['file' => $request->file('photo'), 'column' => 'photo_path', 'directory' => 'captains'], mask: ['license_number']);

        return redirect()->route('admin.captains.edit', $captain)->with('status', "{$captain->name} was added. You can now upload the license.");
    }

    public function edit(Captain $captain): View
    {
        Gate::authorize('viewAny', Captain::class);

        return $this->form($captain->load('documents'));
    }

    public function update(CaptainRequest $request, Captain $captain, RecordWriter $writer): RedirectResponse
    {
        $writer->save($captain, $request->captainData(), $request->user(), ['file' => $request->file('photo'), 'remove' => $request->boolean('remove_photo'), 'column' => 'photo_path', 'directory' => 'captains'], mask: ['license_number']);

        return redirect()->route('admin.captains.edit', $captain)->with('status', 'Changes saved.');
    }

    /** Upload a license document (PDF or image) to the private disk. */
    public function storeDocument(CaptainDocumentRequest $request, Captain $captain, MediaService $media, AuditService $audit): RedirectResponse
    {
        $document = $media->addDocument($captain, 'license', $request->file('document'), $request->user());
        $audit->record('captain.document_added', $captain, [], ['document' => $document->uuid, 'name' => $document->original_name], actor: $request->user());

        return redirect()->to(route('admin.captains.edit', $captain).'#documents')->with('status', 'License document uploaded.');
    }

    public function destroyDocument(Request $request, Captain $captain, string $document, MediaService $media, AuditService $audit): RedirectResponse
    {
        Gate::authorize('update', $captain);
        $file = $captain->documents()->where('uuid', $document)->firstOrFail();

        $media->delete($file);
        $audit->record('captain.document_deleted', $captain, ['document' => $file->uuid, 'name' => $file->original_name], [], actor: $request->user());

        return redirect()->to(route('admin.captains.edit', $captain).'#documents')->with('status', 'Document deleted.');
    }

    public function trash(RecordLifecycleRequest $request, Captain $captain, TrashService $trash): RedirectResponse
    {
        $trash->trash($captain, $request->reason(), $request->user());

        return redirect()->route('admin.captains.index')->with('status', "{$captain->name} was moved to the Trash. You can restore it from the Trash tab.");
    }

    public function restore(RecordLifecycleRequest $request, Captain $captain, TrashService $trash): RedirectResponse
    {
        $trash->restore($captain, $request->reason(), $request->user());

        return redirect()->route('admin.captains.edit', $captain)->with('status', "{$captain->name} was restored.");
    }

    public function destroy(RecordLifecycleRequest $request, Captain $captain, TrashService $trash): RedirectResponse
    {
        $trash->forceDelete($captain, $request->reason(), $request->user());

        return redirect()->route('admin.captains.index', ['view' => 'trash'])->with('status', "{$captain->name} was deleted permanently.");
    }

    private function form(Captain $captain): View
    {
        return view('admin.captains.form', [
            'captain' => $captain,
            'licenseTypes' => collect(LicenseType::cases())->mapWithKeys(fn (LicenseType $t) => [$t->value => $t->label()])->all(),
            'trashBlockers' => $captain->exists && ! $captain->trashed() ? $captain->trashBlockers() : [],
            'deleteBlockers' => $captain->trashed() ? $captain->permanentDeleteBlockers() : [],
        ]);
    }
}
