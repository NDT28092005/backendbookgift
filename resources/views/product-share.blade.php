<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- Basic Meta Tags -->
    <title>{{ $product->name }}{{ $priceText ? ' - ' . $priceText : '' }} - Bloom & Box</title>
    <meta name="description" content="{{ $richDescription }}">
    
    <!-- Open Graph Meta Tags (Facebook, Messenger) -->
    <meta property="og:type" content="product">
    <meta property="og:title" content="{{ $product->name }}{{ $priceText ? ' - ' . $priceText : '' }} - Bloom & Box">
    <meta property="og:description" content="{{ $richDescription }}">
    <meta property="og:url" content="{{ $currentUrl }}">
    <meta property="og:site_name" content="Bloom & Box">
    <meta property="og:locale" content="vi_VN">
    
    @if($imageUrl)
    <meta property="og:image" content="{{ $imageUrl }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="1200">
    <meta property="og:image:alt" content="{{ $product->name }}">
    <meta property="og:image:type" content="image/jpeg">
    @endif
    
    <!-- Product-specific Open Graph tags -->
    <meta property="product:price:amount" content="{{ $product->price }}">
    <meta property="product:price:currency" content="VND">
    <meta property="product:availability" content="{{ $product->stock_quantity > 0 ? 'in stock' : 'out of stock' }}">
    
    @if($product->category)
    <meta property="product:category" content="{{ $product->category->name }}">
    @endif
    
    <!-- Twitter Card Meta Tags -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $product->name }}{{ $priceText ? ' - ' . $priceText : '' }} - Bloom & Box">
    <meta name="twitter:description" content="{{ $richDescription }}">
    @if($imageUrl)
    <meta name="twitter:image" content="{{ $imageUrl }}">
    @endif
    
    <!-- Structured Data (JSON-LD) for better SEO -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "Product",
        "name": "{{ $product->name }}",
        "description": "{{ $product->short_description ?: $product->full_description ?: '' }}",
        "image": @if($imageUrl)["{{ $imageUrl }}"]@else[]@endif,
        "sku": "{{ $product->id }}",
        "brand": {
            "@type": "Brand",
            "name": "Bloom & Box"
        },
        "offers": {
            "@type": "Offer",
            "price": "{{ $product->price }}",
            "priceCurrency": "VND",
            "availability": "https://schema.org/{{ $product->stock_quantity > 0 ? 'InStock' : 'OutOfStock' }}",
            "url": "{{ $currentUrl }}"
        }@if($averageRating > 0 && $reviewCount > 0),
        "aggregateRating": {
            "@type": "AggregateRating",
            "ratingValue": "{{ $averageRating }}",
            "reviewCount": "{{ $reviewCount }}",
            "bestRating": "5",
            "worstRating": "1"
        }@endif
        @if($product->category),
        "category": "{{ $product->category->name }}"
        @endif
    }
    </script>
    
    <!-- Redirect to frontend for normal users -->
    <meta http-equiv="refresh" content="0;url={{ $frontendUrl }}">
    <script>
        // Fallback redirect
        window.location.href = "{{ $frontendUrl }}";
    </script>
</head>
<body>
    <div style="text-align: center; padding: 50px; font-family: Arial, sans-serif;">
        <h1>{{ $product->name }}</h1>
        @if($imageUrl)
        <img src="{{ $imageUrl }}" alt="{{ $product->name }}" style="max-width: 600px; height: auto; margin: 20px 0;">
        @endif
        <p>{{ $richDescription }}</p>
        <p style="font-size: 24px; color: #FB6376; font-weight: bold;">{{ $priceText }}</p>
        @if($averageRating > 0 && $reviewCount > 0)
        <p>
            @for($i = 0; $i < round($averageRating); $i++)
                ⭐
            @endfor
            {{ $averageRating }}/5 ({{ $reviewCount }} đánh giá)
        </p>
        @endif
        <p><a href="{{ $frontendUrl }}">Xem sản phẩm →</a></p>
    </div>
</body>
</html>

