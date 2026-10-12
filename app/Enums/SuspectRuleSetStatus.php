<?php

namespace App\Enums;

enum SuspectRuleSetStatus: string
{
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Retired = 'retired';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('entry.suspect_status_draft'),
            self::PendingApproval => __('entry.suspect_status_pending_approval'),
            self::Approved => __('entry.suspect_status_approved'),
            self::Retired => __('entry.suspect_status_retired'),
        };
    }
}
