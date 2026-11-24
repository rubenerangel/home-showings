<?php

namespace App\Enums;

use App\Models\Showing;
use App\Models\User;

enum ShowingEvent: string
{
    case Booked = 'booked';
    case CancelledByBuyer = 'cancelled_by_buyer';
    case CancelledBySeller = 'cancelled_by_seller';

    /** Who did it. */
    public function author(Showing $showing): User
    {
        return $this === self::CancelledBySeller ? $showing->listing->seller : $showing->buyer;
    }

    /** Who has to know. */
    public function recipient(Showing $showing): User
    {
        return $this === self::CancelledBySeller ? $showing->buyer : $showing->listing->seller;
    }
}
