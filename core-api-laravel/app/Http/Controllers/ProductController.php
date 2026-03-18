<?php

namespace App\Http\Controllers;

use App\Exceptions\ExternalServiceException;
use App\Helpers\ApiResponse;
use App\Services\SellerService;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(private SellerService $sellerService)
    {
    }

    public function index(Request $request)
    {
        try {
            return ApiResponse::success(
                $this->sellerService->listProducts($request->search)
            );
        } catch (ExternalServiceException $exception) {
            return ApiResponse::error($exception->getMessage(), $exception->status());
        }
    }

    public function show($id)
    {
        try {
            $product = $this->sellerService->getProduct((int) $id);
        } catch (ExternalServiceException $exception) {
            return ApiResponse::error($exception->getMessage(), $exception->status());
        }

        if (! $product) {
            return ApiResponse::error('Product not found', 404);
        }

        return ApiResponse::success($product);
    }

    public function store(Request $request)
    {
        try {
            $product = $this->sellerService->createProduct([
                'name' => $request->name,
                'price' => $request->price,
                'stock' => $request->stock,
            ]);
        } catch (ExternalServiceException $exception) {
            return ApiResponse::error($exception->getMessage(), $exception->status());
        }

        return ApiResponse::success($product, 'OK', 201);
    }

    public function update(Request $request, $id)
    {
        try {
            $product = $this->sellerService->updateProduct((int) $id, [
                'name' => $request->name,
                'price' => $request->price,
                'stock' => $request->stock,
            ]);
        } catch (ExternalServiceException $exception) {
            return ApiResponse::error($exception->getMessage(), $exception->status());
        }

        return ApiResponse::success($product);
    }

    public function destroy($id)
    {
        try {
            $this->sellerService->deleteProduct((int) $id);
        } catch (ExternalServiceException $exception) {
            return ApiResponse::error($exception->getMessage(), $exception->status());
        }

        return ApiResponse::success(null, 'Product deleted');
    }
}
