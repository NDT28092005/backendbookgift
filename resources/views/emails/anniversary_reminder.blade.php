<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Nhắc nhở dịp {{ $anniversary->event_name }}</title>
</head>
<body>
    <h2>Xin chào {{ $user->name }} 👋</h2>
    <p>Chúng tôi muốn nhắc bạn rằng dịp <strong>{{ $anniversary->event_name }}</strong> của bạn sẽ diễn ra vào ngày <strong>{{ \Carbon\Carbon::parse($anniversary->event_date)->format('d/m/Y') }}</strong>.</p>

    @if ($daysLeft == 7)
        <p>🎁 Chỉ còn 7 ngày nữa thôi! Đây là lúc lý tưởng để bạn chuẩn bị một món quà thật ý nghĩa.</p>
    @elseif ($daysLeft == 1)
        <p>⏰ Ngày mai là dịp đặc biệt của bạn rồi! Đừng quên gửi lời chúc hoặc món quà nhé!</p>
    @endif

    <p>Cảm ơn bạn đã sử dụng dịch vụ của chúng tôi 💖</p>
</body>
</html>
