<?php

namespace Tests\Feature\Push;

use App\Models\Showing;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\MessageSentReport;
use NotificationChannels\WebPush\ReportHandler;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;
use RuntimeException;
use Tests\Feature\Scheduling\SchedulingScenario;
use Tests\TestCase;

/** Criterion 25: the job belongs to the transaction of the action, and the image can encrypt it. */
class PushOutboxTest extends TestCase
{
    use SchedulingScenario;

    public function test_a_failure_after_queueing_undoes_the_job_too(): void
    {
        config(['queue.default' => 'database']);
        $this->seller->updatePushSubscription('https://fcm.googleapis.com/fcm/send/seller', 'key', 'token');
        $this->app['events']->listen(JobQueued::class, fn () => throw new RuntimeException('Failed after queueing.'));

        $this->actingAs($this->buyer)->post('/buyer/showings', [
            'window_id' => $this->window->id,
            'starts_at' => $this->instant('10:00')->toIso8601ZuluString(),
        ])->assertServerError();

        $this->assertSame([0, 0], [Showing::count(), DB::table('jobs')->count()]);
    }

    public function test_a_rejected_push_is_logged_without_its_address(): void
    {
        $log = Log::spy();
        $subscription = $this->seller->updatePushSubscription('https://fcm.googleapis.com/fcm/send/secret', 'key', 'token');
        $report = new MessageSentReport(new Request('POST', $subscription->endpoint), new Response(403), false, "POST {$subscription->endpoint} was forbidden");

        app(ReportHandler::class)->handleReport($report, $subscription, new WebPushMessage);

        $log->shouldHaveReceived('warning')->once()->withArgs(fn (string $message, array $context) => $context === [
            'subscription' => $subscription->id,
            'user' => $this->seller->id,
            'status' => 403,
            'reason' => 'POST [endpoint] was forbidden',
        ]);
    }

    public function test_the_channel_can_be_built_with_the_extensions_of_the_image(): void
    {
        // The library raises a notice, which Laravel turns into an exception, without BCMath or GMP.
        $this->assertInstanceOf(WebPushChannel::class, app(WebPushChannel::class));
        $this->assertTrue(extension_loaded('bcmath') || extension_loaded('gmp'));
    }
}
