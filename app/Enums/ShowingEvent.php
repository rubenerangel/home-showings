<?php

namespace App\Enums;

enum ShowingEvent: string
{
    case Booked = 'booked';
    case CancelledByBuyer = 'cancelled_by_buyer';
    case CancelledBySeller = 'cancelled_by_seller';
}
