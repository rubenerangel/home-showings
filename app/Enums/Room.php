<?php

namespace App\Enums;

enum Room: string
{
    case Exterior = 'exterior';
    case LivingRoom = 'living_room';
    case Kitchen = 'kitchen';
    case Bedroom = 'bedroom';
    case Bathroom = 'bathroom';
    case Other = 'other';
}
