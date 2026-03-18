<?php

namespace App\Http\Controllers;

use App\Exceptions\ExternalServiceException;
use App\Helpers\ApiResponse;
use App\Models\Cart;
use App\Models\CartItem;
use App\Services\SellerService;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(private SellerService $sellerService)
    {
    }

    public function add(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|integer',
            'product_id' => 'required|integer',
            'quantity' => 'nullable|integer|min:1',
        ]);

        try {
            $product = $this->sellerService->getProduct($validated['product_id']);
        } catch (ExternalServiceException $exception) {
            return ApiResponse::error($exception->getMessage(), $exception->status());
        }

        if (! $product) {
            return ApiResponse::error('Product not found', 404);
        }

        $cart = Cart::firstOrCreate([
            'user_id' => $validated['user_id'],
        ]);

        $item = CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $validated['product_id'],
            'quantity' => $validated['quantity'] ?? 1,
        ]);

        return ApiResponse::success($this->serializeCartItem($item, $product));
    }

    public function show($user_id)
    {
        $cart = Cart::where('user_id', $user_id)
            ->with('items')
            ->first();

        if (! $cart) {
            return ApiResponse::success(null);
        }

        try {
            $products = $this->sellerService->getProducts($cart->items->pluck('product_id')->all());
        } catch (ExternalServiceException $exception) {
            return ApiResponse::error($exception->getMessage(), $exception->status());
        }

        return ApiResponse::success([
            'id' => $cart->id,
            'user_id' => $cart->user_id,
            'items' => $cart->items
                ->map(fn (CartItem $item) => $this->serializeCartItem($item, $products[$item->product_id] ?? null))
                ->values()
                ->all(),
            'created_at' => $cart->created_at,
            'updated_at' => $cart->updated_at,
        ]);
    }

    public function remove($id)
    {
        $item = CartItem::findOrFail($id);

        $item->delete();

        return ApiResponse::success(null, 'Item removed');
    }

    private function serializeCartItem(CartItem $item, ?array $product): array
    {
        return [
            'id' => $item->id,
            'cart_id' => $item->cart_id,
            'product_id' => $item->product_id,
            'quantity' => $item->quantity,
            'product' => $product,
            'created_at' => $item->created_at,
            'updated_at' => $item->updated_at,
        ];
    }
}
