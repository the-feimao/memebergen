<?php

namespace App\Enums;

enum MembershipBatchRowStatus: string
{
    case Valid = 'valid';
    case Warning = 'warning';
    case Invalid = 'invalid';
    case Processed = 'processed';
}
