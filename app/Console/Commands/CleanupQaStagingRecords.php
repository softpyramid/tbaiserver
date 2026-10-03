<?php

namespace App\Console\Commands;

use App\Enums\AccessRequestStatus;
use App\Enums\TeamRole;
use App\Models\AccessRequest;
use App\Models\DailyReturn;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;

class CleanupQaStagingRecords extends Command
{
    protected $signature = 'tbai:cleanup-qa-pass {--force : Delete the matching staging rows}';

    protected $description = 'Delete QA pass daily return 790 and the pending qa-test-request access request on staging, after the rows match.';

    public function handle(): int
    {
        if (! app()->environment('staging')) {
            $this->error('Refused. This command runs only when the application environment is staging.');

            return self::FAILURE;
        }

        $return = DailyReturn::query()->find(790);
        $returnMatches = $return instanceof DailyReturn && $this->returnMatches($return);

        $requests = AccessRequest::query()
            ->where('email', 'qa-test-request@example.invalid')
            ->where('status', AccessRequestStatus::Pending)
            ->get();

        if (! $returnMatches) {
            $this->warn('Daily return 790 was not deleted. It is missing, or it is not a technician return for 4 Oct 2026.');
        } else {
            $this->line('Daily return 790 matches: 4 Oct 2026, technician.');
        }

        if ($requests->isEmpty()) {
            $this->warn('No pending access request for qa-test-request@example.invalid.');
        } else {
            $this->line('Pending access requests to delete: '.$requests->count().'.');
        }

        if (! $this->option('force')) {
            $this->comment('Dry run. Pass --force to delete the rows that matched.');

            return self::SUCCESS;
        }

        if ($returnMatches && $return instanceof DailyReturn) {
            $return->revisions()->delete();
            $return->delete();
            $this->info('Deleted daily return 790.');
        }

        if ($requests->isNotEmpty()) {
            AccessRequest::query()->whereKey($requests->modelKeys())->delete();
            $this->info('Deleted '.$requests->count().' pending access request(s).');
        }

        return self::SUCCESS;
    }

    private function returnMatches(DailyReturn $return): bool
    {
        $reportedOn = $return->reported_on;
        $day = $reportedOn instanceof CarbonInterface
            ? $reportedOn->format('Y-m-d')
            : (string) $reportedOn;

        if ($day !== '2026-10-04') {
            return false;
        }

        $submitter = $return->submitter;

        if (! $submitter instanceof User) {
            return false;
        }

        $return->loadMissing('facility.team');
        $team = $return->facility?->team;

        if ($team === null) {
            return false;
        }

        return $submitter->teamRole($team) === TeamRole::Member;
    }
}
