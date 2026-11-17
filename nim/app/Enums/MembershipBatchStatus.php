<?php

namespace App\Enums;

enum MembershipBatchStatus: string
{
    case Validating = 'validating';
    case ReadyForReview = 'ready_for_review';
    case Failed = 'failed';
    case Processing = 'processing';
    case Completed = 'completed';
    case CompletedWithErrors = 'completed_with_errors';
    case Cancelled = 'cancelled';
}
