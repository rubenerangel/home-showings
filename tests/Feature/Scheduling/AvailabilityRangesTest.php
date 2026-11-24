<?php

namespace Tests\Feature\Scheduling;

use App\Models\AvailabilityRange;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Criterion 16, rule R12 for buyers. The scenario has one range, 08:00 to 18:00 on 2027-03-10. */
class AvailabilityRangesTest extends TestCase
{
    use SchedulingScenario;

    public function test_a_buyer_adds_and_removes_ranges(): void
    {
        $this->add('2027-03-11', '09:00', '11:00')->assertRedirect()->assertSessionHasNoErrors();

        $range = AvailabilityRange::latest('id')->firstOrFail();
        $this->assertSame($this->buyer->id, $range->buyer_id);
        $this->assertSame('2027-03-11 09:00', $range->local_starts_at->format('Y-m-d H:i'));
        $this->assertSame('2027-03-11 11:00', $range->local_ends_at->format('Y-m-d H:i'));

        $this->actingAs($this->buyer)->delete("/buyer/availability/{$range->id}")->assertRedirect();
        $this->assertModelMissing($range);
    }

    public function test_a_range_is_rejected_once_it_is_over_in_pacific_time(): void
    {
        $this->travelTo(CarbonImmutable::parse('2027-03-11 11:00:00', 'America/Los_Angeles'));
        $this->add('2027-03-11', '09:00', '11:00')->assertSessionHasErrors('end');

        $this->travelTo(CarbonImmutable::parse('2027-03-11 10:59:59', 'America/Los_Angeles'));
        $this->add('2027-03-11', '09:00', '11:00')->assertRedirect()->assertSessionHasNoErrors();
    }

    public function test_past_ranges_are_hidden_and_the_rest_are_ordered(): void
    {
        $this->add('2027-03-12', '09:00', '11:00');
        $this->add('2027-03-11', '09:00', '11:00');
        $this->travelTo(CarbonImmutable::parse('2027-03-10 18:00:00', 'America/Los_Angeles'));

        $this->actingAs($this->buyer)->get('/buyer/availability')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('buyer/Availability')
            ->has('ranges', 2)
            ->where('ranges.0.date', '2027-03-11')
            ->where('ranges.0.start', '09:00')
            ->where('ranges.0.end', '11:00')
            ->where('ranges.1.date', '2027-03-12'));
    }

    /** @param array<string, string> $input */
    #[DataProvider('invalidInput')]
    public function test_malformed_ranges_are_rejected(array $input, string $field): void
    {
        $this->add(...['date' => '2027-03-11', 'start' => '10:00', 'end' => '11:00', ...$input])->assertSessionHasErrors($field);

        $this->assertSame(1, AvailabilityRange::count());
    }

    /** @return array<string, array{array<string, string>, string}> */
    public static function invalidInput(): array
    {
        return [
            'impossible date' => [['date' => '2027-02-30'], 'date'],
            'ends at midnight' => [['end' => '24:00'], 'end'],
            'empty' => [['end' => '10:00'], 'end'],
            'reversed' => [['end' => '09:00'], 'end'],
        ];
    }

    private function add(string $date, string $start, string $end): TestResponse
    {
        return $this->actingAs(User::findOrFail($this->buyer->id))->post('/buyer/availability', compact('date', 'start', 'end'));
    }
}
