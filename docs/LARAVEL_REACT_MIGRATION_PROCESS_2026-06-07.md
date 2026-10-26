# Laravel/React Migration Process

Ngày bắt đầu: 2026-06-07

Mục tiêu: thực hiện plan migrate dự án Spring Boot + Vue hiện tại sang Laravel + React trong `GiayOhoho/giayohoho`, đồng thời ghi lại tiến độ theo từng milestone.

## Trạng thái tổng quan

| Milestone | Trạng thái | Ghi chú |
| --- | --- | --- |
| M0 - Process tracking | Done | Tạo file process và bắt đầu ghi log |
| M1 - Laravel schema/migrations | Done | Đã bỏ migration legacy và thay bằng 1 migration canonical cho database PostgreSQL mới |
| M2 - Laravel seeders | Done | Đã thay seeder faker bằng seeders theo `DataSeeder.java`; 100 products/502 variants |
| M3 - Backend API contract | Done with parity pass | Đã port/alias các route chính auth, catalog, cart, order, payment, shipping, profile, admin; bổ sung các endpoint Spring còn thiếu trong audit 2026-06-13 |
| M4 - React shell/frontend | Done | Giữ nguyên UI login/register cũ; thêm API unwrap và role compatibility |
| M5 - Verification | Done with caveat | PHP lint các file đổi, route:list 176 API routes và docker compose config đều pass; chưa build container hoặc migrate live PostgreSQL |
| M6 - Provider integrations | Done | Đã nối PayOS, GHN, Google OAuth và refresh-token DB vào Laravel target |
| M7 - Docker/docs | Done | Đã thêm Dockerfile, docker-compose, entrypoint, `.env.example`, README và PayOS docs |
| M8 - Backend parity audit | Done with caveat | Đã đối chiếu thêm Spring controllers và port tiếp media, review moderation/order-scope, admin account detail/delete/restore, PayOS sync, CSV Excel-compatible admin endpoints |
| M9 - Spring/Vue parity refresh | Done with caveat | Audit lại source đến Flyway V27, hoàn thiện backend contract chính và port các route/page Vue còn thiếu sang React Material UI |
| M10 - 2026-07 parity continuation | Done with caveat | Port tiếp Flyway V28-V30, search preview, session management/admin session routes, monitoring routes và React/MUI header/profile/admin session UI |

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

### 2026-06-13

1. Audit lại backend Laravel target so với Spring Boot hiện tại và xác định migration chưa hoàn tất 100% trước lượt này.
2. Port tiếp các phần còn thiếu:
   - `GET /api/products/media/{slug}/{view}.svg` bằng `Api/ProductMediaController`.
   - `GET /api/media/{path}` bằng `Api/MediaController`.
   - `GET /api/reviews/options`, review theo order đã giao/xong, review mặc định `PENDING`.
   - Admin review moderation: approve, reject, reply.
   - Admin accounts: show, delete soft-delete, restore, lọc role/status/locked/deleted.
   - PayOS sync: `POST /api/payments/payos/sync`.
   - Admin Excel-compatible endpoints cho accounts/products/variants/dashboard. Runtime hiện dùng CSV có BOM UTF-8, mở được bằng Excel, gồm template/export/validate/import cho accounts/products.
3. Dọn SePay runtime cũ; luồng thanh toán migrate hiện dùng PayOS:
   - Xóa route SePay khỏi `routes/web.php`.
   - Xóa controller `Payment/SepayController` và `Payment/SepayCheckoutController`.
   - Xóa config `services.sepay`.
   - Cập nhật Postman full collection từ request SePay sang PayOS init/sync.
4. Sửa migration canonical:
   - `reviews` thêm `order_id`, `status`, `shop_reply`, `moderated_at`, `moderated_by`, indexes và unique partial `(account_id, product_id, order_id)`.
   - Chuyển foreign key `reviews.order_id -> order_details.id` sang bước constraint cuối migration vì `order_details` được tạo sau `reviews`.
5. Verification:
   - `php -l` pass cho migration chính, `ReviewController`, `Admin/UserController`, `Admin/ExportController`, `PaymentController`, `PayOsService`, `routes/api.php`, `routes/web.php`.
   - `composer install --no-interaction --prefer-dist` bị timeout sau 120 giây nhưng đã tải phần lớn packages; `composer dump-autoload` pass và tạo `vendor/autoload.php`.
   - `php artisan route:list --path=api` pass, hiện có 176 API routes; `php artisan route:list` toàn bộ pass với 181 routes.
   - `php artisan migrate --pretend --force --no-interaction` chưa chạy được trên PHP local vì thiếu PDO sqlite driver khi chưa có `.env`; Docker runtime đã cài `pdo_pgsql` và là đường chạy migration chính.
   - `docker compose config` pass.
