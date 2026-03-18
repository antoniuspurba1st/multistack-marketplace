<?php

namespace App\Console\Commands;

use App\Exceptions\ExternalServiceException;
use App\Models\OutboxEvent;
use App\Services\SellerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessOutboxEvents extends Command
{
    protected $signature = 'outbox:process';

    protected $description = 'Process unhandled outbox events';

    public function handle(SellerService $sellerService): int
    {
        $events = OutboxEvent::whereNull('processed_at')->get();

        foreach ($events as $event) {
            Log::info('processing_outbox_event', $event->payload);

            try {
                match ($event->event_type) {
                    'SellerReservationConfirmRequested' => $sellerService->confirmReservation(
                        (string) $event->payload['reservation_id']
                    ),
                    'SellerReservationReleaseRequested' => $sellerService->releaseReservation(
                        (string) $event->payload['reservation_id']
                    ),
                    default => null,
                };
            } catch (ExternalServiceException $exception) {
                Log::error('outbox_event_processing_failed', [
                    'event_id' => $event->id,
                    'event_type' => $event->event_type,
                    'payload' => $event->payload,
                    'status' => $exception->status(),
                    'message' => $exception->getMessage(),
                ]);

                continue;
            }

            $event->update([
                'processed_at' => now(),
            ]);
        }

        $this->info("Processed {$events->count()} outbox events.");

        return self::SUCCESS;
    }
}
