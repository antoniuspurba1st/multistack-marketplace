<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Product;

class Cart extends Model
{
    protected $fillable = [
        'user_id'
    ];

    public function items()
    {
        return $this->hasMany(CartItem::class);
    }
}
