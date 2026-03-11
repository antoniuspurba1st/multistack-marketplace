<?php

namespace App\Console\Commands;

use App\Models\OutboxEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessOutboxEvents extends Command
{
    protected $signature = 'outbox:process';

    protected $description = 'Process unhandled outbox events';

    public function handle(): int
    {
        $events = OutboxEvent::whereNull('processed_at')->get();

        foreach ($events as $event) {
            Log::info('processing_outbox_event', $event->payload);

            $event->update([
                'processed_at' => now(),
            ]);
        }

        $this->info("Processed {$events->count()} outbox events.");

        return self::SUCCESS;
    }
}
