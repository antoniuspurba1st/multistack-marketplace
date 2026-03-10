<?php

namespace App\Http\Controllers;
use App\Models\Cart;
use App\Models\CartItem;

use Illuminate\Http\Request;

class CartController extends Controller
{
    public function add(Request $request)
    {

        $cart = Cart::firstOrCreate([
            'user_id' => $request->user_id
        ]);

        $item = CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $request->product_id,
            'quantity' => $request->quantity ?? 1
        ]);

        return response()->json($item);

    }
    public function show($user_id)
{

    $cart = Cart::where('user_id',$user_id)
        ->with('items.product')
        ->first();

    return response()->json($cart);

}


public function remove($id)
{
    $item = CartItem::findOrFail($id);

    $item->delete();

    return response()->json([
        "message"=>"Item removed"
    ]);
}
}
