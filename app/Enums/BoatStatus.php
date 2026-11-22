<?php

namespace App\Enums;

/** Where a boat is in the listing workflow (PLAN §6.2, §7). Only Published boats show on the site. */
enum BoatStatus: string
{
    case Draft = 'draft';
    case PendingReview = 'pending_review';
    case ChangesRequested = 'changes_requested';
    case Approved = 'approved';
    case Published = 'published';
    case Paused = 'paused';
    case Suspended = 'suspended';
    case Rejected = 'rejected';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::PendingReview => 'Pending review',
            self::ChangesRequested => 'Changes requested',
            self::Approved => 'Approved',
            self::Published => 'Published',
            self::Paused => 'Paused',
            self::Suspended => 'Suspended',
            self::Rejected => 'Rejected',
            self::Archived => 'Archived',
        };
    }

    /** Shown on the public site (it may still carry the "Unavailable" tag via booking_enabled). */
    public function isListed(): bool
    {
        return $this === self::Published;
    }
}
