<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    private function rules(): array
    {
        return [
            'user_id' => 'required|exists:users,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric',
            'stock' => 'required|integer|min:0',
        ];
    }

    public function index(Request $request)
    {
        $query = Product::with('images');

        if ($request->search) {
            $query->where('name', 'like', '%'.$request->search.'%');
        }

        $products = $query->latest()->paginate(10);

        return ApiResponse::success($products);
    }

    public function show($id)
    {
        return ApiResponse::success(Product::with('images')->findOrFail($id));
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules());

        $product = Product::create($validated);

        return ApiResponse::success($product, 'OK', 201);
    }

    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);
        $validated = $request->validate($this->rules());

        $product->update($validated);

        return ApiResponse::success($product->load('images'));
    }

    public function destroy($id)
    {
        $product = Product::findOrFail($id);
        $product->delete();

        return ApiResponse::success(null, 'Product deleted');
    }
}
