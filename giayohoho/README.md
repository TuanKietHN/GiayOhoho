# GiayOhoho

Laravel 12 + React 18 SPA cho website ban giay OhGiay/GiayOhoho. Ban migrate hien tai dung database PostgreSQL moi, schema duoc tao bang Laravel migration, seed data duoc chuyen tu `DataSeeder.java`, va frontend React giu nguyen giao dien login/register cu cua GiayOhoho.

## Chay tu dau

Thu muc can dung de chay du an:

```powershell
cd F:\WebBanGiayTLU\GiayOhoho\giayohoho
```

Tat ca lenh Docker, Composer, NPM va Artisan ben duoi deu chay trong thu muc nay.

## Stack hien tai

- Backend: PHP 8.2+, Laravel 12, Laravel Sanctum
- Frontend: React 18, Vite 7, Material UI 5
- Database: PostgreSQL 16
- Tich hop: PayOS, GHN, Google OAuth ID token, refresh token/session management luu DB, monitoring proxy tuy chon
- Docker: `Dockerfile`, `docker-compose.yml`, PostgreSQL, Mailpit

## Chay nhanh bang Docker

```powershell
docker compose up --build
```

Sau khi container len:

- App: `http://localhost:8000`
- API health: `http://localhost:8000/api/health`
- Mailpit: `http://localhost:8025`
- PostgreSQL exposed: `localhost:5432`

Container app se tu dong:

1. Cho PostgreSQL ready.
2. Chay `php artisan migrate --force`.
3. Chay `php artisan db:seed --force` neu `RUN_SEEDERS=true`.
4. Serve Laravel tai port 8000.

Reset database moi hoan toan:

```powershell
docker compose down -v
docker compose up --build
```

## Chay local khong dung Docker

Can cai san PHP 8.2+, Composer, Node.js va PostgreSQL.

```powershell
composer install
npm ci
Copy-Item .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
npm run build
php artisan serve --host=127.0.0.1 --port=8000
```

Neu dung database khac Docker, sua cac bien `DB_*` trong `.env` truoc khi migrate.

## Tai khoan seed

Tat ca tai khoan seed dung mat khau:

```text
123456
```

- Admin: `admin@ohgiay.vn`
- Customer: `tuankiethn@ohgiay.vn`
- Customer: `kiet@ohgiay.vn`

## Database migration

Ban migrate nay khong giu cac migration legacy theo schema cu. Database moi duoc tao tu:

```text
database/migrations/2026_06_07_000001_create_ohgiay_schema.php
```

Migration nay tao cac bang chinh:

- Auth/RBAC: `accounts`, `roles`, `account_roles`, `permissions`, `role_permissions`, `profiles`, `refresh_tokens`, `auth_tokens`, `account_login_events`
- Catalog: `categories`, `products`, `product_variants`, `product_images`, `surfaces`, `tags`
  - Search preview dung trigram indexes PostgreSQL tu `pg_trgm` cho `products`, `categories`, `product_variants`.
- Commerce: `cart`, `cart_item`, `order_details`, `order_item`, `coupons`, `account_coupons`
  - `order_item` luu snapshot product/variant/image de lich su don hang khong bi thay doi khi admin sua hoac soft-delete catalog.
- Providers: `payment_details`, `payment_events`, `payment_webhook_events`, `shipping_orders`, `shipping_events`
- Laravel runtime: `personal_access_tokens`, `sessions`, `cache`, `jobs`, `failed_jobs`

## Provider env

### PayOS

```env
PAYOS_CLIENT_ID=
PAYOS_API_KEY=
PAYOS_CHECKSUM_KEY=
PAYOS_RETURN_URL=http://localhost:8000/orders
PAYOS_CANCEL_URL=http://localhost:8000/orders
```

Webhook URL cau hinh tren PayOS:

```text
http://localhost:8000/api/payments/webhooks/payos
```

### GHN

```env
GHN_ENABLED=true
GHN_BASE_URL=https://dev-online-gateway.ghn.vn
GHN_TOKEN=
GHN_SHOP_ID=
GHN_FROM_NAME=GiayOhoho
GHN_FROM_PHONE=0900000000
GHN_FROM_ADDRESS=Ha Noi
GHN_FROM_DISTRICT_ID=
GHN_FROM_WARD_CODE=
```

Webhook GHN:

