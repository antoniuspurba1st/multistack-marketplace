<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ProductImage;

class ProductImageController extends Controller
{
    public function upload(Request $request)
    {

        $request->validate([
            'product_id' => 'required|exists:products,id',
            'image' => 'required|image|max:2048'
        ]);

        $path = $request->file('image')->store('products','public');

        $image = ProductImage::create([
            'product_id' => $request->product_id,
            'image_path' => $path
        ]);

        return response()->json($image);

    }
}