6. Ghi chú dependency:
   - `composer remove sepay/sepay-pg` đã thử nhưng Composer chặn update do security advisory trên Laravel/PHPUnit đang lock sẵn, sau đó tự revert `composer.json`.
   - Vì vậy package `sepay/sepay-pg` vẫn còn trong Composer metadata, nhưng không còn được route/config/controller runtime sử dụng.

### 2026-06-19

1. Audit lại source canonical Spring Boot + Vue hiện tại:
   - Đối chiếu controller annotations, Vue service URLs và Laravel route list.
   - Đối chiếu Flyway mới nhất đến `V27__order_item_product_snapshot.sql`.
   - Xác nhận backend trước lượt này vẫn còn auth stub, thiếu snapshot order item, thiếu một số route parity và thiếu `SoftDeletes` trên nhiều model.
2. Hoàn thiện backend parity:
   - Thêm bảng `auth_tokens` DB-backed cho reset password và verify email token một lần, có hạn dùng và lưu hash.
   - Thay `forgot-password`, `reset-password`, `resend-verification`, `verify-email` stub bằng luồng thật; ghi/gửi mail qua `mail_outbox`.
   - Thêm cấu hình `AUTH_EMAIL_VERIFICATION_REQUIRED`, frontend URL và TTL token.
   - Port `V26` permissions cho account/product/dashboard Excel vào `RolePermissionSeeder`.
   - Port `V27` snapshot fields vào `order_item`; checkout ghi snapshot product/variant/image và order DTO ưu tiên snapshot.
   - Bổ sung `GET /api/admin/variants`, `GET /api/admin/coupons/{id}`, `DELETE /api/wishlist/items/{productId}`.
   - Bật `SoftDeletes` cho Address, Category, Product, ProductVariant, ProductImage, Wishlist, Coupon và ProductSizeGuide.
   - API route list pass với 179 routes.
3. Migrate frontend Vue sang React Material UI:
   - Thay `/` bằng Home page React, giữ `/products` cho catalog và thêm `/sale`.
   - Thêm auth pages: forgot password, reset password, verify email, setup password.
   - Thêm Profile, Contact, Policies, Chatbot, Order Detail và PayOS Return.
   - Thêm admin pages: Size Guides, Sale Prices, Order Detail và Monitoring.
   - Thêm auth/admin guards, `requiresPasswordSetup` redirect và alias route tiếng Việt.
   - Sửa Product Detail hỗ trợ slug hoặc numeric ID và review theo delivered order.
   - Giữ nguyên giao diện Login/Register React cũ.
4. Verification:
   - `npm ci` pass; npm audit báo 14 vulnerabilities trong dependency tree hiện tại.
   - `npm run build` pass, 710 modules transformed.
   - Vite cảnh báo main chunk khoảng 513 kB; cần code-splitting ở phase tối ưu.
   - PHP lint pass cho auth/order/admin controllers, auth/mail services, migration và seeder vừa thay đổi.
   - HTTP smoke test với APP_KEY/session tạm pass: `/`, `/contact`, `/admin/monitoring` và `/api/health` đều trả 200.
   - Playwright MCP không chạy được do Windows sandbox `CreateProcessAsUserW failed: 5`; chưa có screenshot browser tự động trong lượt này.
   - Chưa chạy `migrate:fresh --seed` trên PostgreSQL container thật.

### 2026-07-07

1. Audit lại source hiện tại sau lần migrate trước:
   - Source Spring/Flyway đã có thêm `V28__product_search_preview_indexes.sql`, `V29__account_session_management.sql`, `V30__product_variant_search_indexes.sql`.
   - Vue frontend vẫn đang dùng `/products/search-preview`, `/auth/sessions/revoke/others`, `/admin/accounts/{accountId}/sessions` và admin monitoring endpoints.
