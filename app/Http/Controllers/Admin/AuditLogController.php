<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Support\Admin\ListQuery;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Admin → Audit log (PLAN §8.5, 3.4.7): every admin action, status change and money action — who, when, what
 * changed (before → after) and why. Read-only: entries can't be edited or deleted (AuditLog refuses).
 */
class AuditLogController extends Controller
{
    /** Filter value => label. "settings" entries have no subject; the rest are morph names. */
    public const AREAS = [
        'boat' => 'Boats', 'harbor' => 'Harbors', 'amenity' => 'Amenities', 'experience' => 'Experiences', 'addon' => 'Add-ons',
        'cancellation_policy' => 'Cancellation policies', 'captain' => 'Captains', 'user' => 'Users', 'settings' => 'Settings',
    ];

    public function index(Request $request): View
    {
        Gate::authorize('view-audit-log');

        $list = new ListQuery($request);
        $q = $list->search();
        $area = $list->pick('area', array_keys(self::AREAS));
        $who = $list->pick('who', ['admin', 'system']);

        $entries = AuditLog::query()
            ->with('actor')
            ->when($area === 'settings', fn ($query) => $query->where('action', 'like', 'settings.%'))
            ->when($area !== null && $area !== 'settings', fn ($query) => $query->where('subject_type', $area))
            ->when($who === 'system', fn ($query) => $query->whereNull('user_id'))
            ->when($who === 'admin', fn ($query) => $query->whereNotNull('user_id'))
            ->when($q !== '', fn ($query) => $query->where(fn ($where) => $where
                ->where('action', 'like', '%'.addcslashes($q, '%_\\').'%')
                ->orWhere('reason', 'like', '%'.addcslashes($q, '%_\\').'%')))
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        return view('admin.system.audit', [
            'entries' => $entries,
            'subjects' => $this->subjectNames($entries->getCollection()),
            'filters' => compact('q', 'area', 'who'),
            'areas' => self::AREAS,
        ]);
    }

    /**
     * "boat:12" => "The Azure" for the entries on this page — also for records in the Trash or deleted since
     * (then the name the entry itself kept, if any).
     *
     * @param  Collection<int, AuditLog>  $entries
     * @return array<string, string>
     */
    private function subjectNames(Collection $entries): array
    {
        $names = [];
        foreach ($entries->whereNotNull('subject_type')->groupBy('subject_type') as $type => $group) {
            $class = Relation::getMorphedModel($type);
            if ($class === null) {
                continue;
            }
            $query = $class::query();
            if (in_array(SoftDeletes::class, class_uses_recursive($class), true)) {
                $query->withTrashed();
            }
            foreach ($query->whereKey($group->pluck('subject_id')->unique()->all())->get() as $model) {
                /** @var Model $model */
                $names[$type.':'.$model->getKey()] = (string) ($model->getAttribute('title') ?? $model->getAttribute('name') ?? $model->getAttribute('email') ?? '');
            }
        }

        return $names;
    }
}
