<?php

namespace App\Http\Controllers;

use App\Models\DailyReturn;
use App\Support\DailyReturnDetail;
use App\Support\DailyReturnListing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DailyReturnAdminController extends Controller
{
    public function index(Request $request, DailyReturnListing $listing): Response|StreamedResponse
    {
        Gate::authorize('administer');

        $user = $request->user();
        abort_unless($user !== null, 403);

        return $listing->respond($request, $user, administration: true);
    }

    public function show(DailyReturn $dailyReturn, DailyReturnDetail $detail): Response
    {
        Gate::authorize('administer');

        return $detail->render($dailyReturn);
    }
}
