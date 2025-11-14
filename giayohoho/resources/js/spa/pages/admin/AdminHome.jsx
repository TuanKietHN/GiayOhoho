import React from 'react'
import { Link } from 'react-router-dom'

export default function AdminHome() {
  return (
    <div>
      <h2>Admin</h2>
      <ul>
        <li><Link to="/admin/users">Người dùng</Link></li>
        <li><Link to="/admin/orders">Đơn hàng</Link></li>
        <li><Link to="/admin/products">Sản phẩm</Link></li>
        <li><Link to="/admin/categories">Danh mục</Link></li>
        <li><Link to="/admin/variants">Biến thể</Link></li>
        <li><Link to="/admin/coupons">Coupons</Link></li>
        <li><Link to="/admin/reviews">Reviews</Link></li>
      </ul>
    </div>
  )
}

