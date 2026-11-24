<?php

namespace App\Scheduling;

/** Why a scheduling action is refused. The booking reasons are in the order they are checked (R6). */
enum Refusal: string
{
    case NotAvailable = 'not_available';
    case Notice = 'notice';
    case OutsideAvailability = 'outside_availability';
    case AlreadyHasShowing = 'already_has_showing';
    case JustBooked = 'just_booked';
    case Overlaps = 'overlaps';

    case ShowingCancelled = 'showing_cancelled';
    case ShowingStarted = 'showing_started';

    case WindowClosed = 'window_closed';
    case WindowHasShowings = 'window_has_showings';
    case WindowInPast = 'window_in_past';
    case ClockChange = 'clock_change';
    case WindowTooShort = 'window_too_short';
    case WindowOverlaps = 'window_overlaps';

    public function message(): string
    {
        return match ($this) {
            self::NotAvailable => 'This time is no longer available.',
            self::Notice => 'This time is too soon for the notice this home asks for.',
            self::OutsideAvailability => 'This time is outside your availability.',
            self::AlreadyHasShowing => 'You already have a showing at this home.',
            self::JustBooked => 'Someone else just booked this time.',
            self::Overlaps => 'You have another showing at this time.',
            self::ShowingCancelled => 'This showing was already cancelled.',
            self::ShowingStarted => 'This showing has already started.',
            self::WindowClosed => 'This window is already closed.',
            self::WindowHasShowings => 'This window has a showing that has not ended. Cancel it first.',
            self::WindowInPast => 'A window starts in the future.',
            self::ClockChange => 'The clock changes on this date, so windows start at 3:00 AM or later.',
            self::WindowTooShort => 'Nothing fits in this window.',
            self::WindowOverlaps => 'This overlaps another of your windows.',
        };
    }
}
