# Laravel/React Migration Process

Ngày bắt đầu: 2026-06-07

Mục tiêu: thực hiện plan migrate dự án Spring Boot + Vue hiện tại sang Laravel + React trong `GiayOhoho/giayohoho`, đồng thời ghi lại tiến độ theo từng milestone.

## Trạng thái tổng quan

| Milestone | Trạng thái | Ghi chú |
| --- | --- | --- |
| M0 - Process tracking | Done | Tạo file process và bắt đầu ghi log |
| M1 - Laravel schema/migrations | Done | Đã bỏ migration legacy và thay bằng 1 migration canonical cho database PostgreSQL mới |
| M2 - Laravel seeders | Done | Đã thay seeder faker bằng seeders theo `DataSeeder.java`; 100 products/502 variants |
| M3 - Backend API contract | Done | Đã port/alias các route chính auth, catalog, cart, order, payment, shipping, profile, admin |
| M4 - React shell/frontend | Done | Giữ nguyên UI login/register cũ; thêm API unwrap và role compatibility |
| M5 - Verification | Done with caveat | PHP lint toàn bộ app/routes/database, route:list, npm build và docker compose config đều pass; chưa build container hoặc migrate live PostgreSQL |
| M6 - Provider integrations | Done | Đã nối PayOS, GHN, Google OAuth và refresh-token DB vào Laravel target |
| M7 - Docker/docs | Done | Đã thêm Dockerfile, docker-compose, entrypoint, `.env.example`, README và PayOS docs |

## Nhật ký thực hiện

### 2026-06-07

1. Tạo file process tracking.
2. Chọn cách triển khai schema bước đầu:
   - Không xóa hàng loạt migration cũ của GiayOhoho trong bước đầu vì worktree đang có nhiều thay đổi sẵn.
   - Thêm migration finalize schema để DB sau khi migrate tiến gần schema canonical từ Flyway hiện tại.
   - Ưu tiên PostgreSQL vì dự án hiện tại dùng PostgreSQL/Flyway với partial indexes, check constraints và `DO $$`.
3. Thêm migration `GiayOhoho/giayohoho/database/migrations/2026_06_07_000001_finalize_ohgiay_schema.php`.
4. Migration đã qua `php -l`.
5. Thay `DatabaseSeeder.php` cũ bằng bộ seeders:
   - `RolePermissionSeeder`
   - `AccountSeeder`
   - `ReferenceCatalogSeeder`
   - `ProductCatalogSeeder`
   - `CustomerActivitySeeder`
6. Sinh `ProductCatalogSeeder.php` từ `DataSeeder.java`: 100 sản phẩm, 502 variants, 100 ảnh.
7. Tất cả seeders đã qua `php -l`.
8. Port model/backend contract:
   - Model `User` trỏ sang bảng `accounts`, các quan hệ dùng `account_id`.
   - Thêm/chuẩn hóa các controller API: auth, profile, address, cart, wishlist, review, order, payment, shipping, contact, policies, chatbot.
   - Thêm admin routes/controllers cho accounts, dashboard, products, images, variants, categories, coupons, orders, shipping và size guides.
   - Giữ alias route cũ như `/api/auth/cart`, `/api/admin/users` để React GiayOhoho hiện tại không bị đứt ngay.
9. Port frontend shell:
   - `resources/js/spa/api.js` unwrap `ApiResponse`.
   - Login/Register/Nav giữ giao diện cũ, đổi logic token/account/role để nhận role string uppercase và wrapper response.
   - Vá `AdminCoupons.jsx` để đọc được mảng canonical hoặc paginator cũ.
10. Verification:
   - `composer install --no-interaction --prefer-dist` hoàn tất.
   - `php artisan route:list --path=api` pass, hiện có 154 API routes.
   - `php -l` toàn bộ `app`, `routes`, `database` pass.
   - `npm ci` hoàn tất; npm audit báo 10 vulnerabilities từ dependency tree hiện tại.
   - `npm run build` pass.
   - `php artisan migrate --pretend --force --no-interaction` chưa chạy được vì PHP runtime hiện tại thiếu PDO sqlite driver.
