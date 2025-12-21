<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- Basic SEO -->
    <title>{{ $product->name }} - Bloom & Box</title>
    <meta name="description" content="{{ $description }}">
    
    <!-- Open Graph Meta Tags for Facebook -->
    <meta property="og:type" content="product">
    <meta property="og:title" content="{{ $product->name }} - Bloom & Box">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:image" content="{{ $productImage }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="{{ $product->name }}">
    <meta property="og:url" content="{{ request()->url() }}">
    <meta property="og:site_name" content="Bloom & Box">
    <meta property="og:locale" content="vi_VN">
    
    <!-- Product-specific Open Graph tags -->
    <meta property="product:price:amount" content="{{ $product->price }}">
    <meta property="product:price:currency" content="VND">
    <meta property="product:availability" content="in stock">
    
    <!-- Twitter Card Meta Tags -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $product->name }} - Bloom & Box">
    <meta name="twitter:description" content="{{ $description }}">
    <meta name="twitter:image" content="{{ $productImage }}">
    
    <!-- Redirect to frontend after a short delay -->
    <script>
        // Redirect to frontend React app after meta tags are loaded
        setTimeout(function() {
            window.location.href = '{{ $shareUrl }}';
        }, 100);
    </script>
</head>
<body>
    <div style="text-align: center; padding: 50px; font-family: Arial, sans-serif;">
        <h1>{{ $product->name }}</h1>
        <p>{{ $description }}</p>
        <p style="font-size: 24px; color: #FB6376; font-weight: bold;">
            ₫{{ $formattedPrice }}
        </p>
        <p style="color: #666;">
            Đang chuyển hướng...
        </p>
        <p>
            <a href="{{ $shareUrl }}" style="color: #1877F2; text-decoration: none;">
                Nhấn vào đây nếu không tự động chuyển hướng
            </a>
        </p>
    </div>
</body>
</html>

