<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\RecommendationService;

class OrderController extends Controller
{

    public function checkout(Request $request)
{

    $cart = Cart::where('user_id',$request->user_id)
        ->with('items.product')
        ->first();

    if(!$cart){
        return response()->json([
            "message"=>"Cart not found"
        ],404);
    }

    $total = 0;

    foreach($cart->items as $item){
        $total += $item->product->price * $item->quantity;
    }

    $order = Order::create([
        'user_id'=>$request->user_id,
        'total_price'=>$total
    ]);

    foreach($cart->items as $item){

        OrderItem::create([
            'order_id'=>$order->id,
            'product_id'=>$item->product_id,
            'quantity'=>$item->quantity,
            'price'=>$item->product->price
        ]);

        $item->product->decrement('stock',$item->quantity);
    }

    // CALL DJANGO MICROSERVICE
    $recommendationService = new RecommendationService();

    $recommendations = $recommendationService->getRecommendations($request->user_id);

    $cart->items()->delete();

    return response()->json([
        "message"=>"Checkout successful",
        "order"=>$order,
        "recommendations"=>$recommendations
    ]);
}
    public function history($user_id)
{
    $orders = Order::where('user_id',$user_id)
        ->with('items.product')
        ->latest()
        ->get();

    return response()->json($orders);
}

}
