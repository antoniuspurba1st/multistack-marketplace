<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\IdempotencyKey;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OutboxEvent;
use App\Services\RecommendationService;
use Illuminate\Http\Request;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    public function checkout(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|integer',
        ]);

        $idempotencyKey = $request->header('Idempotency-Key');

        if ($idempotencyKey) {
            $existing = IdempotencyKey::where('key', $idempotencyKey)->first();

            if ($existing) {
                return response()->json($existing->response);
            }
        }

        try {
            $transactionResult = DB::transaction(function () use ($validated, $idempotencyKey) {
                $cart = Cart::where('user_id', $validated['user_id'])
                    ->with('items.product')
                    ->first();

                if (! $cart || $cart->items->isEmpty()) {
                    throw new HttpResponseException(response()->json([
                        'message' => 'Cart not found',
                    ], 404));
                }

                $total = $cart->items->sum(function ($item) {
                    return $item->product->price * $item->quantity;
                });

                $order = Order::create([
                    'user_id' => $validated['user_id'],
                    'total_price' => $total,
                ]);

                foreach ($cart->items as $item) {
                    $updated = DB::table('products')
                        ->where('id', $item->product_id)
                        ->where('stock', '>=', $item->quantity)
                        ->decrement('stock', $item->quantity);

                    if (! $updated) {
                        Log::warning('stock_not_enough', [
                            'product_id' => $item->product_id,
                            'requested' => $item->quantity,
                        ]);

                        throw new \RuntimeException('Stock not enough');
                    }

                    OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $item->product_id,
                        'quantity' => $item->quantity,
                        'price' => $item->product->price,
                    ]);
                }

                OutboxEvent::create([
                    'event_type' => 'OrderCreated',
                    'payload' => [
                        'order_id' => $order->id,
                        'user_id' => $validated['user_id'],
                        'total_price' => $order->total_price,
                    ],
                ]);

                $cart->items()->delete();

                $responseData = [
                    'message' => 'Checkout successful',
                    'order' => $order->fresh('items.product'),
                    'recommendations' => [],
                ];

                if ($idempotencyKey) {
                    IdempotencyKey::create([
                        'key' => $idempotencyKey,
                        'user_id' => $validated['user_id'],
                        'response' => $responseData,
                    ]);
                }

                return [
                    'order' => $order,
                ];
            });
        } catch (HttpResponseException $exception) {
            throw $exception;
        } catch (\RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        $recommendations = app(RecommendationService::class)
            ->getRecommendations($validated['user_id']);

        $responseData = [
            'message' => 'Checkout successful',
            'order' => $transactionResult['order']->fresh('items.product'),
            'recommendations' => $recommendations,
        ];

        if ($idempotencyKey) {
            IdempotencyKey::where('key', $idempotencyKey)
                ->update(['response' => $responseData]);
        }

        Log::info('checkout_completed', [
            'order_id' => $transactionResult['order']->id,
            'user_id' => $validated['user_id'],
        ]);

        return response()->json($responseData);
    }

    public function history($user_id)
    {
        $orders = Order::where('user_id', $user_id)
            ->with('items.product')
            ->latest()
            ->get();

        return response()->json($orders);
    }
}
