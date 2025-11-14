-- ============================================
-- DATABASE: ONLINE SHOE STORE
-- ============================================

CREATE DATABASE IF NOT EXISTS `giayohoho`;
USE `giayohoho`;

-- ============================================
-- USERS & AUTH
-- ============================================

CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    avatar VARCHAR(255),
    first_name VARCHAR(255),
    last_name VARCHAR(255),
    username VARCHAR(255) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255),
    birth_of_date DATE,
    phone_number VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL
);

CREATE TABLE roles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL UNIQUE,
    description VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE user_roles (
    user_id INT,
    role_id INT,
    PRIMARY KEY (user_id, role_id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (role_id) REFERENCES roles(id)
);

CREATE TABLE addresses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    address_line VARCHAR(255),
    ward VARCHAR(255),
    district VARCHAR(255),
    city VARCHAR(100),
    country VARCHAR(100),
    postal_code VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- ============================================
-- CATEGORIES (đa cấp) CHO GIÀY & SẢN PHẨM LIÊN QUAN
-- ============================================

CREATE TABLE categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    parent_id INT NULL,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (parent_id) REFERENCES categories(id)
);

-- Ví dụ data sau này:
-- Footwear (parent)
--   - running-shoes
--   - trail-running-shoes
--   - walking-shoes
-- Apparel (parent)
--   - running-tops
--   - running-shorts
-- Accessories ...

-- ============================================
-- PRODUCTS (GIÀY)
-- ============================================

CREATE TABLE products (
    id INT PRIMARY KEY AUTO_INCREMENT,
    category_id INT,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    brand VARCHAR(255),
    gender ENUM('male','female','unisex') DEFAULT 'unisex',
    base_price BIGINT NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (category_id) REFERENCES categories(id)
);

CREATE INDEX idx_products_category ON products(category_id);
CREATE INDEX idx_products_brand ON products(brand);
CREATE INDEX idx_products_gender ON products(gender);
CREATE INDEX idx_products_price ON products(base_price);

-- ============================================
-- SURFACES / USAGE (ROAD / TRAIL / TREADMILL / WALKING)
-- ============================================

CREATE TABLE surfaces (
    id INT PRIMARY KEY AUTO_INCREMENT,
    code VARCHAR(50) NOT NULL UNIQUE, -- 'road', 'trail', 'treadmill', 'walking', ...
    name VARCHAR(100) NOT NULL,       -- 'Chạy đường nhựa', 'Chạy trail', ...
    description TEXT
);

CREATE TABLE product_surfaces (
    product_id INT NOT NULL,
    surface_id INT NOT NULL,
    PRIMARY KEY (product_id, surface_id),
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (surface_id) REFERENCES surfaces(id)
);

-- ============================================
-- SPEC KỸ THUẬT RIÊNG CHO GIÀY (SHOE SPECS)
-- ============================================

CREATE TABLE product_specs_shoes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    product_id INT UNIQUE, -- 1:1 với products
    cushioning_level ENUM('low','medium','high','maximum') DEFAULT 'medium',
    pronation_type ENUM('neutral','stability','motion_control') DEFAULT 'neutral',
    drop_mm DECIMAL(4,1),       -- ví dụ 8.0 mm
    weight_grams INT,           -- trọng lượng đôi giày (theo size chuẩn)
    is_waterproof BOOLEAN DEFAULT FALSE,
    is_reflective BOOLEAN DEFAULT FALSE,
    upper_material VARCHAR(255),
    midsole_technology VARCHAR(255),
    outsole_technology VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id)
);

-- ============================================
-- PRODUCT VARIANTS (SIZE + MÀU + STOCK)
-- ============================================

CREATE TABLE product_variants (
    id INT PRIMARY KEY AUTO_INCREMENT,
    product_id INT NOT NULL,
    size VARCHAR(10) NOT NULL,      -- EU 40, 41, 42..., US 8, 9,...
    color VARCHAR(50) NOT NULL,     -- 'Black/White', 'Blue/Yellow', ...
    sku VARCHAR(100) UNIQUE,
    stock INT DEFAULT 0,
    extra_price BIGINT DEFAULT 0,   -- chênh giá so với base_price (nếu có)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (product_id) REFERENCES products(id)
);

