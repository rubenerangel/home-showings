<?php

namespace Tests\Feature\Concurrency;

use App\Enums\HouseTimezone;
use App\Models\AvailabilityRange;
use App\Models\Listing;
use App\Models\Showing;
use App\Models\ShowingWindow;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Real processes against committed rows: no surrounding transaction, and the real clock.
 * The suite runs on its own database server, so nothing else can be affected.
 */
abstract class ConcurrencyTestCase extends TestCase
{
    use DatabaseTruncation;

    private const BARRIER = 4242;

    protected function tearDown(): void
    {
        // A failed test may leave its transaction open, and other tests expect empty tables.
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        DB::statement('TRUNCATE users, listings, showing_windows, availability_ranges, showings, notifications CASCADE');

        parent::tearDown();
    }

    /**
     * Starts every job, waits until all of them are at the barrier, and releases them together.
     *
     * @param  list<array{string, mixed, ...}>  $jobs  action and arguments
     * @return list<string> results, in the order of the jobs
     */
    protected function race(array ...$jobs): array
    {
        DB::select('SELECT pg_advisory_lock(?)', [self::BARRIER]);
        $processes = array_map(fn (array $job) => $this->start($job, self::BARRIER), $jobs);
        $this->waitUntilBlocked($processes);
        DB::select('SELECT pg_advisory_unlock(?)', [self::BARRIER]);

        return $this->results($processes);
    }

    /**
     * Queues the jobs, in order, behind a lock held by this process, and then lets them through.
     *
     * @param  list<array{string, mixed, ...}>  $jobs
     * @return list<string>
     */
    protected function queue(callable $lock, array ...$jobs): array
    {
        return $this->queueUntil(null, $lock, ...$jobs);
    }

    /**
     * The same, holding the lock at least until the given instant.
     *
     * @param  list<array{string, mixed, ...}>  $jobs
     * @return list<string>
     */
    protected function queueUntil(?CarbonImmutable $instant, callable $lock, array ...$jobs): array
    {
        $processes = [];
        DB::beginTransaction();
        $lock();
        foreach ($jobs as $job) {
            $processes[] = $this->start($job);
            $this->waitUntilBlocked($processes);
        }
        if ($instant !== null && $instant->isFuture()) {
            time_sleep_until($instant->getTimestamp() + 0.1);
        }
        DB::commit();

        return $this->results($processes);
    }

    protected function date(): string
    {
        return CarbonImmutable::now()->addDays(7)->format('Y-m-d');
    }

    protected function window(string $start, string $end, ?Listing $listing = null): ShowingWindow
    {
        return ShowingWindow::factory()->for($listing ?? Listing::factory()->create())->between($this->date(), $start, $end)->create();
    }

    protected function buyer(): User
    {
        $buyer = User::factory()->buyer()->create();
        AvailabilityRange::factory()->for($buyer, 'buyer')->between($this->date(), '08:00', '18:00')->create();

        return $buyer;
    }

    /** @return array{string, int, int, string} */
    protected function book(User $buyer, ShowingWindow $window, string $time): array
    {
        return ['book', $buyer->id, $window->id, $this->instant($time)];
    }

    protected function instant(string $time): string
    {
        return CarbonImmutable::parse("{$this->date()} $time", HouseTimezone::Mountain->value)->utc()->toIso8601ZuluString();
    }

    protected function confirmed(): int
    {
        return Showing::where('status', 'confirmed')->count();
    }

    /** @param array{string, mixed, ...} $job */
    private function start(array $job, ?int $barrier = null): Process
    {
        $process = new Process([PHP_BINARY, __DIR__.'/worker.php', json_encode([
            'action' => array_shift($job),
            'arguments' => $job,
            'barrier' => $barrier,
        ])]);
        $process->setTimeout(30)->start();

        return $process;
    }

    /** @param list<Process> $processes */
    private function waitUntilBlocked(array $processes): void
    {
        $deadline = microtime(true) + 20;

        while (DB::scalar('SELECT count(DISTINCT pid) FROM pg_locks WHERE NOT granted') < count($processes)) {
            $stopped = array_any($processes, fn (Process $process) => ! $process->isRunning());
            if ($stopped || microtime(true) > $deadline) {
                $this->fail('Some process never reached the lock: '.implode(' | ', array_map(fn (Process $p) => $p->getOutput().$p->getErrorOutput(), $processes)));
            }
            usleep(20_000);
        }
    }

    /**
     * @param  list<Process>  $processes
     * @return list<string>
     */
    private function results(array $processes): array
    {
        return array_map(function (Process $process) {
            $process->wait();

            return $process->getOutput().$process->getErrorOutput();
        }, $processes);
    }
}
