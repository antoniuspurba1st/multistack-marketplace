<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductImageController;

Route::get('/products', [ProductController::class, 'index']);
Route::post('/products', [ProductController::class, 'store']);
Route::post('/cart/add',[CartController::class,'add']);
Route::get('/cart/{user_id}',[CartController::class,'show']);
Route::post('/checkout',[OrderController::class,'checkout']);
Route::get('/orders/{user_id}',[OrderController::class,'history']);
Route::delete('/cart/item/{id}',[CartController::class,'remove']);
Route::post('/product/upload',[ProductImageController::class,'upload']);
