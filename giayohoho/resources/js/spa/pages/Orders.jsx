import React, { useEffect, useState } from 'react'
import api from '../api'

export default function Orders() {
  const [orders, setOrders] = useState([])

  useEffect(() => {
    api.get('/auth/orders').then(res => setOrders(res.data))
  }, [])

  return (
    <div>
      <h2>Đơn hàng của bạn</h2>
      <ul>
        {orders.map(o => (
          <li key={o.id}>
            #{o.id} • {o.status} • Tổng: {o.total}
          </li>
        ))}
      </ul>
    </div>
  )
}

