# Hướng dẫn Debug Email Anniversary Reminder

## Các cách debug email

### 1. Sử dụng Artisan Command (Khuyên dùng)

Command này cung cấp nhiều tùy chọn để test và debug:

```bash
# Chạy interactive mode (hiển thị menu)
php artisan anniversary:test-email

# Test một anniversary cụ thể
php artisan anniversary:test-email --anniversary-id=1

# Test một anniversary với force (bỏ qua tracking)
php artisan anniversary:test-email --anniversary-id=1 --force

# Test tất cả anniversaries hợp lệ (15-10 ngày)
php artisan anniversary:test-email --all

# Chạy job SendAnniversaryReminderJob
php artisan anniversary:test-email --run-job
```

### 2. Sử dụng Web Route (Chỉ hoạt động trong môi trường local/testing)

Truy cập qua browser hoặc API client:

```
# Xem danh sách anniversaries
GET /test/anniversary-email

# Test gửi email cho anniversary cụ thể
GET /test/anniversary-email/1
```

### 3. Kiểm tra Logs

Laravel sẽ log các lỗi vào file log. Kiểm tra:
- `storage/logs/laravel.log`

Hoặc xem log real-time:
```bash
tail -f storage/logs/laravel.log
```

### 4. Kiểm tra cấu hình Mail

Kiểm tra file `.env` có các biến sau:

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=your-email@gmail.com
MAIL_FROM_NAME="${APP_NAME}"
```

**Lưu ý với Gmail:**
- Cần dùng App Password, không dùng password thường
- Bật 2-Step Verification trước
- Tạo App Password tại: https://myaccount.google.com/apppasswords

### 5. Test với Mail Driver "log" (Để debug)

Nếu muốn xem email mà không thực sự gửi, đổi trong `.env`:

```env
MAIL_MAILER=log
```

Email sẽ được ghi vào log file thay vì gửi thực tế.

### 6. Kiểm tra Database Tracking

Kiểm tra xem tracking fields đã được cập nhật chưa:

```sql
SELECT 
    anniversary_id,
    event_name,
    event_date,
    reminder_15_days_sent_at,
    reminder_10_days_sent_at,
    user_id
FROM user_anniversaries
WHERE event_date IS NOT NULL;
```

### 7. Chạy Job thủ công

```bash
php artisan anniversary:test-email --run-job
```

Hoặc chạy job trực tiếp trong tinker:

```bash
php artisan tinker
```

```php
$job = new \App\Jobs\SendAnniversaryReminderJob();
$job->handle();
```

### 8. Kiểm tra Queue (nếu dùng queue)

Nếu Job được queue, kiểm tra queue:

```bash
# Xem queue status
php artisan queue:work

# Xem failed jobs
php artisan queue:failed
```

## Các lỗi thường gặp

### 1. "Connection could not be established"

**Nguyên nhân:** Cấu hình SMTP sai hoặc không thể kết nối đến mail server

**Giải pháp:**
- Kiểm tra MAIL_HOST, MAIL_PORT
- Kiểm tra firewall/network
- Thử với mail driver khác (log, mailgun, etc.)

### 2. "Authentication failed"

**Nguyên nhân:** Username/password sai

**Giải pháp:**
- Kiểm tra MAIL_USERNAME và MAIL_PASSWORD
- Với Gmail: đảm bảo dùng App Password
- Kiểm tra có bật "Less secure app access" (nếu cần)

### 3. Email không gửi nhưng không có lỗi

**Nguyên nhân:** 
- Job chạy nhưng logic check không pass
- Tracking fields đã được set (đã gửi rồi)

**Giải pháp:**
- Dùng `--force` flag khi test
- Kiểm tra số ngày còn lại có đúng 15 hoặc 10 không
- Reset tracking fields trong database

### 4. "Job không chạy tự động"

**Nguyên nhân:** Laravel scheduler chưa được setup

**Giải pháp:**
Thêm vào crontab:
```bash
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

## Reset tracking để test lại

Nếu muốn test lại email cho một anniversary:

```sql
UPDATE user_anniversaries 
SET 
    reminder_15_days_sent_at = NULL,
    reminder_10_days_sent_at = NULL
WHERE anniversary_id = ?;
```

Hoặc dùng command với `--force` flag.

## Checklist khi debug

- [ ] Cấu hình mail trong .env đúng
- [ ] Mail driver có thể kết nối (test với command)
- [ ] Anniversary có user hợp lệ (user không null)
- [ ] User có email hợp lệ
- [ ] Số ngày còn lại đúng (15 hoặc 10)
- [ ] Tracking fields chưa được set (hoặc dùng --force)
- [ ] Job có chạy được (test với --run-job)
- [ ] Kiểm tra log file để xem lỗi chi tiết
