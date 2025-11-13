import React, { useEffect, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import api from '../api'

export default function Cart() {
  const [cart, setCart] = useState(null)
  const [code, setCode] = useState('')
  const nav = useNavigate()

  const load = async () => {
    const res = await api.get('/auth/cart')
    setCart(res.data)
  }

  useEffect(() => { load() }, [])

  const updateQty = async (id, quantity) => {
    await api.put(`/auth/cart/items/${id}`, { quantity })
    load()
  }

  const removeItem = async (id) => {
    await api.delete(`/auth/cart/items/${id}`)
    load()
  }

  const apply = async () => {
    await api.post('/auth/cart/apply-coupon', { code })
    load()
  }

  if (!cart) return <p>Đang tải...</p>

  return (
    <div>
      <h2>Giỏ hàng</h2>
      <ul>
        {(cart.items || []).map(it => (
          <li key={it.id} style={{ marginBottom: 8 }}>
            <Link to={`/products/${it.variant.product.id}`}>{it.variant.product.name}</Link>
            <span style={{ marginLeft: 8 }}>{it.variant.size} • {it.variant.color}</span>
            <span style={{ marginLeft: 8 }}>x {it.quantity}</span>
            <button onClick={() => updateQty(it.id, it.quantity + 1)} style={{ marginLeft: 8 }}>+</button>
            <button onClick={() => updateQty(it.id, Math.max(1, it.quantity - 1))} style={{ marginLeft: 4 }}>-</button>
            <button onClick={() => removeItem(it.id)} style={{ marginLeft: 8 }}>Xoá</button>
          </li>
        ))}
      </ul>
      <div style={{ marginTop: 12 }}>
        <input placeholder="Mã giảm giá" value={code} onChange={e => setCode(e.target.value)} />
        <button onClick={apply} style={{ marginLeft: 8 }}>Áp dụng</button>
      </div>
      <p>Trước giảm: {cart.sub_total} • Giảm: {cart.discount_amount} • Tổng: {cart.total}</p>
      <button onClick={() => nav('/checkout')}>Đặt hàng</button>
    </div>
  )
}