11. Theo yêu cầu tiếp theo, chuyển hướng sang database mới hoàn toàn:
    - Xóa toàn bộ migration legacy trong `GiayOhoho/giayohoho/database/migrations`.
    - Tạo `2026_06_07_000001_create_ohgiay_schema.php` làm migration canonical duy nhất.
    - Schema mới bao gồm bảng Laravel runtime, `accounts`, RBAC, refresh tokens, catalog, cart/order/payment/shipping và mail outbox.
12. Tích hợp provider/backend thật:
    - `RefreshTokenService`: refresh token opaque lưu hash SHA-256 trong DB, rotate token, revoke session, cookie + CSRF cookie.
    - `GoogleOAuthService`: verify Google ID token qua tokeninfo endpoint, link/create account bằng `google_id`/email.
    - `PayOsService`: ký request bằng HMAC-SHA256, tạo checkout link, verify webhook signature, cập nhật payment/order idempotent.
    - `GhnClient`: gọi GHN master-data, fee/leadtime, store, preview/create/detail/cancel/return/update COD/print token.
13. Cập nhật controller:
    - `AuthController` trả `token`, `refreshToken`, `csrfToken`; `/auth/refresh` rotate token; logout/session revoke refresh token.
    - `PaymentController` tạo PayOS payment link và xử lý webhook đã verify.
    - `ShippingController` dùng GHN khi bật `GHN_ENABLED`, fallback local khi chưa cấu hình.
    - `Api/Admin/ShippingController` gọi GHN thật cho store/preview/create/sync/cancel/return/COD/print.
14. Cập nhật frontend React:
    - Giữ UI login/register cũ.
    - `resources/js/spa/api.js` lưu refresh token và tự gọi `/api/auth/refresh` khi access token hết hạn.
    - Checkout demo đổi lựa chọn SePay sang PayOS và redirect theo `checkoutUrl`.
15. Thêm Docker/docs:
    - `Dockerfile` multi-stage build Composer vendor + Vite assets.
    - `docker-compose.yml` chạy app + PostgreSQL 16 + Mailpit.
    - `docker/entrypoint.sh` chờ DB, migrate, seed và serve app.
    - Cập nhật `.env.example`, `README.md`, `PAYMENTS_SETUP.md`.
16. Verification sau provider/Docker:
    - `php -l` toàn bộ `app`, `routes`, `database` pass.
    - `php artisan route:list --path=api` pass, vẫn có 154 API routes.
    - `npm run build` pass với Vite.
    - `docker compose config` pass.
    - Không chạy `docker compose up --build` trong lượt này để tránh kéo image/dependency và khởi động stack dài hạn trong môi trường làm việc hiện tại.

## Quyết định kỹ thuật

1. Source canonical là dự án hiện tại tại root `F:\WebBanGiayTLU`.
2. Target Laravel/React là `F:\WebBanGiayTLU\GiayOhoho\giayohoho`.
3. UI Login/Register của GiayOhoho cũ được giữ, chỉ đổi logic API/auth.
4. Vì người dùng chọn tạo database mới, không preserve dữ liệu/migration legacy trong Laravel target.
5. PostgreSQL là database mặc định cho Docker/local vì schema dùng partial index và check constraint.

## Việc đang làm

Không còn việc đang chạy trong lượt này.

## Blockers / rủi ro

1. Chưa chạy `docker compose up --build`; Docker build có thể cần network để tải base image/dependency nếu máy chưa cache sẵn.
2. PayOS/GHN/Google OAuth cần credential thật trong `.env` hoặc biến môi trường compose.
3. GHN khi chưa bật `GHN_ENABLED=true` sẽ dùng fallback local để demo chạy được.
4. Chưa migrate live PostgreSQL trong container ở lượt này; entrypoint sẽ tự chạy migrate/seed khi người dùng chạy compose.
