<?php

namespace App\Http\Controllers;

use App\Support\RoleHome;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * The empty dashboard placeholder is gone. Send each role to its home.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        return redirect()->to(app(RoleHome::class)->url($user));
    }
}
