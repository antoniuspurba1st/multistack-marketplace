<?php

namespace Tests\Feature;

use App\Models\OutboxEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ProcessOutboxEventsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_process_outbox_events_marks_events_as_processed(): void
    {
        OutboxEvent::create([
            'event_type' => 'OrderCreated',
            'payload' => [
                'order_id' => 1,
                'user_id' => 2,
                'total_price' => 300,
            ],
        ]);

        Log::spy();

        Artisan::call('outbox:process');

        $event = OutboxEvent::firstOrFail();

        $this->assertNotNull($event->processed_at);

        Log::shouldHaveReceived('info')
            ->with('processing_outbox_event', $event->payload)
            ->once();
    }

    public function test_schedule_list_contains_outbox_process_command(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('outbox:process')
            ->assertExitCode(0);
    }
}
