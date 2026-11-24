<?php

namespace App\Enums;

enum HouseTimezone: string
{
    case Eastern = 'America/New_York';
    case Central = 'America/Chicago';
    case Mountain = 'America/Denver';
    case Arizona = 'America/Phoenix';
    case Pacific = 'America/Los_Angeles';
}
