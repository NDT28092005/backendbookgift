<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class ProductShareController extends Controller
{
    /**
     * Render HTML với Open Graph meta tags cho Facebook crawler
     */
    public function show($id)
    {
        $product = Product::with('images', 'category', 'occasion')->find($id);
        
        if (!$product) {
            abort(404);
        }

        // Lấy ảnh sản phẩm
        $productImage = 'https://via.placeholder.com/1200x630?text=Bloom+%26+Box';
        if ($product->images && $product->images->count() > 0) {
            $firstImage = $product->images->first();
            $imageUrl = $firstImage->image_url;
            
            // Đảm bảo URL ảnh là absolute
            if (!preg_match('/^https?:\/\//', $imageUrl)) {
                // Nếu là relative URL, thêm base URL
                $baseUrl = config('app.url');
                $productImage = rtrim($baseUrl, '/') . '/' . ltrim($imageUrl, '/');
            } else {
                $productImage = $imageUrl;
            }
        } elseif ($product->image_url) {
            $imageUrl = $product->image_url;
            if (!preg_match('/^https?:\/\//', $imageUrl)) {
                $baseUrl = config('app.url');
                $productImage = rtrim($baseUrl, '/') . '/' . ltrim($imageUrl, '/');
            } else {
                $productImage = $imageUrl;
            }
        }

        // Lấy mô tả
        $description = $product->short_description 
            ?: $product->full_description 
            ?: "Khám phá {$product->name} - món quà tặng ý nghĩa và chất lượng cao từ Bloom & Box.";

        // Format giá
        $formattedPrice = number_format($product->price, 0, ',', '.');
        
        // Frontend URL - redirect đến React app
        $frontendUrl = 'https://proud-mud-098aeae00.1.azurestaticapps.net';
        $shareUrl = rtrim($frontendUrl, '/') . '/products/' . $id;

        return view('product-share', compact(
            'product',
            'productImage',
            'description',
            'formattedPrice',
            'shareUrl'
        ));
    }
}

