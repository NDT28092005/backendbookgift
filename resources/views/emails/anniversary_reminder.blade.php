<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nhắc nhở dịp {{ $anniversary->event_name }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .email-container {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 3px solid #FB6376;
        }
        .header h1 {
            color: #5D2A42;
            margin: 0;
            font-size: 24px;
        }
        .content {
            margin: 20px 0;
        }
        .event-info {
            background: linear-gradient(135deg, rgba(251, 99, 118, 0.1), rgba(252, 177, 166, 0.1));
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid #FB6376;
        }
        .event-name {
            font-size: 20px;
            font-weight: bold;
            color: #5D2A42;
            margin-bottom: 10px;
        }
        .event-date {
            font-size: 16px;
            color: #666;
        }
        .reminder-message {
            background: #fff3cd;
            border: 2px solid #ffc107;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .reminder-message h3 {
            color: #856404;
            margin-top: 0;
        }
        .cta-button {
            display: inline-block;
            background: linear-gradient(135deg, #FB6376, #FCB1A6);
            color: white;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 25px;
            margin: 20px 0;
            font-weight: bold;
            text-align: center;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            color: #999;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <h1>🎁 Nhắc nhở dịp đặc biệt</h1>
        </div>
        
        <div class="content">
            <p>Xin chào <strong>{{ $user->name }}</strong> 👋</p>
            
            <div class="event-info">
                <div class="event-name">{{ $anniversary->event_name }}</div>
                <div class="event-date">📅 Ngày: <strong>{{ \Carbon\Carbon::parse($anniversary->event_date)->format('d/m/Y') }}</strong></div>
            </div>

            @if ($daysLeft == 15)
                <div class="reminder-message">
                    <h3>⏰ Còn 15 ngày nữa!</h3>
                    <p>Dịp <strong>{{ $anniversary->event_name }}</strong> của bạn sẽ diễn ra sau <strong>15 ngày</strong>. Đây là thời điểm lý tưởng để bạn:</p>
                    <ul>
                        <li>🎁 Lựa chọn món quà phù hợp</li>
                        <li>📦 Đặt hàng sớm để đảm bảo giao hàng đúng thời gian</li>
                        <li>💝 Chuẩn bị lời chúc ý nghĩa</li>
                    </ul>
                </div>
            @elseif ($daysLeft == 10)
                <div class="reminder-message">
                    <h3>⏰ Còn 10 ngày nữa!</h3>
                    <p>Dịp <strong>{{ $anniversary->event_name }}</strong> của bạn sẽ diễn ra sau <strong>10 ngày</strong>. Hãy nhanh chóng:</p>
                    <ul>
                        <li>🛒 Hoàn tất đơn hàng quà tặng</li>
                        <li>📝 Xác nhận địa chỉ giao hàng</li>
                        <li>🎀 Chọn giấy gói và phụ kiện trang trí</li>
                    </ul>
                </div>
            @endif

            <div style="text-align: center; margin: 30px 0;">
                <a href="https://bebookgift-hugmbshcgaa0b4d6.eastasia-01.azurewebsites.net/products" class="cta-button">
                    🛍️ Xem sản phẩm ngay
                </a>
            </div>
        </div>

        <div class="footer">
            <p>Cảm ơn bạn đã tin tưởng và sử dụng dịch vụ của chúng tôi 💖</p>
            <p>Nếu bạn có bất kỳ câu hỏi nào, vui lòng liên hệ với chúng tôi.</p>
        </div>
    </div>
</body>
</html>
