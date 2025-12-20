<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Nhắc nhở dịp {{ $anniversary->event_name }}</title>
</head>
<body>
    <h2>Xin chào {{ $user->name }} 👋</h2>
    <p>Chúng tôi muốn nhắc bạn rằng dịp <strong>{{ $anniversary->event_name }}</strong> của bạn sẽ diễn ra vào ngày <strong>{{ \Carbon\Carbon::parse($anniversary->event_date)->format('d/m/Y') }}</strong>.</p>

    @if ($daysLeft == 15)
        <p>📅 Còn <strong>15 ngày</strong> nữa là đến dịp đặc biệt này! Bạn có muốn tìm một món quà ý nghĩa để chuẩn bị không?</p>
    @elseif ($daysLeft == 10)
        <p>🎁 Chỉ còn <strong>10 ngày</strong> nữa thôi! Đây là lúc lý tưởng để bạn chuẩn bị một món quà thật ý nghĩa.</p>
    @elseif ($daysLeft == 7)
        <p>🎁 Chỉ còn 7 ngày nữa thôi! Đây là lúc lý tưởng để bạn chuẩn bị một món quà thật ý nghĩa.</p>
    @elseif ($daysLeft == 1)
        <p>⏰ Ngày mai là dịp đặc biệt của bạn rồi! Đừng quên gửi lời chúc hoặc món quà nhé!</p>
    @else
        <p>Còn <strong>{{ $daysLeft }} ngày</strong> nữa là đến dịp đặc biệt này! Hãy chuẩn bị một món quà thật ý nghĩa nhé!</p>
    @endif

    <p>Cảm ơn bạn đã sử dụng dịch vụ của chúng tôi 💖</p>
</body>
</html>
