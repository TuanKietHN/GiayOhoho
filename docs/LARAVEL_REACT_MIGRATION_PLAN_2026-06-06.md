# Plan migrate WebBanGiayTLU hiện tại sang Laravel/React GiayOhoho

Ngày lập: 2026-06-06

## Kết luận khả thi

Có thể migrate toàn bộ dự án hiện tại sang Laravel và thay thế code GiayOhoho hiện tại, nhưng đây là migration lớn, không nên làm kiểu copy file từng phần. Code hiện tại là Spring Boot + Vue/Vite với schema Flyway PostgreSQL, auth JWT/refresh cookie, PayOS, GHN, MinIO, email outbox, chatbot, admin monitoring. GiayOhoho hiện tại là Laravel 12 + Sanctum + React 18/MUI/Vite, có domain shop cơ bản nhưng thiếu nhiều phần mới.

Hướng migration đúng là dùng `GiayOhoho/giayohoho` làm target Laravel/React, đưa trạng thái cuối của Spring/Vue sang target, giữ UI login/register React cũ của GiayOhoho, và thay toàn bộ phần backend/frontend còn lại theo contract hiện tại.

## Nguyên tắc chính

1. Canonical source là dự án hiện tại, không phải GiayOhoho cũ.
2. Schema canonical lấy từ toàn bộ Flyway `src/main/resources/db/migration/V1__...V23__...`.
3. Laravel migrations phải tạo đúng trạng thái cuối của schema, không chỉ convert từng file SQL máy móc.
4. Laravel seeders phải chuyển từ `DataSeeder.java`, không giữ seeder faker 20 sản phẩm của GiayOhoho làm dữ liệu chính.
5. React target dùng cấu trúc React/Vite trong GiayOhoho, nhưng migrate đầy đủ route/page/service từ Vue hiện tại.
6. Login/Register giữ nguyên giao diện GiayOhoho cũ ở mức JSX/layout/MUI; chỉ đổi logic gọi API, validation, redirect, auth state.
7. Nếu phải giữ dữ liệu GiayOhoho cũ đang chạy, cần phase backup và data transform riêng. Nếu được reset DB, migration đơn giản hơn nhiều.

## Phase 0 - Baseline và chốt phạm vi

Việc cần làm:

1. Tạo branch migrate, ví dụ `codex/laravel-react-migration`.
2. Backup toàn bộ `GiayOhoho/giayohoho`, database hiện tại nếu có, `.env`, storage upload.
3. Chốt DB target là PostgreSQL vì schema hiện tại dùng `BIGSERIAL`, partial indexes, `JSONB`, `DO $$`, check constraint.
4. Chốt cách chạy local: Laravel app + Postgres + Redis + MinIO + queue worker + scheduler + Vite.
5. Chốt auth token: ưu tiên giữ API response contract hiện tại để React mới dễ dùng:
   - `ApiResponse<T> { success, message, data, errors, timestamp }`
   - auth data `{ token, csrfToken, account, requiresPasswordSetup }`

Deliverable:

1. `.env.example` Laravel đầy đủ biến DB, Redis, MinIO, PayOS, GHN, mail, Google OAuth, AI/chatbot.
2. Docker compose mới hoặc compose hiện tại được chỉnh cho Laravel.

## Phase 1 - Dọn Laravel target

Target: `GiayOhoho/giayohoho`.

Việc cần làm:

1. Giữ Laravel 12, PHP 8.2, Vite, React, MUI nếu không có lý do đổi.
2. Cài thêm package cần thiết:
   - HTTP client đã có trong Laravel qua `Http` facade.
   - Redis/cache/queue dùng driver Laravel.
   - MinIO/S3 dùng `league/flysystem-aws-s3-v3`.
   - JWT nếu quyết định giữ token JWT thật cho frontend đọc `exp`; hoặc giữ Sanctum và sửa frontend không phụ thuộc JWT expiry.
3. Xóa hoặc thay thế migrations cũ xung đột:
   - `users`, `user_roles`, `user_coupons` sẽ đổi sang `accounts`, `account_roles`, `account_coupons`.
   - Các bảng domain trùng tên như `products`, `cart`, `order_details` phải tạo lại theo schema Spring cuối cùng.
