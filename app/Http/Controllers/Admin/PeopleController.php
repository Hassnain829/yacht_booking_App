<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Demo\DemoAdmin;
use Illuminate\View\View;

/** The contact inbox (demo data until Phase 9). Captains and users have their own controllers (3.4.6, 3.4.7). */
class PeopleController extends Controller
{
    public function inbox(DemoAdmin $admin): View
    {
        return view('admin.people.inbox', ['messages' => $admin->messages()]);
    }
}
