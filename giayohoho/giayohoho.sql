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
