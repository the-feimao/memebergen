<?php

namespace App\Enums;

enum MembershipBatchType: string
{
    case NewRegistration = 'new_registration';
    case Upgrade = 'upgrade';
}