```text
http://localhost:8000/api/shipping/ghn/webhooks/order-status
http://localhost:8000/api/shipping/ghn/webhooks/ticket
```

Khi `GHN_ENABLED=false`, app dung fallback local cho province/district/ward/quote de demo va seed chay duoc.

### Google OAuth

```env
GOOGLE_CLIENT_ID=
GOOGLE_TOKENINFO_URL=https://oauth2.googleapis.com/tokeninfo
```

Frontend hoac client gui Google ID token vao:

```text
POST /api/auth/google
{ "idToken": "..." }
```

### Refresh token

```env
REFRESH_TOKEN_TTL_SECONDS=2592000
REFRESH_TOKEN_COOKIE=refresh_token
REFRESH_TOKEN_CSRF_COOKIE=csrf_refresh_token
REFRESH_TOKEN_COOKIE_SECURE=false
REFRESH_TOKEN_COOKIE_SAME_SITE=lax
```

Login/register/google tra ve `token`, `refreshToken`, `csrfToken`. React API client se luu token va tu goi `/api/auth/refresh` khi access token het han.

Quan ly phien dang nhap:

```text
GET    /api/auth/sessions
DELETE /api/auth/sessions/revoke/others
DELETE /api/auth/sessions/{sessionId}
DELETE /api/auth/sessions
GET    /api/admin/accounts/{accountId}/sessions
DELETE /api/admin/accounts/{accountId}/sessions/{sessionId}
DELETE /api/admin/accounts/{accountId}/sessions
```

Password reset va email verification dung token mot lan luu hash trong `auth_tokens`. Cau hinh:

```env
AUTH_EMAIL_VERIFICATION_REQUIRED=false
AUTH_FRONTEND_URL=http://localhost:8000
AUTH_PASSWORD_RESET_TTL_SECONDS=3600
AUTH_EMAIL_VERIFICATION_TTL_SECONDS=86400
```

### Monitoring

Mac dinh monitoring tat de local chay nhe. Neu co Prometheus/Loki/Grafana, bat:

```env
MONITORING_ENABLED=true
MONITORING_PROMETHEUS_URL=http://prometheus:9090
MONITORING_LOKI_URL=http://loki:3100
MONITORING_GRAFANA_URL=http://grafana:3000
MONITORING_TIMEOUT_SECONDS=3
```

Admin routes:

```text
GET  /api/admin/monitoring/summary
POST /api/admin/monitoring/logql
```

## React routes da migrate

- Storefront: `/`, `/products`, `/products/:slugOrId`, `/sale`, `/wishlist`
- Header search da migrate sang React/MUI va dung `GET /api/products/search-preview`.
- Customer: `/cart`, `/checkout`, `/orders`, `/orders/:id`, `/profile`
  - `/profile` co quan ly phien dang nhap/refresh token.
- Auth: `/login`, `/register`, `/forgot-password`, `/reset-password`, `/verify-email`, `/setup-password`
- Support: `/contact`, `/policies`, `/ai-chatbot`
- Payment: `/payment/payos-return`, `/payment/payos-cancel`
- Admin: dashboard, accounts, products, categories, variants, coupons, reviews, orders, order detail, size guides, sale prices, monitoring

Giao dien Login/Register React cu cua GiayOhoho duoc giu nguyen; cac page Vue con lai da duoc chuyen sang React Material UI.

## Lenh kiem tra

```powershell
php artisan route:list --path=api
npm run build
```

PHP lint nhanh:

```powershell
Get-ChildItem app,routes,database -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
```

## API chinh

- Public catalog: `GET /api/products`, `GET /api/products/search-preview`, `GET /api/products/{id}`, `GET /api/categories`
- Auth: `POST /api/auth/register`, `POST /api/auth/login`, `POST /api/auth/refresh`, `POST /api/auth/google`, `GET/DELETE /api/auth/sessions`
- Customer: `GET /api/cart`, `POST /api/orders`, `POST /api/payments`, `POST /api/shipping/quotes`
- Admin: `GET /api/admin/dashboard`, `GET /api/admin/products`, `GET /api/admin/orders`, `GET/DELETE /api/admin/accounts/{accountId}/sessions`, `GET /api/admin/monitoring/summary`
- Providers: `POST /api/payments/webhooks/payos`, `POST /api/shipping/ghn/webhooks/order-status`

Postman collections cu van nam trong root du an, nhung schema/payload canonical la Laravel API hien tai.
