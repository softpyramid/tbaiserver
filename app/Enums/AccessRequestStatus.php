<?php

namespace App\Enums;

enum AccessRequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('entry.status_pending'),
            self::Approved => __('entry.status_approved'),
            self::Rejected => __('entry.status_rejected'),
        };
    }
}
