<?php

// One process of a concurrency test: boots the application, waits at the barrier if there
// is one, runs one action and prints "ok", "refused: <reason>" or "error: <message>".
// A refused window also prints its message, which names the conflict.

use App\Enums\Role;
use App\Models\Listing;
use App\Models\Showing;
use App\Models\ShowingWindow;
use App\Models\User;
use App\Scheduling\BookShowing;
use App\Scheduling\CancelShowing;
use App\Scheduling\CloseWindow;
use App\Scheduling\Interval;
use App\Scheduling\OpenWindow;
use App\Scheduling\Refused;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../../vendor/autoload.php';
(require __DIR__.'/../../../bootstrap/app.php')->make(Kernel::class)->bootstrap();

['action' => $action, 'arguments' => $arguments, 'barrier' => $barrier] = json_decode($argv[1], true);

try {
    // Models are loaded before the barrier, so an action that trusts them works on stale data.
    $run = match ($action) {
        'book' => (function () use ($arguments) {
            $buyer = User::findOrFail($arguments[0]);

            return fn () => app(BookShowing::class)->handle($buyer, $arguments[1], CarbonImmutable::parse($arguments[2]));
        })(),
        'book-home' => (function () use ($arguments) {
            $buyer = User::findOrFail($arguments[0]);
            $listing = Listing::findOrFail($arguments[1]);

            return fn () => app(BookShowing::class)->fromHome($buyer, $listing, $arguments[2], CarbonImmutable::parse($arguments[3]));
        })(),
        'cancel' => (function () use ($arguments) {
            $showing = Showing::findOrFail($arguments[0]);

            return fn () => app(CancelShowing::class)->handle($showing, Role::from($arguments[1]));
        })(),
        'close' => (function () use ($arguments) {
            $window = ShowingWindow::findOrFail($arguments[0]);

            return fn () => app(CloseWindow::class)->handle($window);
        })(),
        'open' => (function () use ($arguments) {
            $listing = Listing::findOrFail($arguments[0]);

            return fn () => app(OpenWindow::class)->handle($listing, Interval::parse($arguments[1], $arguments[2]));
        })(),
    };

    if ($barrier !== null) {
        DB::select('SELECT pg_advisory_lock_shared(?)', [$barrier]);
        DB::select('SELECT pg_advisory_unlock_shared(?)', [$barrier]);
    }

    $run();
    echo 'ok';
} catch (Refused $refused) {
    echo 'refused: '.$refused->reason->value.($action === 'open' ? ' '.$refused->getMessage() : '');
} catch (Throwable $error) {
    echo 'error: '.$error::class.' '.$error->getMessage();
}