CREATE INDEX idx_product_variants_product ON product_variants(product_id);
CREATE INDEX idx_product_variants_size_color ON product_variants(product_id, size, color);

-- Giá bán thực tế = products.base_price + product_variants.extra_price

-- ============================================
-- PRODUCT IMAGES (CÓ THỂ GẮN THEO PRODUCT HOẶC VARIANT)
-- ============================================

CREATE TABLE product_images (
    id INT PRIMARY KEY AUTO_INCREMENT,
    product_id INT NOT NULL,
    product_variant_id INT NULL, -- nếu ảnh riêng cho màu/biến thể
    image_url VARCHAR(255) NOT NULL,
    alt_text VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (product_variant_id) REFERENCES product_variants(id)
);

-- ============================================
-- TAGS (TÙY CHỌN: CARBON PLATE, WIDE, DAILY TRAINER, RACE DAY)
-- ============================================

CREATE TABLE tags (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    slug VARCHAR(100) UNIQUE
);

CREATE TABLE product_tags (
    product_id INT NOT NULL,
    tag_id INT NOT NULL,
    PRIMARY KEY (product_id, tag_id),
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (tag_id) REFERENCES tags(id)
);

-- ============================================
-- REVIEWS & WISHLIST
-- ============================================

CREATE TABLE reviews (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    rating INT CHECK (rating >= 1 AND rating <= 5),
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (product_id) REFERENCES products(id)
);

CREATE TABLE wishlist (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (product_id) REFERENCES products(id)
);

-- ============================================
-- COUPONS & USER_COUPONS
-- ============================================

