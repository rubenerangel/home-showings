<?php

namespace App\Enums;

enum Role: string
{
    case Seller = 'seller';
    case Buyer = 'buyer';

    /** The route where the area of the role starts. */
    public function home(): string
    {
        return "{$this->value}.home";
    }
}
