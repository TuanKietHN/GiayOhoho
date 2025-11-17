import React from 'react'
import { Link } from 'react-router-dom'

export default function StaffHome() {
  return (
    <div>
      <h2>Staff</h2>
      <ul>
        <li><Link to="/admin/orders">Đơn hàng</Link></li>
        <li><Link to="/admin/variants">Biến thể</Link></li>
        <li><Link to="/admin/reviews">Reviews</Link></li>
      </ul>
    </div>
  )
}