CREATE TABLE coupons (
    id INT PRIMARY KEY AUTO_INCREMENT,
    code VARCHAR(50) UNIQUE NOT NULL,
    description TEXT,
    discount_type ENUM('PERCENTAGE', 'FIXED_AMOUNT') NOT NULL,
    discount_value DECIMAL(10,2) NOT NULL,
    min_purchase DECIMAL(10,2),
    max_discount DECIMAL(10,2),
    start_date DATETIME NOT NULL,
    end_date DATETIME NOT NULL,
    usage_limit INT,
    times_used INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================
-- CART & CART ITEMS (DÙNG PRODUCT_VARIANT_ID)
-- ============================================

CREATE TABLE cart (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    total BIGINT DEFAULT 0,          -- tổng sau giảm
    sub_total DECIMAL(10,2),         -- trước giảm
    discount_amount DECIMAL(10,2),   -- số tiền giảm
    coupon_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (coupon_id) REFERENCES coupons(id)
);

CREATE TABLE cart_item (
    id INT PRIMARY KEY AUTO_INCREMENT,
    cart_id INT NOT NULL,
    product_variant_id INT NOT NULL,
    quantity INT NOT NULL,
    price BIGINT NOT NULL,  -- giá tại thời điểm thêm
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (cart_id) REFERENCES cart(id),
    FOREIGN KEY (product_variant_id) REFERENCES product_variants(id)
);

-- ============================================
-- ORDERS & ORDER ITEMS
-- ============================================

CREATE TABLE order_details (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    total BIGINT NOT NULL,           -- sau giảm
    sub_total DECIMAL(10,2),         -- trước giảm
    discount_amount DECIMAL(10,2) DEFAULT 0,
    coupon_id INT,
    order_address VARCHAR(255),
    status VARCHAR(50) DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (coupon_id) REFERENCES coupons(id)
);

CREATE TABLE order_item (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    product_variant_id INT NOT NULL,
    quantity INT NOT NULL,
    price BIGINT NOT NULL,   -- giá tại thời điểm đặt
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES order_details(id),
    FOREIGN KEY (product_variant_id) REFERENCES product_variants(id)
);

CREATE TABLE payment_details (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    amount DECIMAL(10,2),
    provider VARCHAR(255),
    status VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES order_details(id)
);

CREATE TABLE user_coupons (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    coupon_id INT NOT NULL,
    order_id INT NOT NULL,
    used_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (coupon_id) REFERENCES coupons(id),
    FOREIGN KEY (order_id) REFERENCES order_details(id)
);

USE `giayohoho`;

-- ============================================
-- ROLES
-- ============================================
INSERT INTO roles (id, name, description) VALUES
(1, 'ADMIN', 'Quản trị hệ thống'),
(2, 'CUSTOMER', 'Khách hàng mua giày');

-- ============================================
-- USERS
-- ============================================
-- Nhớ thay password hash cho đúng nếu cần (đây chỉ là placeholder)
INSERT INTO users (id, avatar, first_name, last_name, username, email, password, birth_of_date, phone_number) VALUES
(1, NULL, 'Admin', 'System', 'admin', 'admin@giayohoho.test', '$2y$12$oBFGneYinfRi1jYo94cuXeGaXNsszV1bRIyoKO4x.uHBtgdU2kNFC', '2000-01-01', '0900000001'),
(2, NULL, 'Nguyen', 'Van A', 'nguyenvana', 'user1@giayohoho.test', '$2y$12$oBFGneYinfRi1jYo94cuXeGaXNsszV1bRIyoKO4x.uHBtgdU2kNFC', '2002-05-10', '0900000002'),
(3, NULL, 'Tran', 'Thi B', 'tranthib', 'user2@giayohoho.test', '$2y$12$oBFGneYinfRi1jYo94cuXeGaXNsszV1bRIyoKO4x.uHBtgdU2kNFC', '2001-09-20', '0900000003');

-- ============================================
-- USER_ROLES
-- ============================================
INSERT INTO user_roles (user_id, role_id) VALUES
(1, 1),
(2, 2),
(3, 2);

-- ============================================
-- ADDRESSES
-- ============================================
INSERT INTO addresses (id, user_id, address_line, ward, district, city, country, postal_code) VALUES
(1, 2, '123 Đường ABC', 'Phường 1', 'Quận 1', 'TP. Hồ Chí Minh', 'Việt Nam', '700000'),
(2, 3, '456 Đường XYZ', 'Phường 2', 'Quận Cầu Giấy', 'Hà Nội', 'Việt Nam', '100000');

-- ============================================
-- CATEGORIES (đa cấp)
-- ============================================
INSERT INTO categories (id, parent_id, name, slug, description) VALUES
(1, NULL, 'Giày nam', 'giay-nam', 'Tất cả giày cho nam'),
(2, NULL, 'Giày nữ', 'giay-nu', 'Tất cả giày cho nữ'),
(3, 1, 'Giày chạy bộ', 'giay-chay-bo', 'Giày chạy bộ đường nhựa, tập luyện'),
(4, 1, 'Giày đá bóng', 'giay-da-bong', 'Giày đá bóng sân cỏ nhân tạo'),
(5, 1, 'Sneaker lifestyle', 'sneaker-lifestyle', 'Giày sneaker thời trang');

-- ============================================
-- SURFACES
-- ============================================
INSERT INTO surfaces (id, code, name, description) VALUES
(1, 'road', 'Chạy đường nhựa', 'Phù hợp chạy bộ trên mặt đường bằng phẳng'),
(2, 'trail', 'Chạy trail', 'Phù hợp đường mòn, địa hình phức tạp'),
(3, 'treadmill', 'Chạy máy', 'Tối ưu cho chạy bộ trên máy'),
(4, 'walking', 'Đi bộ hằng ngày', 'Thích hợp đi bộ, sử dụng thường ngày');

-- ============================================
-- PRODUCTS
-- ============================================
INSERT INTO products (id, category_id, name, slug, brand, gender, base_price, description) VALUES
(1, 3, 'Ohoho Run Fast 1', 'ohoho-run-fast-1', 'Ohoho', 'unisex', 1500000, 'Giày chạy bộ nhẹ, đệm êm, phù hợp chạy 5-10km.'),
(2, 4, 'Ohoho Field Pro TF', 'ohoho-field-pro-tf', 'Ohoho', 'male', 1200000, 'Giày đá bóng sân cỏ nhân tạo, bám sân tốt.'),
(3, 5, 'Ohoho Street Style 2025', 'ohoho-street-style-2025', 'Ohoho', 'unisex', 1800000, 'Sneaker thời trang, phù hợp đi chơi, đi học.'),
(4, 3, 'Ohoho Cushion Max', 'ohoho-cushion-max', 'Ohoho', 'female', 1900000, 'Giày chạy bộ êm ái, hỗ trợ tốt cho người mới.');

-- ============================================
-- PRODUCT_SURFACES
-- ============================================
INSERT INTO product_surfaces (product_id, surface_id) VALUES
(1, 1),
(1, 3),
(2, 1),
(2, 4),
(3, 4),
(4, 1),
(4, 3);

-- ============================================
-- PRODUCT_SPECS_SHOES
-- ============================================
INSERT INTO product_specs_shoes
(id, product_id, cushioning_level, pronation_type, drop_mm, weight_grams, is_waterproof, is_reflective, upper_material, midsole_technology, outsole_technology)
VALUES
(1, 1, 'medium', 'neutral', 8.0, 260, FALSE, TRUE, 'Mesh thoáng khí', 'Foam Ohoho Energy', 'Cao su chống trượt'),
(2, 2, 'medium', 'stability', 6.0, 280, FALSE, FALSE, 'Da tổng hợp', 'Đệm EVA', 'Đinh TF cao su'),
(3, 3, 'low', 'neutral', 5.0, 300, FALSE, TRUE, 'Canvas', 'Đệm cao su nén', 'Cao su nguyên khối'),
(4, 4, 'high', 'neutral', 10.0, 250, TRUE, TRUE, 'Mesh + phủ chống nước', 'Foam Ohoho Cloud', 'Cao su ma sát cao');

-- ============================================
-- PRODUCT_VARIANTS
-- ============================================
INSERT INTO product_variants
(id, product_id, size, color, sku, stock, extra_price) VALUES
-- Ohoho Run Fast 1
(1, 1, '40', 'Đen/Trắng', 'RF1-40-BW', 10, 0),
(2, 1, '41', 'Đen/Trắng', 'RF1-41-BW', 8, 0),
(3, 1, '42', 'Xanh Navy', 'RF1-42-NV', 5, 50000),

-- Ohoho Field Pro TF
(4, 2, '39', 'Xanh Lá', 'FP-TF-39-GR', 12, 0),
(5, 2, '40', 'Xanh Lá', 'FP-TF-40-GR', 10, 0),
(6, 2, '41', 'Đỏ/Đen', 'FP-TF-41-RB', 7, 50000),

-- Ohoho Street Style 2025
(7, 3, '40', 'Trắng', 'SS25-40-WH', 15, 0),
(8, 3, '41', 'Trắng', 'SS25-41-WH', 10, 0),
(9, 3, '42', 'Đen', 'SS25-42-BK', 6, 50000),

-- Ohoho Cushion Max
(10, 4, '37', 'Hồng', 'CMAX-37-PK', 8, 0),
(11, 4, '38', 'Hồng', 'CMAX-38-PK', 6, 0),
(12, 4, '39', 'Tím', 'CMAX-39-PL', 4, 50000);

-- ============================================
-- PRODUCT_IMAGES
-- ============================================
INSERT INTO product_images
(id, product_id, product_variant_id, image_url, alt_text) VALUES
(1, 1, NULL, '/images/products/run-fast-1-main.jpg', 'Ohoho Run Fast 1 - tổng quan'),
(2, 1, 1, '/images/products/run-fast-1-40-bw.jpg', 'Run Fast 1 size 40 đen trắng'),
(3, 2, NULL, '/images/products/field-pro-tf-main.jpg', 'Ohoho Field Pro TF - tổng quan'),
(4, 3, NULL, '/images/products/street-style-2025-main.jpg', 'Ohoho Street Style 2025 - tổng quan'),
(5, 4, NULL, '/images/products/cushion-max-main.jpg', 'Ohoho Cushion Max - tổng quan');

-- ============================================
-- TAGS
-- ============================================
INSERT INTO tags (id, name, slug) VALUES
(1, 'Hàng mới', 'hang-moi'),
(2, 'Best seller', 'best-seller'),
(3, 'Giảm giá', 'giam-gia'),
(4, 'Chống nước', 'chong-nuoc');

-- ============================================
-- PRODUCT_TAGS
-- ============================================
INSERT INTO product_tags (product_id, tag_id) VALUES
(1, 1),
(1, 2),
(2, 2),
(3, 1),
(3, 3),
(4, 1),
(4, 4);

-- ============================================
-- COUPONS
-- ============================================
INSERT INTO coupons
(id, code, description, discount_type, discount_value, min_purchase, max_discount, start_date, end_date, usage_limit, times_used, is_active)
VALUES
(1, 'GIAY10', 'Giảm 10% cho toàn bộ đơn hàng từ 1.000.000đ', 'PERCENTAGE', 10.00, 1000000.00, 300000.00,
 '2025-01-01 00:00:00', '2025-12-31 23:59:59', 100, 0, TRUE),
(2, 'FREESHIP30', 'Giảm 30K phí ship cho đơn từ 300K', 'FIXED_AMOUNT', 30000.00, 300000.00, 30000.00,
 '2025-01-01 00:00:00', '2025-06-30 23:59:59', 200, 0, TRUE);

-- ============================================
-- WISHLIST
-- ============================================
INSERT INTO wishlist (id, user_id, product_id) VALUES
(1, 2, 1),
(2, 2, 3),
(3, 3, 4);

-- ============================================
-- CART
-- ============================================
INSERT INTO cart (id, user_id, total, sub_total, discount_amount, coupon_id) VALUES
(1, 2, 2700000, 3000000.00, 300000.00, 1);

-- ============================================
-- CART_ITEM
-- ============================================
INSERT INTO cart_item (id, cart_id, product_variant_id, quantity, price) VALUES
(1, 1, 1, 1, 1500000),
(2, 1, 7, 1, 1800000);

-- ============================================
-- ORDER_DETAILS
-- ============================================
INSERT INTO order_details
(id, user_id, total, sub_total, discount_amount, coupon_id, order_address, status)
VALUES
(1, 2, 2700000, 3000000.00, 300000.00, 1,
 '123 Đường ABC, Phường 1, Quận 1, TP. Hồ Chí Minh', 'completed');

-- ============================================
-- ORDER_ITEM
-- ============================================
INSERT INTO order_item
(id, order_id, product_variant_id, quantity, price) VALUES
(1, 1, 1, 1, 1500000),
(2, 1, 7, 1, 1800000);

-- ============================================
-- PAYMENT_DETAILS
-- ============================================
INSERT INTO payment_details
(id, order_id, amount, provider, status) VALUES
(1, 1, 2700000.00, 'VNPay', 'paid');

-- ============================================
-- USER_COUPONS
-- ============================================
INSERT INTO user_coupons (id, user_id, coupon_id, order_id) VALUES
(1, 2, 1, 1);

-- ============================================
-- REVIEWS
-- ============================================
INSERT INTO reviews (id, user_id, product_id, rating, comment) VALUES
(1, 2, 1, 5, 'Giày chạy rất êm, mang đi chạy 5km rất ok.'),
(2, 3, 3, 4, 'Sneaker đẹp, dễ phối đồ.'),
(3, 2, 2, 4, 'Giày đá bóng bám sân, đi hơi cứng nhưng ổn.');
