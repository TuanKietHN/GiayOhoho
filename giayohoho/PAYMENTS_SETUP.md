# PayOS Setup

Thanh toan hien tai dung PayOS. SePay/VNPAY/Momo/ZaloPay khong con la luong chinh trong ban migrate Laravel nay.

## Env bat buoc

```env
PAYOS_API_URL=https://api-merchant.payos.vn/v2/payment-requests
PAYOS_CLIENT_ID=
PAYOS_API_KEY=
PAYOS_CHECKSUM_KEY=
PAYOS_RETURN_URL=http://localhost:8000/orders
PAYOS_CANCEL_URL=http://localhost:8000/orders
PAYOS_EXPIRATION_MINUTES=30
```

## API flow

1. Tao order:

```http
POST /api/orders
Authorization: Bearer <access-token>
```

2. Tao payment link PayOS:

```http
POST /api/payments
Authorization: Bearer <access-token>
Content-Type: application/json

{
  "orderId": 1,
  "provider": "PAYOS",
  "returnUrl": "http://localhost:8000/orders",
  "cancelUrl": "http://localhost:8000/orders"
}
```

Response tra `checkoutUrl`, `paymentLinkId`, `qrCode`; frontend redirect nguoi dung den `checkoutUrl`.

3. Webhook PayOS:

```text
POST http://localhost:8000/api/payments/webhooks/payos
```

Backend verify `signature` bang `PAYOS_CHECKSUM_KEY`, luu event vao `payment_webhook_events`, sau do cap nhat:

- `payment_details.status`
- `order_details.status`
- `payment_events`

## Luu y bao mat

- Khong commit `PAYOS_API_KEY` hoac `PAYOS_CHECKSUM_KEY`.
- Khong cap nhat trang thai thanh toan neu webhook signature sai.
- `payment_webhook_events(provider,event_key)` dung de chong replay/idempotency.
