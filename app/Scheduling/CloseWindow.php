<?php

namespace App\Scheduling;

use App\Models\ShowingWindow;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/** Closes a window that has no active showing (R7). */
class CloseWindow
{
    public function handle(ShowingWindow $window): void
    {
        DB::transaction(function () use ($window) {
            $window = ShowingWindow::query()->lockForUpdate()->findOrFail($window->id);
            $now = CarbonImmutable::now();

            if ($window->closed_at !== null) {
                throw new Refused(Refusal::WindowClosed);
            }
            if ($window->showings()->active($now)->exists()) {
                throw new Refused(Refusal::WindowHasShowings);
            }

            $window->update(['closed_at' => $now]);
        }, attempts: 3);
    }
}