4. Chuẩn hóa namespaces:
   - `app/Models`
   - `app/Http/Controllers/Api`
   - `app/Services`
   - `app/DTO` hoặc `app/Data`
   - `app/Jobs`
   - `app/Console/Commands`
   - `database/migrations`
   - `database/seeders`

Deliverable:

1. Laravel build chạy được với empty DB.
2. Không còn migration cũ tạo schema lệch `users/user_roles`.

## Phase 2 - Convert Flyway SQL sang Laravel migrations

Nên gom theo domain, không nhất thiết giữ 23 file tương ứng 1-1. Tên migration đề xuất:

1. `2026_06_06_000001_create_auth_schema.php`
   - `accounts`, `profiles`, `roles`, `account_roles`, `permissions`, `role_permissions`, `account_login_events`.
   - Cột hậu kỳ: `google_id`, `status`, `email_verified`, `email_verified_at`, `last_login_at`, `last_login_ip`, `login_count`, `ban_reason`.

2. `2026_06_06_000002_create_catalog_schema.php`
   - `categories`, `products`, `surfaces`, `product_surfaces`, `product_specs_shoes`, `product_variants`, `product_images`, `tags`, `product_tags`.
   - Cột hậu kỳ: `products.original_price`, `product_variants.version`, `product_images.object_name`, `content_type`, `size_bytes`, `is_primary`, `sort_order`.
   - Index: category, brand, gender, base price, deleted_at, SKU, image sort/object.

3. `2026_06_06_000003_create_customer_activity_schema.php`
   - `reviews`, `wishlist`, `addresses`.
   - Cột GHN address: `ghn_province_id`, `ghn_district_id`, `ghn_ward_code`.

4. `2026_06_06_000004_create_coupon_cart_order_schema.php`
   - `coupons`, `account_coupons`, `cart`, `cart_item`, `order_details`, `order_item`.
   - Cột hậu kỳ:
     - `coupons.version`, `deleted_at`, `applicable_scope`, `applicable_brand`, `applicable_product_id`, `applicable_variant_id`.
     - `order_details.payment_method`, `recipient_name`, `recipient_phone`, `contact_email`, `shipping_provider`, `shipping_fee`, `shipping_discount`, GHN snapshot fields, `expected_delivery_time`, `version`.
   - Check constraint coupon scope: `ALL`, `BRAND`, `PRODUCT`, `VARIANT`.

5. `2026_06_06_000005_create_payment_schema.php`
   - `payment_details`, `payment_events`, `payment_webhook_events`.
   - Archive tables from V22: `payment_details_order_duplicate_archive`, `payment_events_duplicate_payment_archive`.
   - Constraint unique `payment_details.order_id`.
   - Unique provider transaction and webhook idempotency partial index.

6. `2026_06_06_000006_create_shipping_schema.php`
   - `shipping_orders`, `shipping_events`.
   - Index provider status, client order code, provider order code.

7. `2026_06_06_000007_create_mail_outbox_schema.php`
   - `mail_outbox`.
   - Partial due index for `PENDING`, `FAILED`.

8. `2026_06_06_000008_create_product_size_guides_schema.php`
   - `product_size_guides`.
   - Partial unique active guide by product.
   - Brand active index.

9. `2026_06_06_000009_seed_static_rbac.php` hoặc seeder riêng
   - Nếu muốn role/permission seed chạy qua seeder thay vì migration.

Laravel Blueprint không hỗ trợ đầy đủ partial indexes/checks phức tạp. Với PostgreSQL, dùng `DB::statement()` cho:

1. Partial indexes.
2. `CHECK` constraints.
3. `JSONB`.
4. `ON CONFLICT` không dùng trong migration tạo schema.
5. Data archival logic V22/V23 nếu migrate DB đã có dữ liệu.

## Phase 3 - Data migration nếu cần giữ DB GiayOhoho cũ

Nếu target DB hiện tại của GiayOhoho có dữ liệu cần giữ:

1. Backup SQL trước.
2. Tạo migration hoặc command transform:
   - `users` -> `accounts` + `profiles`.
   - `user_roles` -> `account_roles`.
   - `user_coupons` -> `account_coupons`.
   - `reviews.user_id` -> `reviews.account_id`.
   - `wishlist.user_id` -> `wishlist.account_id`.
   - `cart.user_id` -> `cart.account_id`.
   - `order_details.user_id` -> `order_details.account_id`.
