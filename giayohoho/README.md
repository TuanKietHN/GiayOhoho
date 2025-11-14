<div align="center">

# 👟 GiayOhoho

### Hệ thống E-commerce Bán Giày Chuyên Nghiệp

[![Laravel](https://img.shields.io/badge/Laravel-12.0-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)](https://laravel.com)
[![React](https://img.shields.io/badge/React-19.2.0-61DAFB?style=for-the-badge&logo=react&logoColor=black)](https://reactjs.org)
[![PHP](https://img.shields.io/badge/PHP-8.2-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://php.net)
[![Vite](https://img.shields.io/badge/Vite-7.2.2-646CFF?style=for-the-badge&logo=vite&logoColor=white)](https://vitejs.dev)
[![MySQL](https://img.shields.io/badge/MySQL-8.0-4479A1?style=for-the-badge&logo=mysql&logoColor=white)](https://mysql.com)

<p align="center">
  <img src="app-gif.gif" alt="Demo" width="600"/>
</p>

**GiayOhoho** là một ứng dụng web full-stack hiện đại cho việc mua bán giày thể thao trực tuyến, được xây dựng với Laravel API backend và React SPA frontend.

[Tính năng](#-tính-năng) • [Cài đặt](#-cài-đặt) • [Chạy dự án](#-chạy-dự-án) • [Testing](#-testing) • [API](#-api-documentation) • [Đóng góp](#-đóng-góp)

---

</div>

## 📋 Mục lục

- [Giới thiệu](#-giới-thiệu)
- [Tính năng](#-tính-năng)
- [Công nghệ](#️-công-nghệ-sử-dụng)
- [Kiến trúc hệ thống](#-kiến-trúc-hệ-thống)
- [Yêu cầu hệ thống](#-yêu-cầu-hệ-thống)
- [Cài đặt](#-cài-đặt)
- [Cấu hình](#️-cấu-hình)
- [Chạy dự án](#-chạy-dự-án)
- [Testing](#-testing)
- [API Documentation](#-api-documentation)
- [Database Schema](#️-database-schema)
- [Screenshots](#-screenshots)
- [Đóng góp](#-đóng-góp)
- [License](#-license)

---

## 🎯 Giới thiệu

**GiayOhoho** là một nền tảng thương mại điện tử toàn diện chuyên về giày thể thao, được thiết kế để mang đến trải nghiệm mua sắm trực tuyến tốt nhất cho người dùng. Dự án kết hợp sức mạnh của Laravel backend API và React SPA frontend, tạo nên một ứng dụng web hiện đại, nhanh chóng và dễ sử dụng.

### 🌟 Điểm nổi bật

- ⚡ **Hiệu suất cao**: SPA React với Vite cho tốc độ tải trang nhanh
- 🔐 **Bảo mật**: Laravel Sanctum với token-based authentication
- 🎨 **UI/UX hiện đại**: Giao diện responsive, thân thiện với người dùng
- 📊 **Admin Dashboard**: Hệ thống quản trị đầy đủ và mạnh mẽ
- 🔍 **Tìm kiếm nâng cao**: Bộ lọc đa tiêu chí cho sản phẩm
- 💳 **Thanh toán linh hoạt**: Hỗ trợ nhiều phương thức thanh toán
- 📱 **Responsive Design**: Hoạt động mượt mà trên mọi thiết bị

---

## ✨ Tính năng

### 🛍️ Khách hàng

<details>
<summary><b>Quản lý tài khoản</b></summary>

- ✅ Đăng ký, đăng nhập, đăng xuất
- ✅ Quản lý thông tin cá nhân
- ✅ Quản lý địa chỉ giao hàng
- ✅ Lịch sử đơn hàng

</details>

<details>
<summary><b>Mua sắm</b></summary>

- ✅ Duyệt danh mục sản phẩm
- ✅ Tìm kiếm & lọc sản phẩm theo:
  - Brand, Gender, Price range
  - Size, Color, Surface type
  - Cushioning level, Pronation type
  - Waterproof, Reflective features
- ✅ Xem chi tiết sản phẩm với đầy đủ thông tin
- ✅ Xem sản phẩm tương tự
- ✅ Sắp xếp theo: Mới nhất, Giá, Đánh giá

</details>

<details>
<summary><b>Giỏ hàng & Thanh toán</b></summary>

- ✅ Thêm/xóa/cập nhật sản phẩm trong giỏ
- ✅ Áp dụng mã giảm giá
- ✅ Tính toán tự động: giá trị, giảm giá, tổng tiền
- ✅ Checkout với địa chỉ giao hàng
- ✅ Nhiều phương thức thanh toán

</details>

<details>
<summary><b>Tương tác</b></summary>

- ✅ Wishlist (danh sách yêu thích)
- ✅ Đánh giá & review sản phẩm
- ✅ Chỉ đánh giá sản phẩm đã mua
- ✅ Sửa/xóa review của mình

</details>

### 👨‍💼 Admin

<details>
<summary><b>Quản lý người dùng</b></summary>

- ✅ Danh sách tất cả người dùng
- ✅ Tìm kiếm & lọc người dùng
- ✅ Gán/sửa roles (Customer, Admin, Staff)
- ✅ Xem thông tin chi tiết người dùng

</details>

<details>
<summary><b>Quản lý đơn hàng</b></summary>

- ✅ Danh sách đơn hàng với phân trang
- ✅ Lọc theo trạng thái
- ✅ Cập nhật trạng thái đơn hàng
- ✅ Xem chi tiết đơn hàng
- ✅ Export CSV đơn hàng

</details>

<details>
<summary><b>Quản lý sản phẩm</b></summary>

- ✅ CRUD sản phẩm đầy đủ
- ✅ Quản lý danh mục (Categories)
- ✅ Quản lý biến thể (Variants: size, color, stock)
- ✅ Upload & quản lý ảnh sản phẩm
- ✅ Quản lý thông số kỹ thuật (Specs)
- ✅ Quản lý bề mặt sử dụng (Surfaces)
- ✅ Quản lý tags
- ✅ Cảnh báo sản phẩm sắp hết hàng
- ✅ Export CSV sản phẩm

</details>

<details>
<summary><b>Quản lý khuyến mãi</b></summary>

- ✅ CRUD mã giảm giá (Coupons)
- ✅ Thiết lập điều kiện: giá trị tối thiểu, số lần dùng
- ✅ Coupons theo % hoặc số tiền cố định
- ✅ Thời gian hiệu lực
- ✅ Thống kê sử dụng coupon

</details>

<details>
<summary><b>Moderation</b></summary>

- ✅ Xem tất cả reviews
- ✅ Xóa reviews không phù hợp
- ✅ Lọc reviews theo sản phẩm

</details>

---

## 🛠️ Công nghệ sử dụng

### Backend

```
Laravel Framework: ^12.0
PHP: ^8.2
Laravel Sanctum: ^4.2 (Token Authentication)
MySQL: ^8.0
```

### Frontend

```
React: ^19.2.0
React Router DOM: ^7.1.3
Vite: ^7.2.2
Axios: ^1.7.9
```

### DevOps & Tools

```
Composer: ^2.8.12
Node.js: ^24.11.1
NPM: Latest
Git: Latest
```

---

## 🏗️ Kiến trúc hệ thống

```
┌─────────────────────────────────────────────────────────┐
│                    CLIENT LAYER                         │
│  ┌──────────────────────────────────────────────────┐  │
│  │          React SPA (Vite)                        │  │
│  │  - React Router                                  │  │
│  │  - Axios API Client                              │  │
│  │  - UI Components (Toast, Slider)                 │  │
│  └──────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────┘
                         ▼ HTTP/HTTPS
┌─────────────────────────────────────────────────────────┐
│                    API LAYER                            │
│  ┌──────────────────────────────────────────────────┐  │
│  │          Laravel API (REST)                      │  │
│  │  - Sanctum Auth Middleware                       │  │
│  │  - RBAC (Role Middleware)                        │  │
│  │  - CORS Middleware                               │  │
│  │  - Rate Limiting                                 │  │
│  └──────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────┘
                         ▼
┌─────────────────────────────────────────────────────────┐
│                  BUSINESS LOGIC                         │
│  ┌──────────────────────────────────────────────────┐  │
│  │  Controllers (API)                               │  │
│  │  - AuthController                                │  │
│  │  - ProductController                             │  │
│  │  - CartController                                │  │
│  │  - OrderController                               │  │
│  │  - Admin/* Controllers                           │  │
│  └──────────────────────────────────────────────────┘  │
│  ┌──────────────────────────────────────────────────┐  │
│  │  Models (Eloquent ORM)                           │  │
│  │  - User, Role, Product, Order, etc.              │  │
│  └──────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────┘
                         ▼
┌─────────────────────────────────────────────────────────┐
│                   DATA LAYER                            │
│  ┌──────────────────────────────────────────────────┐  │
│  │          MySQL Database                          │  │
│  │  - 25+ Tables                                    │  │
│  │  - Relationships & Indexes                       │  │
│  │  - Migrations & Seeders                          │  │
│  └──────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────┘
```

---

## 💻 Yêu cầu hệ thống

### Bắt buộc

- **PHP**: >= 8.2
- **Composer**: >= 2.8
- **Node.js**: >= 24.x
- **MySQL**: >= 8.0
- **Apache/Nginx**: Web server

### Extensions PHP cần thiết

```
- OpenSSL
- PDO
- mysqli
- pdo_mysql
- fileinfo
```
---

## 📦 Cài đặt

### 1. Clone Repository

```bash/CMD/PS
git clone https://github.com/TuanKietHN/GiayOhoho.git
cd giayohoho
```

### 2. Cài đặt Backend Dependencies

```bash/CMD/PS
composer install
```

### 3. Cài đặt Frontend Dependencies

```bash/CMD
npm install
```

### 4. Tạo file Environment

```bash/CMD/PS
cp .env.example .env
```

### 5. Generate Application Key

```bash/CMD/PS
php artisan key:generate
```

---

## ⚙️ Cấu hình

### Database Configuration

Mở file `.env` và cấu hình database:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=giayohoho
DB_USERNAME=your_username
DB_PASSWORD=your_password

SESSION_DRIVER=file
CACHE_STORE=file
```

### Application Configuration

```env
APP_NAME="GiayOhoho"
APP_ENV=local
APP_DEBUG=true
APP_URL=your_URL // http://localhost:8000/

# Sanctum Configuration
SANCTUM_STATEFUL_DOMAINS=localhost:5173,localhost:8000
SESSION_DOMAIN=localhost
```

### CORS Configuration

File `config/cors.php` đã được cấu hình sẵn để cho phép SPA hoạt động:

```php
'paths' => ['api/*'],
'allowed_methods' => ['*'],
'allowed_origins' => ['*'],
'allowed_headers' => ['*'],
```

---

## 🚀 Chạy dự án

### Phương án 1: Sử dụng Migrations & Seeders (Khuyến nghị)

#### Bước 1: Chạy Migrations

```bash
php artisan migrate:fresh
```

#### Bước 2: Seed dữ liệu mẫu

```bash
php artisan db:seed
```

Hoặc kết hợp một lệnh:

```bash
php artisan migrate:fresh --seed
```

### Phương án 2: Import SQL trực tiếp

```bash
mysql -u your_username -p giayohoho < giayohoho.sql
```

### Khởi động Backend Server

```bash
php artisan serve
```

Backend sẽ chạy tại: `http://localhost:8000`

### Khởi động Frontend Development Server

Mở terminal mới:

```bash
npm run dev
```

Frontend sẽ chạy tại: `http://localhost:5173`

### Build Production

```bash
npm run build
php artisan optimize
```

---

## 🧪 Testing

### Tài khoản mẫu

Sau khi seed dữ liệu, bạn có thể sử dụng các tài khoản sau:

#### Admin Account
```
Email: admin@example.com
Username: admin
Password: Admin@123
```

#### Customer Account
```
Email: customer@example.com
Username: customer
Password: Customer@123
```

### Test Postman Collection

Import file `postman_collection.json` vào Postman để test các API endpoints.

#### Các bước test:

1. **Health Check**
   ```
   GET http://localhost:8000/api/health
   ```

2. **Login**
   ```
   POST http://localhost:8000/api/auth/login
   Body: {
     "email": "customer@example.com",
     "password": "Customer@123"
   }
   ```
   → Lưu token từ response

3. **Get User Info**
   ```
   GET http://localhost:8000/api/auth/me
   Header: Authorization: Bearer {your_token}
   ```

4. **Browse Products**
   ```
   GET http://localhost:8000/api/products
   Query params: ?brand=Nike&gender=Unisex&sort=newest
   ```

5. **Add to Cart**
   ```
   POST http://localhost:8000/api/auth/cart/items
   Header: Authorization: Bearer {your_token}
   Body: {
     "product_variant_id": 1,
     "quantity": 1
   }
   ```

6. **Apply Coupon**
   ```
   POST http://localhost:8000/api/auth/cart/apply-coupon
   Header: Authorization: Bearer {your_token}
   Body: {
     "code": "WELCOME10"
   }
   ```

7. **Checkout**
   ```
   POST http://localhost:8000/api/auth/checkout
   Header: Authorization: Bearer {your_token}
   Body: {
     "order_address": "123 Đường ABC, Quận 1, TP.HCM",
     "payment_method": "COD"
   }
   ```

### Test Frontend Features

#### 1. Customer Flow
- Truy cập `http://localhost:5173`
- Đăng nhập với tài khoản customer
- Duyệt sản phẩm, sử dụng bộ lọc
- Thêm sản phẩm vào giỏ hàng
- Áp mã giảm giá `WELCOME10`
- Thực hiện checkout
- Kiểm tra lịch sử đơn hàng

#### 2. Admin Flow
- Đăng nhập với tài khoản admin
- Truy cập Dashboard tại `/admin`
- Quản lý users, products, orders
- Tạo/sửa/xóa sản phẩm
- Cập nhật trạng thái đơn hàng
- Export CSV

---

## 📚 API Documentation

### Base URL
```
http://localhost:8000/api
```

### Authentication
Tất cả các endpoint yêu cầu auth sử dụng Bearer Token:
```
Authorization: Bearer {your_token}
```

### Public Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/health` | Health check |
| POST | `/auth/register` | Đăng ký tài khoản |
| POST | `/auth/login` | Đăng nhập |
| GET | `/products` | Danh sách sản phẩm |
| GET | `/products/{id}` | Chi tiết sản phẩm |
| GET | `/products/{id}/reviews` | Reviews của sản phẩm |
| GET | `/products/{id}/similar` | Sản phẩm tương tự |

### Protected Endpoints (Customer)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/auth/me` | Thông tin user hiện tại |
| POST | `/auth/logout` | Đăng xuất |
| GET | `/auth/addresses` | Danh sách địa chỉ |
| POST | `/auth/addresses` | Thêm địa chỉ |
| GET | `/auth/wishlist` | Danh sách yêu thích |
| POST | `/auth/wishlist` | Thêm vào wishlist |
| GET | `/auth/cart` | Xem giỏ hàng |
| POST | `/auth/cart/items` | Thêm vào giỏ |
| POST | `/auth/cart/apply-coupon` | Áp mã giảm giá |
| POST | `/auth/checkout` | Đặt hàng |
| GET | `/auth/orders` | Lịch sử đơn hàng |
| POST | `/auth/reviews` | Tạo review |

### Admin Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/admin/users` | Quản lý users |
| POST | `/admin/users/{id}/roles` | Gán roles |
| GET | `/admin/orders` | Quản lý đơn hàng |
| POST | `/admin/orders/{id}/status` | Cập nhật trạng thái |
| GET | `/admin/products` | Quản lý sản phẩm |
| POST | `/admin/products` | Tạo sản phẩm |
| GET | `/admin/categories` | Quản lý danh mục |
| GET | `/admin/variants` | Quản lý biến thể |
| GET | `/admin/coupons` | Quản lý coupons |
| GET | `/admin/reviews` | Moderation reviews |
| GET | `/admin/export/products.csv` | Export sản phẩm |
| GET | `/admin/export/orders.csv` | Export đơn hàng |

### Filter Parameters (Products)

```
?q=keyword                    # Tìm kiếm
&brand=Nike                   # Thương hiệu
&gender=Unisex                # Giới tính
&min_price=1000000            # Giá tối thiểu
&max_price=5000000            # Giá tối đa
&size=42                      # Size EU
&color=Black                  # Màu sắc
&surface=road                 # Bề mặt
&cushioning_level=high        # Độ đệm
&pronation_type=neutral       # Kiểu sải chân
&is_waterproof=1              # Chống nước
&sort=newest                  # Sắp xếp: newest, price_asc, price_desc, rating
```

---

## 🗄️ Database Schema

### Core Tables

#### Users & Roles
- `users` - Thông tin người dùng
- `roles` - Các vai trò (guest, customer, admin, staff)
- `user_roles` - Bảng trung gian

#### Products Catalog
- `categories` - Danh mục sản phẩm
- `products` - Sản phẩm
- `product_variants` - Biến thể (size, color, stock)
- `product_images` - Ảnh sản phẩm
- `product_specs` - Thông số kỹ thuật
- `surfaces` - Bề mặt sử dụng
- `tags` - Tags sản phẩm
- `product_tag` - Bảng trung gian

#### Shopping & Orders
- `carts` - Giỏ hàng
- `cart_items` - Items trong giỏ
- `order_details` - Đơn hàng
- `order_items` - Items trong đơn
- `payment_details` - Thông tin thanh toán

#### User Interactions
- `reviews` - Đánh giá sản phẩm
- `wishlists` - Danh sách yêu thích
- `addresses` - Địa chỉ giao hàng

#### Promotions
- `coupons` - Mã giảm giá
- `user_coupons` - Lịch sử sử dụng coupon

### Entity Relationship Diagram

```
┌─────────┐       ┌──────────┐       ┌──────────┐
│  Users  │───────│   Roles  │       │ Products │
└─────────┘       └──────────┘       └──────────┘
     │                                      │
     │                                      ├─────┐
     │            ┌──────────┐              │     │
     ├────────────│  Carts   │              │     │
     │            └──────────┘              │     │
     │                 │                    │     │
     │            ┌──────────┐              │     │
     │            │CartItems │──────────────┘     │
     │            └──────────┘                    │
     │                                            │
     │            ┌──────────┐              ┌──────────┐
     ├────────────│  Orders  │              │ Variants │
     │            └──────────┘              └──────────┘
     │                 │                          │
     │            ┌──────────┐                    │
     │            │OrderItems│────────────────────┘
     │            └──────────┘
     │
     │            ┌──────────┐
     ├────────────│ Wishlist │──────────────┐
     │            └──────────┘              │
     │                                      │
     │            ┌──────────┐              │
     └────────────│ Reviews  │──────────────┘
                  └──────────┘
```

---

## 📸 Screenshots

### Customer Interface

#### Homepage & Product Listing
![Products](https://via.placeholder.com/800x400?text=Product+Listing+Page)

#### Product Detail
![Detail](https://via.placeholder.com/800x400?text=Product+Detail+Page)

#### Shopping Cart
![Cart](https://via.placeholder.com/800x400?text=Shopping+Cart)

### Admin Dashboard

#### Dashboard Overview
![Admin](https://via.placeholder.com/800x400?text=Admin+Dashboard)

#### Product Management
![Products](https://via.placeholder.com/800x400?text=Product+Management)

#### Order Management
![Orders](https://via.placeholder.com/800x400?text=Order+Management)

---

## 🔧 Troubleshooting

### Lỗi thường gặp

#### 1. CORS Error
```
Error: Access to XMLHttpRequest has been blocked by CORS policy
```
**Giải pháp**: Kiểm tra file `config/cors.php` và đảm bảo CORS middleware được đăng ký trong `bootstrap/app.php`

#### 2. Token Invalid
```
Error: Unauthenticated
```
**Giải pháp**: 
- Kiểm tra token có được gửi trong header không
- Token có còn hiệu lực không
- Đăng nhập lại để lấy token mới

#### 3. Database Connection
```
Error: SQLSTATE[HY000] [2002] Connection refused
```
**Giải pháp**: 
- Kiểm tra MySQL đã chạy chưa
- Kiểm tra thông tin database trong `.env`
- Test connection: `php artisan migrate:status`

#### 4. Vite Error
```
Error: Failed to resolve import
```
**Giải pháp**:
- Xóa `node_modules` và `package-lock.json`
- Chạy lại `npm install`
- Clear cache: `npm run dev -- --force`

#### 5. Permission Error
```
Error: The stream or file could not be opened
```
**Giải pháp**:
```bash
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

---

## 🤝 Đóng góp

Mọi đóng góp đều được chào đón! Để đóng góp:

1. Fork repository
2. Tạo branch mới (`git checkout -b feature/AmazingFeature`)
3. Commit changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to branch (`git push origin feature/AmazingFeature`)
5. Tạo Pull Request

### Coding Standards

- Tuân thủ PSR-12 cho PHP
- ESLint cho JavaScript/React
- Viết tests cho features mới
- Document code đầy đủ

---

## 📝 TODO

- [ ] Tích hợp thanh toán VNPay/Momo
- [ ] Thêm chat support realtime
- [ ] Mobile app (React Native)
- [ ] Email notifications
- [ ] SMS notifications
- [ ] Social login (Google, Facebook)
- [ ] Advanced analytics dashboard
- [ ] Multi-language support
- [ ] Dark mode
- [ ] PWA support

---

## 👨‍💻 Tác giả

**Your Name**
- GitHub: [@TuanKietHN](https://github.com/TuanKietHN/)
- Email: tuankiethn2410@gmail.com

---

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

### Why Apache 2.0?

We chose Apache 2.0 for this project because:

- ✅ **Patent Protection**: Explicit patent grant protects contributors and users
- ✅ **Corporate Friendly**: Preferred by enterprises for commercial use
- ✅ **Clear Terms**: Detailed legal language reduces ambiguity
- ✅ **Modification Notice**: Requires disclosure of changes (good for transparency)
- ✅ **Permissive**: Still allows commercial use and modification like MIT

**Key Requirements:**
- Keep the LICENSE file in your distribution
- Include NOTICE file if provided
- State significant changes to the code
- Provide attribution to original authors

**You CAN:**
- ✓ Use commercially
- ✓ Modify the code
- ✓ Distribute
- ✓ Sublicense
- ✓ Use privately
- ✓ Use patents granted by contributors

**You CANNOT:**
- ✗ Hold contributors liable
- ✗ Use contributors' trademarks without permission

---

## 🙏 Acknowledgments

- [Laravel](https://laravel.com) - The PHP Framework
- [React](https://reactjs.org) - JavaScript Library
- [Vite](https://vitejs.dev) - Frontend Tooling
- [Tailwind CSS](https://tailwindcss.com) - CSS Framework (if used)
- [Font Awesome](https://fontawesome.com) - Icons

---

<div align="center">

### ⭐ Nếu project hữu ích, hãy cho một star nhé! ⭐

**Made with ❤️ by Kiet**

[⬆ Back to top](#-giayohoho)

</div>