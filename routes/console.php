<?php

use App\Models\TeamInvitation;
use Illuminate\Support\Facades\Schedule;

Schedule::call(function () {
    TeamInvitation::query()
        ->whereNotNull('expires_at')
        ->where('expires_at', '<', now())
        ->delete();
})->daily()->description('Delete expired team invitations');

Schedule::command('tbai:flag-missed-visits')
    ->dailyAt('06:00')
    ->timezone('Asia/Karachi')
    ->withoutOverlapping()
    ->description('Flag missed treatment follow-up visits and send in-app reminders');
