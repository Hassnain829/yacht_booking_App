<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PromoAppliesTo;
use App\Enums\PromoType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PromoCodeRequest;
use App\Http\Requests\Admin\RecordLifecycleRequest;
use App\Models\PromoCode;
use App\Services\RecordWriter;
use App\Services\TrashService;
use App\Support\Admin\ListQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Admin → Promo codes (PLAN 4.8): the codes guests type at checkout (PromoService checks them; QuoteService takes
 * the discount off). List, add, edit, show / hide, Trash. Every change is audit-logged (RecordWriter).
 */
class PromoCodeController extends Controller
{
    private const SORTS = ['code' => 'code', 'used' => 'redemptions_count', 'ends' => 'ends_at', 'updated' => 'updated_at'];

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', PromoCode::class);

        $list = new ListQuery($request);
        $q = strtoupper($list->search());
        $inTrash = $list->inTrash();
        [$sort, $column, $dir] = $list->sort(self::SORTS, 'code');

        $promos = PromoCode::query()
            ->when($inTrash, fn ($query) => $query->onlyTrashed())
            ->when($q !== '', fn ($query) => $query->where('code', 'like', '%'.addcslashes($q, '%_\\').'%'))
            ->orderBy($column, $dir)
            ->orderBy('code')
            ->paginate(ListQuery::PER_PAGE)
            ->withQueryString();

        return view('admin.promos.index', [
            'promos' => $promos,
            'q' => $q,
            'inTrash' => $inTrash,
            'sort' => $sort,
            'dir' => $dir,
            'counts' => ['active' => PromoCode::query()->count(), 'trash' => PromoCode::onlyTrashed()->count()],
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', PromoCode::class);

        return $this->form(new PromoCode(['type' => PromoType::Percent, 'applies_to' => PromoAppliesTo::Charter, 'is_active' => true]));
    }

    public function store(PromoCodeRequest $request, RecordWriter $writer): RedirectResponse
    {
        $promo = new PromoCode;
        $writer->save($promo, $request->promoData(), $request->user());

        return redirect()->route('admin.promo-codes.edit', $promo)->with('status', "{$promo->code} was added.");
    }

    public function edit(PromoCode $promo): View
    {
        Gate::authorize('viewAny', PromoCode::class);

        return $this->form($promo);
    }

    public function update(PromoCodeRequest $request, PromoCode $promo, RecordWriter $writer): RedirectResponse
    {
        $writer->save($promo, $request->promoData(), $request->user());

        return redirect()->route('admin.promo-codes.edit', $promo)->with('status', 'Changes saved.');
    }

    public function trash(RecordLifecycleRequest $request, PromoCode $promo, TrashService $trash): RedirectResponse
    {
        $trash->trash($promo, $request->reason(), $request->user());

        return redirect()->route('admin.promo-codes.index')->with('status', "{$promo->code} was moved to the Trash and stopped working. You can restore it from the Trash tab.");
    }

    public function restore(RecordLifecycleRequest $request, PromoCode $promo, TrashService $trash): RedirectResponse
    {
        $trash->restore($promo, $request->reason(), $request->user());

        return redirect()->route('admin.promo-codes.edit', $promo)->with('status', "{$promo->code} was restored.");
    }

    public function destroy(RecordLifecycleRequest $request, PromoCode $promo, TrashService $trash): RedirectResponse
    {
        $trash->forceDelete($promo, $request->reason(), $request->user());

        return redirect()->route('admin.promo-codes.index', ['view' => 'trash'])->with('status', "{$promo->code} was deleted permanently.");
    }

    private function form(PromoCode $promo): View
    {
        return view('admin.promos.form', [
            'promo' => $promo,
            'types' => [PromoType::Percent->value => 'A percentage', PromoType::Fixed->value => 'A fixed amount (USD)'],
            'appliesTo' => [PromoAppliesTo::Charter->value => 'The yacht charter', PromoAppliesTo::Subtotal->value => 'The whole booking (before tax)'],
            'trashBlockers' => $promo->exists && ! $promo->trashed() ? $promo->trashBlockers() : [],
            'deleteBlockers' => $promo->trashed() ? $promo->permanentDeleteBlockers() : [],
        ]);
    }
}
