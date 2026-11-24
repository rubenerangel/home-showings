<?php

namespace Tests\Feature\Scheduling;

use App\Enums\ShowingStatus;
use App\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Criterion 14, rule R7. */
class ClosingWindowTest extends TestCase
{
    use SchedulingScenario;

    #[DataProvider('moments')]
    public function test_closing_is_refused_while_a_showing_is_active(string $time, bool $allowed): void
    {
        $this->showing('10:00', '10:30', $this->buyer);
        $this->at("2027-03-10 $time");

        $response = $this->close()->assertRedirect();

        $this->window->refresh();
        if ($allowed) {
            $response->assertSessionHasNoErrors();
            $this->assertTrue($this->window->closed_at->equalTo(now()));
        } else {
            $response->assertSessionHasErrors(['refusal' => 'This window has a showing that has not ended. Cancel it first.']);
            $this->assertNull($this->window->closed_at);
        }
    }

    /** @return array<string, array{string, bool}> */
    public static function moments(): array
    {
        return [
            'before it starts' => ['09:00:00', false],
            'while it runs' => ['10:15:00', false],
            'when it ends' => ['10:30:00', true],
        ];
    }

    public function test_cancelled_showings_and_showings_of_other_windows_do_not_prevent_closing(): void
    {
        $this->showing('10:00', '10:30', status: ShowingStatus::CancelledByBuyer);
        $this->showing('10:30', '11:00', status: ShowingStatus::CancelledBySeller);
        $other = $this->window('14:00', '15:00');
        $this->showing('14:00', '14:30', window: $other);

        $this->close()->assertRedirect()->assertSessionHasNoErrors();

        $this->assertNotNull($this->window->refresh()->closed_at);
        $this->assertNull($other->refresh()->closed_at);
    }

    public function test_a_window_is_closed_only_once(): void
    {
        $this->window->update(['closed_at' => now()]);
        $closedAt = $this->window->refresh()->closed_at;
        $this->travel(1)->minutes();

        $this->close()->assertSessionHasErrors(['refusal' => 'This window is already closed.']);

        $this->assertTrue($closedAt->equalTo($this->window->refresh()->closed_at));
    }

    private function close()
    {
        return $this->actingAs(User::findOrFail($this->seller->id))->post("/seller/windows/{$this->window->id}/close");
    }
}
