<?php

namespace Tests\Feature\Seller;

use Inertia\Support\SessionKey;
use Tests\Feature\Scheduling\SchedulingScenario;
use Tests\TestCase;

class ScheduleMessagesTest extends TestCase
{
    use SchedulingScenario;

    public function test_opening_a_window_says_so_and_a_refusal_does_not(): void
    {
        $open = fn () => $this->actingAs($this->seller)->post(
            "/seller/listings/{$this->listing->id}/windows",
            ['date' => '2027-03-10', 'start' => '13:00', 'end' => '14:00'],
        );

        $open()->assertSessionHas(SessionKey::FLASH_DATA, ['message' => 'Window added.']);
        $this->flushSession();
        $open()->assertSessionHasErrors('refusal')->assertSessionMissing(SessionKey::FLASH_DATA);
    }

    public function test_closing_a_window_says_so_and_a_refusal_does_not(): void
    {
        $close = fn () => $this->actingAs($this->seller)->post("/seller/windows/{$this->window->id}/close");

        $close()->assertSessionHas(SessionKey::FLASH_DATA, ['message' => 'Window closed.']);
        $this->flushSession();
        $close()->assertSessionHasErrors('refusal')->assertSessionMissing(SessionKey::FLASH_DATA);
    }

    public function test_cancelling_a_showing_says_so_and_a_refusal_does_not(): void
    {
        $showing = $this->showing('10:00', '10:30');
        $cancel = fn () => $this->actingAs($this->seller)->post("/seller/showings/{$showing->id}/cancel");

        $cancel()->assertSessionHas(SessionKey::FLASH_DATA, ['message' => 'Showing cancelled. The buyer has been notified.']);
        $this->flushSession();
        $cancel()->assertSessionHasErrors('refusal')->assertSessionMissing(SessionKey::FLASH_DATA);
    }
}