2. Hoàn thiện backend Laravel parity:
   - Cập nhật migration canonical duy nhất với `pg_trgm`, trigram indexes cho products/categories/product_variants.
   - Bổ sung `account_login_events.actor_account_id`, `account_login_events.session_id` và indexes audit theo actor/session.
   - Mở rộng `refresh_tokens` DB-backed với `session_id`, `device_id`, `auth_provider`, `session_created_at`, `last_seen_at`, `rotation_claim_id`.
   - Thêm permission `ACCOUNT_SESSION_VIEW`, `ACCOUNT_SESSION_REVOKE` vào `RolePermissionSeeder`.
   - Thêm `GET /api/products/search-preview`.
   - Sửa auth session semantics: `DELETE /api/auth/sessions/revoke/others` thu hồi phiên khác, `DELETE /api/auth/sessions` thu hồi toàn bộ.
   - Thêm admin account session routes: list, revoke one, revoke all.
   - Thêm admin monitoring routes: `GET /api/admin/monitoring/summary`, `POST /api/admin/monitoring/logql`.
3. Hoàn thiện frontend React/MUI:
   - Thêm `HeaderSearch.jsx` bằng MUI, debounce và gọi search preview giống Vue.
   - Trang Products nhận `?q=` từ header và dùng `ProductCard` MUI có ảnh thật/fallback SVG.
   - Trang Profile thêm khu vực quản lý phiên đăng nhập: xem, revoke một phiên, revoke phiên khác, revoke tất cả.
   - Trang Admin Users chuyển sang MUI table, sửa hiển thị roles dạng string và nối admin session management.
   - Trang Admin Monitoring đọc summary từ backend và có form chạy LogQL.
4. Docker/config:
   - Thêm biến `MONITORING_*` vào `.env.example`, `config/services.php`, `docker-compose.yml`.
5. Verification:
   - `php -l` pass cho các file PHP vừa đổi.
   - `composer install --no-interaction --prefer-dist` pass và tạo `vendor`.
   - `php artisan route:list --path=api` pass, hiện có 186 API routes.
   - Route mới đã xuất hiện: product search preview, auth revoke others, admin account sessions, admin monitoring.
   - `npm ci` pass; npm audit vẫn báo 14 vulnerabilities trong dependency tree hiện tại.
   - `npm run build` pass; Vite vẫn cảnh báo main chunk lớn hơn 500 kB.
   - `docker compose config` pass.
6. Caveat:
   - `php artisan migrate --pretend --force --no-interaction` trên PHP local vẫn không chạy vì chưa có `.env`, Laravel fallback sqlite và PHP local thiếu PDO sqlite driver.
   - Chưa chạy `docker compose up --build` / `php artisan migrate:fresh --seed` trên PostgreSQL container thật trong lượt này.

## Quyết định kỹ thuật

1. Source canonical là dự án hiện tại tại root `F:\WebBanGiayTLU`.
2. Target Laravel/React là `F:\WebBanGiayTLU\GiayOhoho\giayohoho`.
3. UI Login/Register của GiayOhoho cũ được giữ, chỉ đổi logic API/auth.
4. Vì người dùng chọn tạo database mới, không preserve dữ liệu/migration legacy trong Laravel target.
5. PostgreSQL là database mặc định cho Docker/local vì schema dùng partial index và check constraint.

## Việc đang làm

Không còn lệnh đang chạy trong lượt này. Bước xác minh tiếp theo nên là chạy stack Docker thật để build app, tạo PostgreSQL mới, migrate và seed.

## Blockers / rủi ro

1. Chưa chạy `docker compose up --build`; Docker build có thể cần network để tải base image/dependency nếu máy chưa cache sẵn.
2. PayOS/GHN/Google OAuth cần credential thật trong `.env` hoặc biến môi trường compose.
3. GHN khi chưa bật `GHN_ENABLED=true` sẽ dùng fallback local để demo chạy được.
4. Chưa migrate live PostgreSQL trong container ở lượt này; entrypoint sẽ tự chạy migrate/seed khi người dùng chạy compose.
5. Các endpoint admin Excel hiện tương thích dạng CSV, chưa phải XLSX native vì target chưa có package xử lý spreadsheet.
6. Composer package `sepay/sepay-pg` còn trong `composer.json`/`composer.lock` do lệnh remove bị Composer audit chặn bởi advisory của package Laravel/PHPUnit đang lock. Runtime SePay đã được gỡ.
7. Frontend production bundle đang có chunk chính trên 500 kB; build pass nhưng nên lazy-load route trước production.
8. NPM audit hiện báo 14 vulnerabilities; chưa tự động chạy `npm audit fix` để tránh nâng dependency ngoài kiểm soát.
9. PHP local chưa chạy được migration pretend do thiếu `.env` và PDO sqlite; Docker/PostgreSQL là đường chạy migration chính.
