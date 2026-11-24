<?php

namespace App\Scheduling;

use App\Enums\Role;
use App\Enums\ShowingEvent;
use App\Enums\ShowingStatus;
use App\Models\Showing;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Cancels a confirmed showing that has not started, once (R8). Its own row is enough to lock:
 * a booking that still reads it as confirmed is refused, which is the safe answer.
 */
final class CancelShowing
{
    public function handle(Showing $showing, Role $side): void
    {
        DB::transaction(function () use ($showing, $side) {
            $showing = Showing::query()->lockForUpdate()->findOrFail($showing->id);
            $now = CarbonImmutable::now();

            if ($showing->status !== ShowingStatus::Confirmed) {
                throw new Refused(Refusal::ShowingCancelled);
            }
            if ($showing->starts_at <= $now) {
                throw new Refused(Refusal::ShowingStarted);
            }

            $showing->update([
                'status' => $side === Role::Buyer ? ShowingStatus::CancelledByBuyer : ShowingStatus::CancelledBySeller,
                'cancelled_at' => $now,
            ]);

            TellOtherSide::that($side === Role::Buyer ? ShowingEvent::CancelledByBuyer : ShowingEvent::CancelledBySeller, $showing);
        }, attempts: 3);
    }
}
