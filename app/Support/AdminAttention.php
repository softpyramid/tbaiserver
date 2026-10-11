<?php

namespace App\Support;

use App\Enums\AccessRequestStatus;
use App\Enums\FacilityStatus;
use App\Models\AccessRequest;
use App\Models\Facility;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class AdminAttention
{
    /**
     * Things a TB-AI admin should look at first. Null for everyone else.
     *
     * @return array{
     *     pendingRequests: int,
     *     pendingRequestsUrl: string,
     *     facilitiesWithoutReturn: int,
     *     facilitiesWithoutReturnUrl: string,
     *     allClear: bool,
     *     labels: array{heading: string, requests: string, facilities: string, allClear: string}
     * }|null
     */
    public function for(User $user): ?array
    {
        if (! $user->isTbaiAdmin()) {
            return null;
        }

        $pending = AccessRequest::query()
            ->where('status', AccessRequestStatus::Pending)
            ->count();

        $missing = self::facilitiesWithoutReturnToday()->count();

        return [
            'pendingRequests' => $pending,
            'pendingRequestsUrl' => route('admin.access-requests.index'),
            'facilitiesWithoutReturn' => $missing,
            'facilitiesWithoutReturnUrl' => route('facilities.index', ['return_today' => 'missing', 'active' => '1']),
            'allClear' => $pending === 0 && $missing === 0,
            'labels' => [
                'heading' => __('entry.attention_heading'),
                'requests' => trans_choice('entry.attention_requests', $pending),
                'facilities' => trans_choice('entry.attention_facilities', $missing),
                'allClear' => __('entry.attention_all_clear'),
            ],
        ];
    }

    /**
     * Active facilities with no return for today's date in the programme time zone.
     *
     * @return Builder<Facility>
     */
    public static function facilitiesWithoutReturnToday(): Builder
    {
        $today = EntryDate::now()->toDateString();
        $tomorrow = CarbonImmutable::parse($today, EntryDate::timezone())->addDay()->toDateString();

        return Facility::query()
            ->where('status', FacilityStatus::Active)
            ->whereDoesntHave('dailyReturns', fn (Builder $returns) => $returns
                ->where('reported_on', '>=', $today)
                ->where('reported_on', '<', $tomorrow));
    }
}
