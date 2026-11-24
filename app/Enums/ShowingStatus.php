<?php

namespace App\Enums;

enum ShowingStatus: string
{
    case Confirmed = 'confirmed';
    case CancelledByBuyer = 'cancelled_by_buyer';
    case CancelledBySeller = 'cancelled_by_seller';
}