3. Map role lowercase cũ sang uppercase mới:
   - `admin` -> `ADMIN`
   - `customer` -> `CUSTOMER`
   - `staff` -> `STAFF`
   - bỏ `guest` nếu không dùng.
4. Với dữ liệu trùng email/username/slug/sku, tạo report trước rồi mới migrate.

Nếu được reset DB:

1. Bỏ data migration.
2. Chạy migration mới.
3. Chạy Laravel seeders chuyển từ `DataSeeder.java`.

## Phase 4 - Convert DataSeeder Java sang Laravel seeders

Seeder hiện tại cần tách nhỏ:

1. `RolePermissionSeeder`
   - Roles: `ADMIN`, `CUSTOMER`, `STAFF`.
   - Permissions: product, order, account, category, dashboard, payment, coupon, shipping.

2. `AccountSeeder`
   - `admin@ohgiay.vn`, `tuankiethn@ohgiay.vn`, `kiet@ohgiay.vn`.
   - Password mặc định hiện tại: `123456`.
   - Tạo profile tương ứng nếu entity/profile logic yêu cầu.

3. `ReferenceCatalogSeeder`
   - Categories: `giay-chay-bo`, `giay-thoi-trang`, `giay-da-bong`.
   - Surfaces: `ROAD`, `TRAIL`, `TREADMILL`.
   - Tags: `ho-tro`, `em-ai`, `toc-do`, `ben-bi`.

4. `ProductCatalogSeeder`
   - Chuyển toàn bộ `buildNikeCatalog`, `buildAdidasCatalog`, `buildPumaCatalog`, `buildConverseCatalog`, `buildVansCatalog`, `buildNewBalanceCatalog`, `buildAsicsCatalog`, `buildHokaCatalog`.
   - Khoảng 100 sản phẩm, nhiều variants/images/specs.
   - Idempotent theo `slug`, SKU và image key, không tạo trùng khi chạy lại.

5. `CustomerActivitySeeder`
   - Reviews mẫu.
   - Wishlist mẫu.
   - Cart mẫu.

6. `DatabaseSeeder`
   - Gọi các seeder theo thứ tự trên.

Lưu ý chuyển đổi:

1. Java `BigDecimal` -> PHP string/float cẩn thận cho `drop_mm`; tiền VND dùng integer.
2. Java collection sync/delete legacy -> Laravel `updateOrCreate`, `sync`, soft delete theo `deleted_at`.
3. Không dùng Faker làm nguồn chính cho catalog, chỉ dùng nếu cần bổ sung demo không quan trọng.

## Phase 5 - Port backend modules Spring sang Laravel

Thứ tự port khuyến nghị:

1. Shared layer:
   - `ApiResponse` helper/macro.
   - Exception handler chuẩn JSON.
   - Pagination response giống `PageResponse`.
   - Request guards/sort whitelist.
   - Rate limit middleware.
   - Role/permission middleware.

2. Auth:
   - Register/login/logout/me.
   - Refresh token, CSRF refresh token nếu giữ flow hiện tại.
   - Google OAuth login.
   - Forgot/reset password.
   - Verify/resend email verification.
   - Change/setup password.
   - Session list/revoke.
   - Account status/ban/lock/audit login.

3. Profile:
   - Get/update profile.
   - Upload/delete avatar qua MinIO/S3 disk.

4. Catalog:
   - Public products: list filter/search/sort/page, show by id, show by slug, similar.
   - Categories/surfaces/tags.
   - Reviews.
   - Wishlist.
   - Product media proxy/generator fallback.

5. Cart/order:
   - Cart get/add/update quantity/change variant/remove/clear.
   - Coupon apply/remove, coupon scope.
   - Checkout idempotency key.
   - Stock concurrency/locking.
   - Order list/detail/cancel.

6. Payment:
   - Payment create/get by order.
   - COD provider.
   - PayOS provider, return status, cancel, webhook idempotency.
   - Payment expiration scheduler.
   - Payment state machine and events.

