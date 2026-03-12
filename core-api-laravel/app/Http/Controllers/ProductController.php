<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{

    public function index(Request $request)
{

    $query = Product::with('images');

    if($request->search){
        $query->where('name','like','%'.$request->search.'%');
    }

    $products = $query->paginate(10);

    return ApiResponse::success($products);

}

    public function show($id)
    {
        return ApiResponse::success(Product::findOrFail($id));
    }

    public function store(Request $request)
    {

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric',
            'stock' => 'required|integer'
        ]);

        $product = Product::create($validated);

        return ApiResponse::success($product, 'OK', 201);
    }

}
