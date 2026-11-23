<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LoginEventType;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserStatusRequest;
use App\Http\Requests\Admin\UserTwoFactorResetRequest;
use App\Models\AuditLog;
use App\Models\LoginEvent;
use App\Models\User;
use App\Services\UserAccountService;
use App\Support\Admin\ListQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Admin → Users (PLAN §4.1, 3.4.7): every account with filters; one account's details, login history and
 * repeated failed logins (PLAN §8.5), the audit entries about it, and the actions suspend / ban / re-activate
 * and reset two-factor login — each with a reason + the password (UserAccountService).
 */
class UserController extends Controller
{
    /** Failed logins in the last 24 hours that make the account page show a warning. */
    public const FAILED_LOGIN_ALERT = 5;

    private const SORTS = ['joined' => 'created_at', 'last_login' => 'last_login_at', 'name' => 'first_name'];

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', User::class);

        $list = new ListQuery($request);
        $q = $list->search();
        $role = $list->pick('role', array_map(fn (Role $r) => $r->value, Role::cases()));
        $status = $list->pick('status', array_map(fn (UserStatus $s) => $s->value, UserStatus::cases()));
        [$sort, $column, $dir] = $list->sort(self::SORTS, 'joined', 'desc');

        $users = User::query()
            ->when($role, fn ($query) => $query->where('role', $role))
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($q !== '', fn ($query) => $query->where(fn ($where) => $where
                ->where('email', 'like', '%'.addcslashes($q, '%_\\').'%')
                ->orWhere('first_name', 'like', '%'.addcslashes($q, '%_\\').'%')
                ->orWhere('last_name', 'like', '%'.addcslashes($q, '%_\\').'%')))
            ->orderBy($column, $dir)
            ->orderBy('id', $dir)
            ->paginate(ListQuery::PER_PAGE)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'filters' => compact('q', 'role', 'status'),
            'sort' => $sort,
            'dir' => $dir,
            'roleCounts' => User::query()->toBase()->selectRaw('role, count(*) as total')->groupBy('role')->pluck('total', 'role')->all(),
        ]);
    }

    public function show(Request $request, User $user): View
    {
        Gate::authorize('view', $user);

        return view('admin.users.show', [
            'user' => $user->loadCount('boats'),
            'events' => LoginEvent::query()->where('user_id', $user->getKey())->latest('id')->limit(25)->get(),
            'failedRecently' => LoginEvent::query()->where('user_id', $user->getKey())->where('event', LoginEventType::Failed)->where('created_at', '>=', now()->subDay())->count(),
            'audit' => AuditLog::query()->where('subject_type', $user->getMorphClass())->where('subject_id', $user->getKey())->with('actor')->latest('id')->limit(20)->get(),
            'canChange' => $request->user()->can('changeStatus', $user),
            'statuses' => collect(UserStatus::cases())->reject(fn (UserStatus $s) => $s === $user->status)->mapWithKeys(fn (UserStatus $s) => [$s->value => $s->label()])->all(),
        ]);
    }

    public function status(UserStatusRequest $request, User $user, UserAccountService $accounts): RedirectResponse
    {
        $status = UserStatus::from($request->validated('status'));
        $accounts->setStatus($user, $status, $request->user(), $request->reason());

        return redirect()->route('admin.users.show', $user)->with('status', $status === UserStatus::Active
            ? "{$user->name} can log in again."
            : "{$user->name} is {$status->label()} and was logged out everywhere.");
    }

    public function resetTwoFactor(UserTwoFactorResetRequest $request, User $user, UserAccountService $accounts): RedirectResponse
    {
        $accounts->resetTwoFactor($user, $request->user(), $request->reason());

        return redirect()->route('admin.users.show', $user)->with('status', "Two-factor login was turned off for {$user->name}. They were logged out everywhere and told by email.");
    }
}