7. Shipping GHN:
   - Provinces/districts/wards cache.
   - Quote.
   - Admin store/list/create.
   - Preview/create/sync/cancel/delivery-again/return/update COD/print token.
   - GHN webhooks.
   - Scheduled sync open shipments.

8. Admin:
   - Dashboard.
   - Accounts/users status, restore, delete.
   - Categories CRUD/restore/product assignment.
   - Products CRUD, variants, images upload/update/primary/reorder/restore.
   - Size guides.
   - Orders list/detail/status.
   - Coupons CRUD/stats.
   - Sale price/original price management.
   - Monitoring page data if still needed.

9. Mail:
   - `mail_outbox` service.
   - Email templates.
   - Queue worker/scheduled command for retries.

10. Contact/policies/chatbot:
   - Contact Google Forms forwarder and spam/rate limit.
   - Policies endpoint.
   - Chatbot message endpoint, memory/state in Redis/cache, product tools, order support.
   - AI provider config and timeout/budget guard.

11. WebSocket/stock updates:
   - Option A: skip initially and poll stock on frontend.
   - Option B: Laravel Reverb/WebSockets + broadcast events after order commit.

## Phase 6 - API contract

React target should consume the same endpoint set as Vue current where possible:

Public:

1. `GET /api/products`
2. `GET /api/products/{id}`
3. `GET /api/products/by-slug/{slug}`
4. `GET /api/products/{id}/similar`
5. `GET /api/categories`
6. `GET /api/categories/{slug}`
7. `GET /api/surfaces`
8. `GET /api/tags`
9. `GET /api/products/{id}/reviews`
10. `GET /api/policies`
11. `POST /api/contact/requests`
12. `POST /api/chatbot/messages`

Auth/profile:

1. `POST /api/auth/register`
2. `POST /api/auth/login`
3. `POST /api/auth/refresh`
4. `POST /api/auth/google`
5. `POST /api/auth/forgot-password`
6. `POST /api/auth/reset-password`
7. `POST /api/auth/verify-email`
8. `POST /api/auth/resend-verification`
9. `GET /api/auth/me`
10. `POST /api/auth/logout`
11. `GET /api/auth/sessions`
12. `DELETE /api/auth/sessions/{deviceFingerprint}`
13. `DELETE /api/auth/sessions`
14. `GET /api/profile`
15. `PUT /api/profile`
16. `POST /api/profile/avatar`
17. `DELETE /api/profile/avatar`

Customer:

1. `GET/POST/PUT/DELETE /api/addresses`
2. `GET /api/cart`
3. `POST /api/cart/items`
4. `PATCH /api/cart/items/{id}`
5. `PATCH /api/cart/items/{id}/variant`
6. `DELETE /api/cart/items/{id}`
7. `DELETE /api/cart`
8. `POST /api/cart/coupon`
9. `DELETE /api/cart/coupon`
10. `POST /api/orders`
11. `GET /api/orders`
12. `GET /api/orders/{id}`
13. `PATCH /api/orders/{id}/cancel`
14. `GET/POST /api/wishlist`
15. `POST/DELETE /api/reviews`

Payment/shipping:

1. `POST /api/payments`
2. `GET /api/orders/{orderId}/payment`
3. `GET /api/payments/payos/return-status`
4. `POST /api/payments/{paymentId}/cancel`
5. `POST /api/payments/webhooks/payos`
6. `GET /api/shipping/ghn/provinces`
7. `GET /api/shipping/ghn/districts`
8. `GET /api/shipping/ghn/wards`
9. `POST /api/shipping/quotes`
10. `POST /api/shipping/ghn/webhooks/order-status`

Admin:

1. `GET /api/admin/dashboard`
2. `GET/PATCH/DELETE /api/admin/accounts`
3. `GET/POST/PUT/DELETE/PATCH restore /api/admin/categories`
4. `GET/POST/PUT/DELETE/PATCH restore /api/admin/products`
5. `POST/PUT/DELETE/PATCH restore /api/admin/products/{id}/variants`
6. `POST/GET/PATCH/DELETE /api/admin/products/{id}/images`
7. `PATCH /api/admin/products/{id}/images/{imageId}/primary`
8. `PATCH /api/admin/products/{id}/images/reorder`
9. `GET/POST/PUT/DELETE /api/admin/size-guides`
10. `GET/PATCH /api/admin/orders`
11. `GET/POST/PUT/DELETE /api/admin/coupons`
12. GHN admin routes under `/api/admin/orders/{orderId}/shipping/...`

