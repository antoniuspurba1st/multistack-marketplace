<?php

namespace App\Http\Controllers;

use App\Exceptions\ExternalServiceException;
use App\Helpers\ApiResponse;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\IdempotencyKey;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OutboxEvent;
use App\Services\RecommendationService;
use App\Services\SellerService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class OrderController extends Controller
{
    public function __construct(private SellerService $sellerService)
    {
    }

    public function checkout(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|integer',
        ]);

        $idempotencyKey = $request->header('Idempotency-Key');

        if ($idempotencyKey) {
            $existing = IdempotencyKey::where('key', $idempotencyKey)->first();

            if ($existing) {
                return ApiResponse::success(
                    $existing->response['data'] ?? null,
                    $existing->response['message'] ?? 'OK'
                );
            }
        }

        $cart = Cart::where('user_id', $validated['user_id'])
            ->with('items')
            ->first();

        if (! $cart || $cart->items->isEmpty()) {
            return ApiResponse::error('Cart not found', 404);
        }

        $checkoutItems = $cart->items
            ->map(fn (CartItem $item) => [
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
            ])
            ->values()
            ->all();

        $reservationRequestId = $idempotencyKey
            ? "checkout:{$idempotencyKey}"
            : (string) Str::uuid();

        try {
            $reservation = $this->sellerService->reserveStock($checkoutItems, $reservationRequestId);

            Log::info('seller_stock_reserved', [
                'user_id' => $validated['user_id'],
                'reservation_id' => $reservation['reservation_id'],
                'reservation_request_id' => $reservationRequestId,
                'payload' => $checkoutItems,
            ]);
        } catch (ExternalServiceException $exception) {
            Log::error('seller_reservation_failed', [
                'user_id' => $validated['user_id'],
                'reservation_request_id' => $reservationRequestId,
                'payload' => $checkoutItems,
                'status' => $exception->status(),
                'message' => $exception->getMessage(),
            ]);

            return ApiResponse::error($exception->getMessage(), $exception->status());
        }

        try {
            $transactionResult = DB::transaction(function () use ($validated, $idempotencyKey, $cart, $reservation) {
                $products = $reservation['products'];

                $total = $cart->items->sum(function (CartItem $item) use ($products) {
                    return ($products[$item->product_id]['price'] ?? 0) * $item->quantity;
                });

                $order = Order::create([
                    'user_id' => $validated['user_id'],
                    'total_price' => $total,
                ]);

                foreach ($cart->items as $item) {
                    $product = $products[$item->product_id] ?? null;

                    if (! $product) {
                        throw new HttpResponseException(ApiResponse::error('Product not found', 404));
                    }

                    OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $item->product_id,
                        'quantity' => $item->quantity,
                        'price' => $product['price'],
                    ]);
                }

                OutboxEvent::create([
                    'event_type' => 'OrderCreated',
                    'payload' => [
                        'order_id' => $order->id,
                        'user_id' => $validated['user_id'],
                        'total_price' => $order->total_price,
                        'reservation_id' => $reservation['reservation_id'],
                    ],
                ]);

                $cart->items()->delete();

                $serializedOrder = $this->serializeOrder($order->fresh('items'), $products);

                $responseData = [
                    'message' => 'Checkout successful',
                    'data' => [
                        'order' => $serializedOrder,
                        'recommendations' => [],
                    ],
                ];

                if ($idempotencyKey) {
                    IdempotencyKey::create([
                        'key' => $idempotencyKey,
                        'user_id' => $validated['user_id'],
                        'response' => $responseData,
                    ]);
                }

                return [
                    'order' => $order->fresh('items'),
                    'products' => $products,
                ];
            });
        } catch (HttpResponseException $exception) {
            $this->releaseReservationOrQueueCompensation(
                $reservation['reservation_id'],
                $validated['user_id'],
                $checkoutItems,
                $exception
            );

            throw $exception;
        } catch (Throwable $exception) {
            Log::error('checkout_transaction_failed', [
                'user_id' => $validated['user_id'],
                'reservation_id' => $reservation['reservation_id'],
                'payload' => $checkoutItems,
                'message' => $exception->getMessage(),
            ]);

            $this->releaseReservationOrQueueCompensation(
                $reservation['reservation_id'],
                $validated['user_id'],
                $checkoutItems,
                $exception
            );

            return ApiResponse::error('Checkout failed', 500);
        }

        try {
            $this->sellerService->confirmReservation($reservation['reservation_id']);

            Log::info('seller_reservation_confirmed', [
                'user_id' => $validated['user_id'],
                'order_id' => $transactionResult['order']->id,
                'reservation_id' => $reservation['reservation_id'],
            ]);
        } catch (ExternalServiceException $exception) {
            Log::error('seller_reservation_confirm_failed', [
                'user_id' => $validated['user_id'],
                'order_id' => $transactionResult['order']->id,
                'reservation_id' => $reservation['reservation_id'],
                'status' => $exception->status(),
                'message' => $exception->getMessage(),
            ]);

            $this->queueReservationEvent('SellerReservationConfirmRequested', [
                'reservation_id' => $reservation['reservation_id'],
                'order_id' => $transactionResult['order']->id,
                'user_id' => $validated['user_id'],
            ]);
        }

        $recommendations = app(RecommendationService::class)
            ->getRecommendations($validated['user_id']);

        $responseData = [
            'message' => 'Checkout successful',
            'data' => [
                'order' => $this->serializeOrder($transactionResult['order'], $transactionResult['products']),
                'recommendations' => $recommendations,
            ],
        ];

        if ($idempotencyKey) {
            IdempotencyKey::where('key', $idempotencyKey)
                ->update(['response' => $responseData]);
        }

        Log::info('checkout_completed', [
            'order_id' => $transactionResult['order']->id,
            'user_id' => $validated['user_id'],
            'reservation_id' => $reservation['reservation_id'],
            'payload' => $checkoutItems,
        ]);

        return ApiResponse::success($responseData['data'], $responseData['message']);
    }

    public function history($user_id)
    {
        $orders = Order::where('user_id', $user_id)
            ->with('items')
            ->latest()
            ->get();

        try {
            $products = $this->sellerService->getProducts(
                $orders
                    ->flatMap(fn (Order $order) => $order->items->pluck('product_id'))
                    ->all()
            );
        } catch (ExternalServiceException $exception) {
            return ApiResponse::error($exception->getMessage(), $exception->status());
        }

        return ApiResponse::success(
            $orders
                ->map(fn (Order $order) => $this->serializeOrder($order, $products))
                ->values()
                ->all()
        );
    }

    private function releaseReservationOrQueueCompensation(
        string $reservationId,
        int $userId,
        array $payload,
        Throwable $exception
    ): void {
        try {
            $this->sellerService->releaseReservation($reservationId);

            Log::warning('seller_reservation_released', [
                'user_id' => $userId,
                'reservation_id' => $reservationId,
                'payload' => $payload,
                'reason' => $exception->getMessage(),
            ]);
        } catch (ExternalServiceException $releaseException) {
            Log::error('seller_reservation_release_failed', [
                'user_id' => $userId,
                'reservation_id' => $reservationId,
                'payload' => $payload,
                'reason' => $exception->getMessage(),
                'status' => $releaseException->status(),
                'message' => $releaseException->getMessage(),
            ]);

            $this->queueReservationEvent('SellerReservationReleaseRequested', [
                'reservation_id' => $reservationId,
                'user_id' => $userId,
                'payload' => $payload,
                'reason' => $exception->getMessage(),
            ]);
        }
    }

    private function queueReservationEvent(string $eventType, array $payload): void
    {
        OutboxEvent::create([
            'event_type' => $eventType,
            'payload' => $payload,
        ]);
    }

    private function serializeOrder(Order $order, array $products): array
    {
        return [
            'id' => $order->id,
            'user_id' => $order->user_id,
            'total_price' => $order->total_price,
            'status' => $order->status,
            'items' => $order->items
                ->map(function (OrderItem $item) use ($products) {
                    return [
                        'id' => $item->id,
                        'order_id' => $item->order_id,
                        'product_id' => $item->product_id,
                        'quantity' => $item->quantity,
                        'price' => $item->price,
                        'product' => $products[$item->product_id] ?? null,
                        'created_at' => $item->created_at,
                        'updated_at' => $item->updated_at,
                    ];
                })
                ->values()
                ->all(),
            'created_at' => $order->created_at,
            'updated_at' => $order->updated_at,
        ];
    }
}
