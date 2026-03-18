<?php

namespace Tests\Feature;

use App\Models\OutboxEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ProcessOutboxEventsCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.seller.url', 'http://seller.test');
    }

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

    public function test_process_outbox_events_retries_pending_seller_release(): void
    {
        OutboxEvent::create([
            'event_type' => 'SellerReservationReleaseRequested',
            'payload' => [
                'reservation_id' => 'res-outbox',
                'user_id' => 2,
            ],
        ]);

        Http::fake([
            config('services.seller.url').'/products/release' => Http::response([
                'reservation_id' => 'res-outbox',
                'status' => 'released',
            ], 200),
        ]);

        Artisan::call('outbox:process');

        $event = OutboxEvent::firstOrFail();

        $this->assertNotNull($event->processed_at);

        Http::assertSent(fn ($request) => $request->url() === config('services.seller.url').'/products/release');
    }
}