## Phase 7 - Frontend Vue sang React

Target giữ React/Vite trong `GiayOhoho/giayohoho/resources/js/spa`.

Thứ tự migrate:

1. App shell:
   - Router React đầy đủ route Vue hiện tại.
   - Auth provider/context thay Pinia store.
   - API client tương đương `apiFetch`, support `ApiResponse`, auth token, refresh handling.
   - Toast provider.
   - Header/footer/admin shell.

2. Giữ nguyên UI Login/Register cũ:
   - File cũ: `resources/js/spa/pages/Login.jsx`, `Register.jsx`.
   - Không đổi layout card MUI cơ bản.
   - Đổi logic:
     - Login gọi `/auth/login`, đọc `data.token`, `data.csrfToken`, `data.account`.
     - Register gửi payload hiện tại: `firstName`, `lastName`, `username`, `email`, `phoneNumber`, `addressLine`, GHN ids/ward, `password`.
     - Nếu vẫn muốn form register cũ tối giản, backend phải cho phép missing phone/address/GHN hoặc React bổ sung fields ẩn/default. Khuyến nghị giữ UI cũ nhưng mở rộng vừa đủ field bắt buộc bằng style MUI đồng nhất.
     - Redirect admin về `/admin/dashboard`, customer về `/` hoặc query `redirect`.

3. Migrate public pages:
   - Home, Products, ProductDetail, Sale, Policies, Contact.
   - Product card/showcase.
   - Chatbot widget/FAB/redirect.

4. Migrate customer pages:
   - Cart, Checkout, Wishlist, Profile, Orders, OrderDetail.
   - PayOSReturn.
   - ForgotPassword, ResetPassword, VerifyEmail, SetupPassword.

5. Migrate admin pages:
   - Dashboard, Categories, Products, Variants, SizeGuides, Orders, OrderDetail, Coupons, SalePrices, Users, Monitoring.

6. Migrate shared libs:
   - form validation.
   - navigation helpers.
   - cart UI helpers.
   - checkout selection/snapshot.
   - chatbot message formatting/storage/events.

7. CSS:
   - Giữ theme/style GiayOhoho cho login/register.
   - Chuyển `frontend/src/styles.css` và các CSS phụ sang `resources/css/styles.css` theo từng page.
   - Tránh rewrite toàn bộ UI nếu page đã hoạt động; ưu tiên contract/API correctness trước.

## Phase 8 - Storage, external services, queue/scheduler

1. MinIO:
   - Laravel filesystem disk `s3` trỏ MinIO.
   - Media proxy route `/api/media/**`.
   - Product image upload validation JPG/PNG/WEBP, max size.
   - Avatar upload.

2. Redis:
   - Cache GHN master data.
   - Rate limit counters.
   - Chatbot memory/conversation state.
   - Token blacklist/session metadata nếu dùng.

3. Mail:
   - Laravel mailer config.
   - `mail_outbox` retry command chạy scheduler mỗi N giây/phút.

4. PayOS:
   - Laravel service/provider.
   - Webhook signature/idempotency.
   - Return/cancel URLs trỏ React routes.

5. GHN:
   - Laravel HTTP client service.
   - Webhook routes public, verify token/signature nếu GHN hỗ trợ.

6. AI/chatbot:
   - OpenAI-compatible HTTP client.
   - Timeout/budget guard.
   - Product search tools port từ service hiện tại.

## Phase 9 - Testing

Backend test:

1. Migration test: fresh migrate trên PostgreSQL.
2. Seeder test: chạy lại 2 lần không duplicate.
3. Auth test: register/login/me/logout/refresh/verify/reset/google mock.
4. Product API filters/sort/page/show by slug.
5. Cart/order concurrency:
   - stock không âm.
   - idempotency key không tạo duplicate order.
   - coupon scope.
6. Payment:
   - COD flow.
   - PayOS create/return/webhook/cancel/expiration.
7. Shipping:
   - GHN quote/admin create/sync/webhook bằng fake HTTP.
8. Mail outbox retry.
9. Admin authorization.

