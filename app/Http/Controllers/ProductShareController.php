<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductReview;
use Illuminate\Http\Request;

class ProductShareController extends Controller
{
    /**
     * Serve HTML page with meta tags for Facebook/Messenger sharing
     * This route is specifically for social media crawlers
     */
    public function share($id)
    {
        $product = Product::with('images', 'category', 'occasion')->find($id);
        
        if (!$product) {
            abort(404, 'Product not found');
        }
        
        // Get reviews for rating calculation
        $reviews = ProductReview::where('product_id', $id)
            ->where('is_blocked', false)
            ->get();
        
        // Calculate average rating
        $averageRating = 0;
        $reviewCount = $reviews->count();
        if ($reviewCount > 0) {
            $sum = $reviews->sum('rating');
            $averageRating = round($sum / $reviewCount, 1);
        }
        
        // Get product image
        $imageUrl = '';
        if ($product->images && $product->images->count() > 0) {
            $imageUrl = $product->images->first()->image_url;
        } else if ($product->image_url) {
            $imageUrl = $product->image_url;
        }
        
        // Ensure image URL is absolute
        if ($imageUrl && !str_starts_with($imageUrl, 'http')) {
            $apiBase = config('app.url');
            $imageUrl = str_starts_with($imageUrl, '/') 
                ? $apiBase . $imageUrl 
                : $apiBase . '/' . $imageUrl;
        }
        
        // Build description
        $description = $product->short_description 
            ?: ($product->full_description 
                ? substr($product->full_description, 0, 150) . '...' 
                : "Khám phá {$product->name} - món quà tặng ý nghĩa và chất lượng cao.");
        
        // Format price
        $formattedPrice = number_format($product->price, 0, ',', '.');
        $priceText = "₫{$formattedPrice}";
        
        // Build rich description
        $descriptionParts = [$description];
        if ($priceText) {
            $descriptionParts[] = "Giá: {$priceText}";
        }
        if ($averageRating > 0 && $reviewCount > 0) {
            $stars = str_repeat('⭐', round($averageRating));
            $descriptionParts[] = "{$stars} {$averageRating}/5 ({$reviewCount} đánh giá)";
        }
        $richDescription = implode(' | ', $descriptionParts);
        
        // Current URL
        $currentUrl = url("/products/{$id}/share");
        
        // Frontend URL for redirect - get from env or use default
        $frontendBaseUrl = env('FRONTEND_URL', 'http://localhost:5173');
        $frontendUrl = rtrim($frontendBaseUrl, '/') . "/products/{$id}";
        
        // Check if it's a bot/crawler
        $userAgent = request()->userAgent() ?? '';
        $isBot = preg_match('/facebookexternalhit|Facebot|Twitterbot|LinkedInBot|WhatsApp|Slackbot|SkypeUriPreview|Applebot|Googlebot|bingbot|YandexBot|Baiduspider|facebook/i', $userAgent);
        
        // Also check for _escaped_fragment_ parameter (used by some crawlers)
        $hasEscapedFragment = request()->has('_escaped_fragment_');
        
        // If it's a bot or has escaped fragment, serve the HTML with meta tags
        // Otherwise, redirect to frontend
        if ($isBot || $hasEscapedFragment) {
            try {
                return view('product-share', compact(
                    'product',
                    'imageUrl',
                    'richDescription',
                    'priceText',
                    'averageRating',
                    'reviewCount',
                    'currentUrl',
                    'frontendUrl'
                ));
            } catch (\Exception $e) {
                // If view fails, return simple HTML with meta tags
                return response()->view('product-share', compact(
                    'product',
                    'imageUrl',
                    'richDescription',
                    'priceText',
                    'averageRating',
                    'reviewCount',
                    'currentUrl',
                    'frontendUrl'
                ), 200)->header('Content-Type', 'text/html');
            }
        }
        
        // Redirect normal users to frontend
        return redirect($frontendUrl, 302);
    }
}

