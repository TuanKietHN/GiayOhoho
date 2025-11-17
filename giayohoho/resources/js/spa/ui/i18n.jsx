import React, { createContext, useContext, useState } from 'react'

const dicts = {
  vi: {
    products: 'Sản phẩm', cart: 'Giỏ hàng', orders: 'Đơn hàng', wishlist: 'Yêu thích', admin: 'Admin', staff: 'Nhân viên', logout: 'Đăng xuất', login: 'Đăng nhập', register: 'Đăng ký', pending: 'đang chờ giao hàng', paid: 'đã thanh toán', shipping: 'đang giao', done: 'hoàn tất', cancel: 'đã hủy',
    users: 'Người dùng', categories: 'Danh mục', variants: 'Biến thể', coupons: 'Giảm giá', reviews: 'Đánh giá', dashboard: 'Bảng điều khiển', back_home: 'Về trang chủ',
    order_details: 'Chi tiết đơn', customer: 'Khách', status: 'Trạng thái', total: 'Tổng', items: 'Sản phẩm', quantity: 'Số lượng', size: 'Kích thước', color: 'Màu', name: 'Tên',
    all: 'Tất cả', filter: 'Lọc', save: 'Lưu', order_status_saved: 'Đã lưu trạng thái đơn hàng', cannot_update_completed_order: 'Không thể cập nhật đơn hàng đã hoàn tất',
    edit_product: 'Sửa sản phẩm', bulk_assign_category: 'Gán danh mục theo bộ lọc', create: 'Tạo', edit: 'Sửa', delete: 'Xoá',
    male: 'Nam', female: 'Nữ', unisex: 'Unisex', price: 'Giá', description: 'Mô tả', category_id: 'ID Danh mục', gender: 'Giới tính',
    price_from: 'Giá từ', price_to: 'Giá đến', execute: 'Thực hiện',
    edit_category: 'Sửa danh mục', slug_description: 'Slug là định danh URL, ví dụ: running-shoes',
    low_stock_warning: 'Cảnh báo sắp hết hàng', threshold: 'Ngưỡng', stock: 'Tồn kho', edit_variant: 'Sửa biến thể', extra_price: 'Giá thêm',
    edit_coupon: 'Sửa coupon', percentage: 'Phần trăm', fixed_amount: 'Cố định', value: 'Giá trị',
    orders_by_status: 'Đơn hàng theo trạng thái', orders_status_distribution: 'Phân bố trạng thái đơn hàng', recent_orders: 'Đơn hàng gần đây',
    total_orders: 'Tổng đơn hàng', total_revenue: 'Tổng doanh thu', order_status_ratio: 'Tỷ lệ trạng thái đơn hàng', revenue_by_week: 'Doanh thu theo tuần', management: 'Quản lý',
    email: 'Email', username: 'Tên đăng nhập', roles: 'Vai trò', assign_roles: 'Gán vai trò', roles_placeholder: 'Nhập vai trò, ví dụ: admin,staff', assign: 'Gán', search_placeholder: 'Tìm kiếm...', search: 'Tìm kiếm',
    slug: 'Slug', order_status_ratio: 'Tỷ lệ trạng thái đơn hàng', revenue_by_week: 'Doanh thu theo tuần', management: 'Quản lý',
    order_status_saved: 'Đã lưu trạng thái đơn hàng', cannot_update_completed_order: 'Không thể cập nhật đơn hàng đã hoàn tất',
    orders_by_status: 'Đơn hàng theo trạng thái', orders_status_distribution: 'Phân bố trạng thái đơn hàng', recent_orders: 'Đơn hàng gần đây'
  },
  en: {
    products: 'Products', cart: 'Cart', orders: 'Orders', wishlist: 'Wishlist', admin: 'Admin', staff: 'Staff', logout: 'Logout', login: 'Login', register: 'Register', pending: 'pending', paid: 'paid', shipping: 'shipping', done: 'done', cancel: 'cancel',
    users: 'Users', categories: 'Categories', variants: 'Variants', coupons: 'Coupons', reviews: 'Reviews', dashboard: 'Dashboard', back_home: 'Back Home',
    order_details: 'Order Details', customer: 'Customer', status: 'Status', total: 'Total', items: 'Items', quantity: 'Qty', size: 'Size', color: 'Color', name: 'Name',
    all: 'All', filter: 'Filter', save: 'Save', order_status_saved: 'Order status saved', cannot_update_completed_order: 'Cannot update completed order',
    edit_product: 'Edit Product', bulk_assign_category: 'Bulk Assign Category', create: 'Create', edit: 'Edit', delete: 'Delete',
    male: 'Male', female: 'Female', unisex: 'Unisex', price: 'Price', description: 'Description', category_id: 'Category ID', gender: 'Gender',
    price_from: 'Price From', price_to: 'Price To', execute: 'Execute',
    edit_category: 'Edit Category', slug_description: 'Slug is the URL identifier, e.g.: running-shoes',
    low_stock_warning: 'Low Stock Warning', threshold: 'Threshold', stock: 'Stock', edit_variant: 'Edit Variant', extra_price: 'Extra Price',
    edit_coupon: 'Edit Coupon', percentage: 'Percentage', fixed_amount: 'Fixed Amount', value: 'Value',
    orders_by_status: 'Orders by Status', orders_status_distribution: 'Orders Status Distribution', recent_orders: 'Recent Orders',
    total_orders: 'Total Orders', total_revenue: 'Total Revenue', order_status_ratio: 'Order Status Ratio', revenue_by_week: 'Revenue by Week', management: 'Management',
    email: 'Email', username: 'Username', roles: 'Roles', assign_roles: 'Assign Roles', roles_placeholder: 'Enter roles, e.g.: admin,staff', assign: 'Assign', search_placeholder: 'Search...', search: 'Search',
    slug: 'Slug', order_status_ratio: 'Order Status Ratio', revenue_by_week: 'Revenue by Week', management: 'Management',
    order_status_saved: 'Order status saved', cannot_update_completed_order: 'Cannot update completed order',
    orders_by_status: 'Orders by Status', orders_status_distribution: 'Orders Status Distribution', recent_orders: 'Recent Orders'
  }
}

const I18nCtx = createContext({ lang: 'vi', t: (k) => k, setLang: () => {} })

export function I18nProvider({ children }) {
  const [lang, setLang] = useState('vi')
  const t = (k) => (dicts[lang] && dicts[lang][k]) || k
  return <I18nCtx.Provider value={{ lang, t, setLang }}>{children}</I18nCtx.Provider>
}

export function useI18n() { return useContext(I18nCtx) }