Frontend test:

1. Build Vite.
2. Smoke route public/customer/admin.
3. Login/register UI giữ đúng layout cũ.
4. Auth redirect.
5. Cart/checkout/payment return.
6. Admin product/image/order/coupon flows.

Manual acceptance:

1. `php artisan migrate:fresh --seed` tạo được DB đầy đủ.
2. Đăng nhập `admin@ohgiay.vn / 123456`.
3. Xem catalog khoảng 100 sản phẩm.
4. Add cart, checkout COD.
5. Admin xem order, đổi trạng thái.
6. Upload ảnh product/avatar qua MinIO.
7. PayOS sandbox tạo payment nếu env có key.
8. GHN quote nếu env có token/shop id.

## Phase 10 - Cutover thay GiayOhoho

1. Freeze code GiayOhoho cũ.
2. Backup DB/storage.
3. Deploy Laravel migration target.
4. Chạy `php artisan migrate --force`.
5. Chạy data transform nếu giữ dữ liệu cũ.
6. Chạy seeders chỉ cho dữ liệu reference/demo cần thiết.
7. Build frontend `npm run build`.
8. Chạy queue worker và scheduler.
9. Smoke test production/staging.
10. Đổi domain/reverse proxy sang Laravel public.

## Rủi ro chính

1. Auth schema đổi từ `users` sang `accounts/profiles` làm vỡ Sanctum default nếu không cấu hình guard/model đúng.
2. React login/register cũ quá tối giản so với register payload hiện tại có địa chỉ/GHN/avatar. Cần quyết định giữ form tối giản hay bổ sung field nhưng giữ style cũ.
3. Flyway V22 có logic archive duplicate payment. Nếu DB target đã có dữ liệu, không được bỏ qua.
4. Laravel Blueprint thiếu partial indexes/checks, cần `DB::statement()` PostgreSQL-specific.
5. Chatbot/Spring AI không có tương đương Laravel trực tiếp; cần port bằng HTTP OpenAI-compatible.
6. WebSocket STOMP Spring không có tương đương sẵn; cần chọn Laravel Reverb hoặc bỏ realtime phase đầu.
7. Seeder 100 sản phẩm dùng ảnh URL ngoài; nếu cần ổn định lâu dài nên mirror ảnh vào MinIO.

## Milestone đề xuất

1. M1: Laravel migrate fresh + seed chạy được.
2. M2: Auth/profile/catalog public API chạy được.
3. M3: React shell + login/register cũ + product listing/detail chạy được.
4. M4: Cart/checkout/order/admin basic chạy được.
5. M5: Payment PayOS/COD + mail outbox chạy được.
6. M6: GHN shipping + product images/avatar MinIO chạy được.
7. M7: Admin advanced + chatbot + policies/contact + monitoring.
8. M8: Full regression, data migration/cutover.

## Checklist file cần xử lý

Current project:

1. `src/main/resources/db/migration/*.sql` -> `GiayOhoho/giayohoho/database/migrations/*.php`
2. `src/main/java/vn/edu/tlu/webbangiaytlu/seeder/DataSeeder.java` -> `database/seeders/*Seeder.php`
3. `src/main/java/.../modules/*` -> Laravel controllers/services/models/jobs.
4. `src/main/java/.../shared/*` -> Laravel middleware/exception/resource helpers.
5. `src/main/java/.../infrastructure/*` -> Laravel services/config/filesystem/mail.
6. `frontend/src/views/*.vue` -> `resources/js/spa/pages/*.jsx`
7. `frontend/src/components/*.vue` -> `resources/js/spa/components/*.jsx`
8. `frontend/src/services/*.ts` -> `resources/js/spa/services/*.js`
9. `frontend/src/lib/*.ts` -> `resources/js/spa/lib/*.js`
10. `frontend/src/stores/*.ts` -> React context/hooks.

GiayOhoho target giữ lại/chỉnh:

1. `resources/js/spa/pages/Login.jsx`
2. `resources/js/spa/pages/Register.jsx`
3. `resources/js/spa/main.jsx` nhưng mở rộng route.
4. `resources/js/spa/ui/*`
5. `resources/css/styles.css`
6. Laravel skeleton, config, routing, Sanctum nếu vẫn dùng.
