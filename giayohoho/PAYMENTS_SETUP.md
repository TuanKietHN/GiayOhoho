# Cấu hình Thanh toán

## SePay
- Env:
  - `SEPAY_MERCHANT_ID=SP-TEST-TT72AB53`
  - `SEPAY_SECRET_KEY=spsk_test_9Qmb7TMWapSwwkK4vLLgfw2XWw1uNB67`
  - `SEPAY_ENV=sandbox`
- IPN URL: `http://localhost:8000/sepay/ipn`
- Checkout URL: `http://localhost:8000/sepay/checkout?amount=100000&invoice=INV_001&desc=Test`

## VNPAY/Momo/ZaloPay (Sandbox)
- Tạo tài khoản sandbox và lấy `merchantId`, `secretKey`, `appId` tương ứng.
- Tạo các env:
  - `VNPAY_TMN_CODE=...`, `VNPAY_HASH_SECRET=...`
  - `MOMO_PARTNER_CODE=...`, `MOMO_ACCESS_KEY=...`, `MOMO_SECRET_KEY=...`
  - `ZALOPAY_APP_ID=...`, `ZALOPAY_KEY1=...`, `ZALOPAY_KEY2=...`
- Tạo các route callback/IPN tương tự `sepay/ipn` và cập nhật URL trong hệ thống cổng thanh toán.
- UI: hiển thị lựa chọn phương thức thanh toán, tạo QR/redirect tới trang thanh toán của nhà cung cấp.

## Lưu ý bảo mật
- Không commit khóa bí mật lên repo công khai.
- Kiểm tra chữ ký trả về từ nhà cung cấp trước khi cập nhật trạng thái đơn hàng.
- Ghi log IPN và chống replay bằng kiểm tra invoice/timestamps.