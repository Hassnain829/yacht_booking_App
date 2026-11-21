<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Demo\DemoAdmin;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Captains, users and the contact inbox. Phase 3/8/9: Eloquent, CaptainRequest, replies by email. */
class PeopleController extends Controller
{
    public function captains(DemoAdmin $admin): View
    {
        return view('admin.people.captains', [
            'captains' => $admin->captains(),
            'licenseTypes' => collect(config('demo_admin.license_types'))->map(fn ($t) => $t['label'])->all(),
        ]);
    }

    public function users(Request $request, DemoAdmin $admin): View
    {
        $role = $request->query('role');
        $role = is_string($role) && in_array($role, ['customer', 'owner', 'admin'], true) ? $role : null;

        return view('admin.people.users', [
            'role' => $role,
            'users' => $admin->users()->when($role, fn ($c) => $c->where('role', $role))->values(),
        ]);
    }

    public function inbox(DemoAdmin $admin): View
    {
        return view('admin.people.inbox', ['messages' => $admin->messages()]);
    }
}
