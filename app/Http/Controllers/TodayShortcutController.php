<?php

namespace App\Http\Controllers;

use App\Support\RoleHome;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TodayShortcutController extends Controller
{
    /**
     * Home-screen entry. Sends facility staff to today's register.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        return redirect()->to(app(RoleHome::class)->url($user));
    }
}
