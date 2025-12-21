# Hướng dẫn sửa lỗi 404 và Session cho Product Share Route

## Vấn đề
1. Lỗi 404 khi truy cập `/products/{id}/share`
2. Lỗi `file_put_contents` cho sessions directory

## Giải pháp đã áp dụng

### 1. Route đã được tạo
Route: `GET /products/{id}/share` → `ProductShareController@share`

### 2. Đã loại bỏ Session Middleware
Route này không sử dụng session middleware để tránh lỗi storage.

### 3. Các bước kiểm tra và sửa lỗi

#### Bước 1: Clear cache
```bash
cd backend
php artisan route:clear
php artisan config:clear
php artisan cache:clear
php artisan view:clear
```

#### Bước 2: Kiểm tra route
```bash
php artisan route:list --name=product.share
```

#### Bước 3: Test route trực tiếp
Truy cập: `https://your-backend-url/products/1/share`

#### Bước 4: Kiểm tra bot detection
Route sẽ:
- Serve HTML với meta tags cho Facebook/Messenger crawlers
- Redirect về frontend cho users thông thường

### 4. Nếu vẫn lỗi 404

#### Kiểm tra .htaccess (nếu dùng Apache)
Đảm bảo file `backend/public/.htaccess` có:
```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^(.*)$ public/$1 [L]
</IfModule>
```

#### Kiểm tra web server config
- Nginx: Đảm bảo root point đến `backend/public`
- Apache: Đảm bảo DocumentRoot point đến `backend/public`

### 5. Test với Facebook Debugger
1. Truy cập: https://developers.facebook.com/tools/debug/
2. Nhập URL: `https://your-backend-url/products/1/share`
3. Click "Debug" để xem preview

### 6. Environment Variables
Đảm bảo trong `.env`:
```
APP_URL=https://your-backend-url
FRONTEND_URL=https://your-frontend-url
```

## Lưu ý
- Route này không cần authentication
- Route này không sử dụng session
- Route này tự động detect bot/crawler
- Normal users sẽ được redirect về frontend

